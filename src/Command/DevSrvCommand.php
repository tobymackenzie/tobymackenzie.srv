<?php
namespace TJM\TMCom\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\TMCom\Dev;

#[AsCommand(
	name: 'dev:srv',
	aliases: ['dev', 'vagrant', 'srv', 'srv:build', 'srv:deploy', 'srv:web'],
	description: 'Control dev server with vagrant'
)]
class DevSrvCommand extends Command{
	public Dev $devService;
	public function __construct(Dev $devService){
		$this->devService = $devService;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('do', InputArgument::REQUIRED, 'Vagrant commands to run on server(s).')
			->addOption('server', 's', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Name of vagrant server(s) to run commands on.')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$command = $input->getArgument('command');
		$server = $input->getOption('server');
		if(substr($command, 0, 4) === 'srv:'){
			$server[] = substr($command, 4);
		}
		$this->devService->controlSrv($server, $input->getArgument('do'));
		return 0;
	}
}
