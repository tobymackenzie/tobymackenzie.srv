<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\ShellRunner\ShellRunner;
use TJM\TMCom\Dev;

#[AsCommand(
	name: 'update',
	description: 'Update site(s) dependencies (local, must deploy to update prod).'
)]
class UpdateCommand extends Command{
	protected Dev $dev;
	public function __construct(Dev $dev){
		$this->dev = $dev;
		parent::__construct();
	}
	protected function configure(){
		$this
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to update.  Matches name of site in sites folder, or an alias.')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		foreach($input->getArgument('site') as $site){
			$this->dev->update($site, $input->isInteractive());
		}
		return 0;
	}

	//==deployment
	protected function runComposer($site, $server, $subcommand = 'install'){
	}
}
