<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;
use TJM\TMCom\Dev;

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
	protected Dev $dev;
	public function __construct(Dev $dev){
		$this->dev = $dev;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to build.  Matches name of site in sites folder, or an alias.')
			->addOption('tasks', 't', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Build tasks to run.')
			->addOption('dist', 'd', InputOption::VALUE_REQUIRED, 'Which dist folder to build to.  May also change some characteristics of how build is done')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Force task to ignore checks for if rebuild needed.')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$command = explode(':', $input->getArgument('command'));
		$tasks = $input->getOption('tasks');
		if(count($command) === 1){
			if(empty($tasks)){
				$tasks = [''];
			}
		}else{
			array_unshift($tasks, $command[1]);
		}
		$verbosity = $output->getVerbosity();
		//--default to verbose
		if($verbosity === 32){
			$verbosity = true;
		}
		foreach($input->getArgument('site') as $site){
			$this->dev->build(
				site: $site,
				dist: $input->getOption('dist'),
				force: $input->getOption('force'),
				interactive: $input->isInteractive(),
				tasks: $tasks,
				verbosity: $verbosity
			);
		}
		return 0;
	}
}

