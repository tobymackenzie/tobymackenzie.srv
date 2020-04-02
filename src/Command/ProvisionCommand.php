<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;

class ProvisionCommand extends Base{
	static public $defaultName = 'provision';
	protected function configure(){
		$this
			->setDescription('Provision server group.')
			->addArgument('group', InputArgument::REQUIRED, 'Name of server group to provision.  Matches YAML file in "provision" directory.')
			->addOption('book', 'b', InputOption::VALUE_REQUIRED, 'Run playbook by name, from `provision/plays` folder')
			->addOption('list-tasks', 'l', InputOption::VALUE_NONE, 'List tasks instead of run them')
			->addOption('start-at-task', null, InputOption::VALUE_REQUIRED, 'Start at task name')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$container = $this->getContainer();
		$projectPath = $container->getParameter('paths.project');
		chdir($projectPath);
		$group = $input->getArgument('group');
		switch($group){
			case 'dev':
				passthru('vagrant provision');
			break;
			case 'public':
				if($input->getOption('book')){
					$book = $projectPath . "/provision/plays/{$input->getOption('book')}.yml";
				}else{
					$book = $projectPath . "/provision/{$group}.yml";
				}
				$inventoryFile = $projectPath . "/provision/hosts/{$group}.yml";
				$command = "ansible-playbook --diff -i {$inventoryFile}";
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
