<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\ShellRunner\ShellRunner;
use TJM\TMCom\Service\Sites;

class UpdateCommand extends Command{
	static public $defaultName = 'update';
	protected ShellRunner $shellRunner;
	protected Sites $sites;
	protected string $sitesPath;
	protected function configure(){
		$this
			->setDescription('Update site(s) dependencies (local, must deploy to update prod).')
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to update.  Matches name of site in sites folder, or an alias.')
		;
	}

	public function __construct(
		ShellRunner $shellRunner,
		Sites $sites
	){
		$this->shellRunner = $shellRunner;
		$this->sites = $sites;
		parent::__construct();
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		foreach($input->getArgument('site') as $site){
			$site = $this->sites->getKey($site);
			$sitePath = $this->sites->getPath($site);
			if(file_exists($sitePath . '/composer.json')){
				$interactive = $input->isInteractive(); //-! should be an option from input
				$command = "sudo fallocate -l 2G /tmp/_swapfile && sudo chmod 600 /tmp/_swapfile && sudo mkswap /tmp/_swapfile && sudo swapon /tmp/_swapfile && php -d memory_limit=-1 `which composer` update; sudo swapoff /tmp/_swapfile && sudo rm -f /tmp/_swapfile";
				if(!$interactive){
					$command = "export COMPOSER_DISCARD_CHANGES='stash' && {$command}";
				}
				$this->shellRunner->run([
					'command'=> $command,
					"host"=> '2b@tm.t',
					'interactive'=> $interactive,
					"path"=> "/var/www/sites/{$site}/",
				]);
			}
		}
		return 0;
	}

	//==deployment
	protected function runComposer($site, $server, $subcommand = 'install'){
	}
}
