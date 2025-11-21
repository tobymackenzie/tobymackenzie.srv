<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;
use TJM\ShellRunner\ShellRunner;
use TJM\TMCom\Sites;

#[AsCommand(
	name: 'build',
	aliases: [
		'build:assets',
		'build:clear',
		'build:css',
		'build:js',
		'build:static',
		'build:svg',
		'build:webroot',
	],
	description: 'Run local build command / tasks.',
)]
class BuildCommand extends Base{
	protected ShellRunner $shellRunner;
	protected Sites $sites;
	protected function configure(){
		$this
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to build.  Matches name of site in sites folder, or an alias.')
			->addOption('tasks', 't', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Build tasks to run.')
			->addOption('dist', 'd', InputOption::VALUE_REQUIRED, 'Which dist folder to build to.  May also change some characteristics of how build is done')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Force task to ignore checks for if rebuild needed.')
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
		$container = $this->getContainer();
		foreach($input->getArgument('site') as $site){
			$site = $this->sites->getKey($site);
			$command = explode(':', $input->getArgument('command'));
			$tasks = $input->getOption('tasks');
			if(count($command) === 1){
				if(empty($tasks)){
					$tasks = [''];
				}
			}else{
				array_unshift($tasks, $command[1]);
			}
			if($site === '10k-gol.site'){
				$command = 'bin/build ' . implode(' ', $tasks);
			}else{
				$command = "bin/console build";
				foreach($tasks as $task){
					if($task){
						$command .= ' -t ' . $task;
					}
				}
				if($input->getOption('dist')){
					$command .= ' -d ' . $input->getOption('dist');
				}
				if($input->getOption('force')){
					$command .= ' -f';
				}
			}
			$this->shellRunner->run([
				'command'=> $command,
				"host"=> '2b@192.168.56.7',
				'interactive'=> $input->isInteractive(),
				"path"=> "/var/www/sites/{$site}/",
			]);
		}
		return 0;
	}
}

