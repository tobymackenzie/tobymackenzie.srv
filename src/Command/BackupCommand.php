<?php
namespace TJM\TMCom\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\TMCom\Servers;

#[AsCommand(
	name: 'backup',
	description: 'Back up server group.'
)]
class BackupCommand extends Command{
	protected Servers $servers;
	public function __construct(Servers $serversService){
		$this->servers = $serversService;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('group', InputArgument::OPTIONAL, 'Name of server group to back up.  Matches YAML file in "provision" directory.', 'public')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->servers->backup($input->getArgument('group'));
		return 0;
	}
}
