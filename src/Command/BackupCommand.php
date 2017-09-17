<?php
namespace TJM\TMCom\Command;
use DateTime;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;

class BackupCommand extends Base{
	protected function configure(){
		$this
			->setName('backup')
			->setDescription('Back up server group.')
			->addArgument('group', InputArgument::OPTIONAL, 'Name of server group to back up.  Matches YAML file in "provision" directory.', 'public')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$container = $this->getContainer();
		$projectPath = $container->getParameter('paths.project');
		chdir($projectPath);
		$group = $input->getArgument('group');
		switch($group){
			case 'public':
				//-! should come from config
				$host = 'tobymackenzie.com';
				$user = 'ubuntu';
				$date = new DateTime();
				$date = $date->format('Ymd-His');
				foreach([
					'letsencrypt'=> [
						'dest'=> '/Volumes/LetsEncrypt'
						,'src'=> '/etc/letsencrypt/'
					]
					,'tmcom files'=> [
						'dest'=> '/Volumes/Backup/tmcom/tmfiles'
						,'src'=> '/var/www/sites/tobymackenzie.com/app/files/'
					]
				] as $name=> $config){
					if(is_dir($config['dest']) && is_writable($config['dest'])){
						passthru("rsync -e ssh -aPvx --delete --link-dest='../_latest' --modify-window=10 --rsync-path='sudo rsync' {$user}@{$host}:{$config['src']} {$config['dest']}/tmp-{$date} && mv {$config['dest']}/tmp-{$date} {$config['dest']}/{$date} && ln -nfs {$config['dest']}/{$date} {$config['dest']}/_latest", $return);
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
