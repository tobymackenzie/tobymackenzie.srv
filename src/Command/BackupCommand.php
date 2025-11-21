<?php
namespace TJM\TMCom\Command;
use DateTime;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
	name: 'backup',
	description: 'Back up server group.'
)]
class BackupCommand extends Command{
	protected string $projectPath;
	public function __construct(string $projectPath){
		$this->projectPath = $projectPath;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('group', InputArgument::OPTIONAL, 'Name of server group to back up.  Matches YAML file in "provision" directory.', 'public')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		chdir($this->projectPath);
		$group = $input->getArgument('group');
		switch($group){
			case 'public':
				//-! should come from config
				$host = 'tobymackenzie.com';
				$user = '2b';
				$date = new DateTime();
				$date = $date->format('Ymd-His');
				$destPath = '/Volumes/Backup';
				foreach([
					'db'=> [
						'dest'=> "{$destPath}/tmcom/db"
						,'pre'=> "ssh {$user}@{$host} \"sudo -u backup /home/backup/bin/db-backup\""
						,'src'=> '/var/bu/db/'
					]
					,'letsencrypt'=> [
						'dest'=> '/Volumes/LetsEncrypt'
						,'src'=> '/etc/letsencrypt/'
					]
					//-! should store all sites files in shared location to easily backup
					,'tmcom files'=> [
						'dest'=> "{$destPath}/tmcom/tmfiles"
						,'src'=> '/var/www/sites/tobymackenzie.com/app/files/'
					]
					//-!! should come from config
					,'wrk'=> [
						'customOpts'=> '--exclude="/.*"'
						,'src'=> '/home/wrk/'
						,'dest'=> "{$destPath}/tmcom/wrk"
					]
				] as $name=> $config){
					if(is_dir($config['dest']) && is_writable($config['dest'])){
						$customOpts = $config['customOpts'] ?? '';
						if(isset($config['pre']) && $config['pre']){
							passthru($config['pre'], $return);
							if($return){
								throw new Exception("Failed running command \`{$config['pre']}\`");
							}
						}
						passthru("rsync -e ssh -aPvxz --delete {$customOpts} --link-dest='../_latest' --modify-window=10 --rsync-path='sudo rsync' {$user}@{$host}:{$config['src']} {$config['dest']}/tmp-{$date} && mv {$config['dest']}/tmp-{$date} {$config['dest']}/{$date} && ln -nfs {$config['dest']}/{$date} {$config['dest']}/_latest", $return);
						if($return){
							throw new Exception("backing up {$name} failed: running \`{$command}\`");
						}
					}else{
						error_log("not backing up {$name} data: path not writeable.");
					}
				}
			break;
			default:
				throw new Exception("Backing up group '{$group}' not implemented.");
			break;
		}
	}
}
