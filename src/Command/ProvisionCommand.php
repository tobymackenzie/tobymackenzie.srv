<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\TMCom\Dev;
use TJM\TMCom\Servers;

#[AsCommand(
	name: 'provision',
	description: 'Provision server group.'
)]
class ProvisionCommand extends Command{
	protected Dev $dev;
	protected Servers $servers;
	public function __construct(Dev $dev, Servers $serversService){
		$this->dev = $dev;
		$this->servers = $serversService;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('group', InputArgument::REQUIRED, 'Name of server group to provision.  Matches YAML file in "provision" directory (public or dev).')
			->addOption('book', 'b', InputOption::VALUE_REQUIRED, 'Run playbook by name, from `provision/plays` folder')
			->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Don\'t change anything, just report what changes would be made.')
			->addOption('list-tasks', 'l', InputOption::VALUE_NONE, 'List tasks instead of run them')
			->addOption('start-at-task', null, InputOption::VALUE_REQUIRED, 'Start at task name')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		if($input->getArgument('group') === 'public'){
			$this->servers->provision(
				book: $input->getOption('book'),
				dryRun: $input->getOption('dry-run'),
				listTasks: $input->getOption('list-tasks'),
				startAtTask: $input->getOption('start-at-task')
			);
		}else{
			$this->dev->provision();
		}
		return 0;
	}
}
