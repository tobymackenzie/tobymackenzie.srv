<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;
use TJM\ShellRunner\ShellRunner;

class BuildCommand extends Base{
	static public $defaultName = 'build';
	const TASKS = [
		'assets',
		'clear',
		'css',
		'js',
		'static',
		'svg',
		'webroot',
	];
	protected function configure(){
		$aliases = [];
		foreach(static::TASKS as $task){
			$aliases[] = 'build:' . $task;
		}
		$this
			->setDescription('Run local build command / tasks.')
			->setAliases($aliases)
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to build.  Matches name of site in sites folder, or an alias.')
			->addOption('tasks', 't', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Build tasks to run.')
		;
	}

	protected $shellRunner;
	public function __construct(ShellRunner $shellRunner){
		$this->shellRunner = $shellRunner;
		parent::__construct();
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$container = $this->getContainer();
		foreach($input->getArgument('site') as $site){
			switch($site){
				//==personal
				case 'tm':
				case 'tmcom':
				case 'tmweb':
					$site = 'tobymackenzie.com';
				break;
				case 'dev':
					$site = 'dev.tobymackenzie.com';
				break;
				//==personal - etc
				case 'priv':
				case 'private':
					$site = 'tmprivate';
				break;
				case '10kgol':
					$site = '10k-gol.site';
				break;
				//==clients
			}
			$command = explode(':', $input->getArgument('command'));
			$tasks = $input->getOption('tasks');
			if(count($command) === 1){
				if(empty($tasks)){
					$tasks = [''];
				}
			}else{
				array_unshift($tasks, $command[1]);
			}
			$command = "bin/console build";
			foreach($tasks as $task){
				if($task){
					$command .= ' -t ' . $task;
				}
			}
			$this->shellRunner->run([
				'command'=> $command,
				"host"=> '2b@192.168.56.7',
				'interactive'=> $input->isInteractive(),
				"path"=> "/var/www/sites/{$site}/",
			]);
		}
	}
}

