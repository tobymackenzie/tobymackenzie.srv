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

				#==back up letsencrypt certs
				$dest = '/Volumes/LetsEncrypt';
				if(is_dir($dest) && is_writable($dest)){
					$date = new DateTime();
					$date = $date->format('Ymd-His');
					passthru("rsync -e ssh -aPvx --delete --link-dest='../_latest' --modify-window=10 --rsync-path='sudo rsync' {$user}@{$host}:/etc/letsencrypt/ {$dest}/tmp-{$date} && mv {$dest}/tmp-{$date} {$dest}/{$date} && ln -nfs {$dest}/{$date} {$dest}/_latest", $return);
					if($return){
						throw new Exception("backing up letsencrypt failed: running \`{$command}\`");
					}
				}else{
					error_log("not backing up letsencrypt data: path not writeable.");
				}
			break;
			default:
				throw new Exception("Backing up group '{$group}' not implemented.");
			break;
		}
	}
}
