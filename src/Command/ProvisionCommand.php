<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProvisionCommand extends Command{
	static public $defaultName = 'provision';
	protected string $projectPath;
	public function __construct(string $projectPath){
		$this->projectPath = $projectPath;
		parent::__construct();
	}
	protected function configure(){
		$this
			->setDescription('Provision server group.')
			->addArgument('group', InputArgument::REQUIRED, 'Name of server group to provision.  Matches YAML file in "provision" directory.')
			->addOption('book', 'b', InputOption::VALUE_REQUIRED, 'Run playbook by name, from `provision/plays` folder')
			->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Don\'t change anything, just report what changes would be made.')
			->addOption('list-tasks', 'l', InputOption::VALUE_NONE, 'List tasks instead of run them')
			->addOption('start-at-task', null, InputOption::VALUE_REQUIRED, 'Start at task name')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		chdir($this->projectPath);
		$group = $input->getArgument('group');
		switch($group){
			case 'dev':
				passthru('vagrant provision');
			break;
			case 'public':
				if($input->getOption('book')){
					$book = $this->projectPath . "/provision/plays/{$input->getOption('book')}.yml";
				}else{
					$book = $this->projectPath . "/provision/{$group}.yml";
				}
				$inventoryFile = $this->projectPath . "/provision/hosts/{$group}.yml";
				$command = "ansible-playbook --diff -i {$inventoryFile}";
				if($input->getOption('dry-run')){
					$command .= ' --check';
				}
				if($input->getOption('list-tasks')){
					$command .= ' --list-tasks';
				}
				if($input->getOption('start-at-task')){
					$command .= ' --start-at-task "' . $input->getOption('start-at-task') . '"';
				}
				$command .= " {$book}";
				passthru($command, $return);
				if($return){
					throw new Exception("provisioning failed: running \`{$command}\`");
				}
			break;
			default:
				throw new Exception("Provisioning group '{$group}' not implemented.");
			break;
		}
	}
}
