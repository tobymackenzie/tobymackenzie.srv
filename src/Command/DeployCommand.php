<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\TMCom\Servers;

#[AsCommand(
	name: 'deploy',
	description: 'Deploy one or all sites.'
)]
class DeployCommand extends Command{
	protected Servers $servers;
	public function __construct(Servers $serversService){
		$this->servers = $serversService;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to deploy.  Matches name of site in sites folder, or an alias.', ['tobymackenzie.com', 'dev.tobymackenzie.com'])
			->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Name of server group to deploy to.  Matches YAML file in "provision" directory.', 'public')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		foreach($input->getArgument('site') as $site){
			$this->servers->deploy($site, $input->getOption('group'), $output);
		}
		return 0;
	}
}
