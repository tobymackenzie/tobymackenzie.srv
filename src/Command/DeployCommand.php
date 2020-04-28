<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;
// use TJM\TMCom\Service\Sites;
use TJM\ShellRunner\ShellRunner;

class DeployCommand extends Base{
	static public $defaultName = 'deploy';
	protected function configure(){
		$this
			->setDescription('Deploy one or all sites.')
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to deploy.  Matches name of site in sites folder, or an alias.', ['tobymackenzie.com', 'dev.tobymackenzie.com'])
			->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Name of server group to deploy to.  Matches YAML file in "provision" directory.', 'public')
		;
	}

	protected $shellRunner;
	public function __construct(ShellRunner $shellRunner){
		$this->shellRunner = $shellRunner;
		parent::__construct();
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$container = $this->getContainer();
		$group = $input->getOption('group');
		switch($group){
			case 'dev':
				$server = 'ubuntu@10.9.8.7';
			break;
			case 'public':
			case 'prod':
				$server = 'ubuntu@tobymackenzie.com';
			break;
			default:
				throw new Exception("Unknown group {$group}");
			break;
		}
		foreach($input->getArgument('site') as $site){
			switch($site){
				case 'tobymackenzie.com':
				case 'tm':
				case 'tmcom':
				case 'tmweb':
					$site = 'tobymackenzie.com';
					$isComposerChanged = $this->isComposerChanged($site, $server);
					$output->writeln($this->syncSite($site, $server, $container->getParameter('paths.project') . "/config/sync/tmweb.exclude"));
					//-! users should come from config
					$output->writeln($this->setSitePermissions($site, $server, [
						"setfacl -dR -m u:www-data:rwX -m u:ubuntu:rwX var && setfacl -R -m u:www-data:rwX -m u:ubuntu:rwX var"
						,"setfacl -dR -m u:www-data:rwX -m u:ubuntu:rwX app/files/wp-uploads && setfacl -R -m u:www-data:rwX -m u:ubuntu:rwX app/files/wp-uploads"
					]));
					if($isComposerChanged){
						$this->runComposer($site, $server);
					}
				break;
				case 'dev.tobymackenzie.com':
				case 'dev':
					$site = 'dev.tobymackenzie.com';
					$output->writeln($this->syncSite($site, $server, $container->getParameter('paths.project') . "/config/sync/site.exclude"));
					$output->writeln($this->setSitePermissions($site, $server));
				break;
				default:
					throw new Exception("Site {$site} unknown");
				break;
			}
		}
	}

	//==deployment
	//-! should move all this out to service(s)
	protected function runComposer($site, $server){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		$interactive = true; //-! should be an option from input
		if($server === 'ubuntu@10.9.8.7'){
			$env = 'dev';
		}else{
			$env = 'prod';
		}
		$command = "composer install";
		if($env === 'prod'){
			$command = "export SYMFONY_ENV='prod' && {$command} --no-dev --optimize-autoloader";
		}
		if(!$interactive){
			$command = "export COMPOSER_DISCARD_CHANGES="
				. ($env === 'prod' ? 1 : "'stash'")
				. " && {$command}"
			;
		}
		return $this->shellRunner->run([
			'command'=> $command
			,"host"=> $server
			,'interactive'=> $interactive
			,"path"=> "/var/www/sites/{$site}/"
		]);
	}
	protected function isComposerChanged($site, $server){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		return (bool) preg_match('/[<>].* composer\.lock/', $this->shellRunner->run([
			'command'=> "rsync -ai --dry-run {$sitesPath}/{$site}/composer.lock {$server}:/var/www/sites/{$site}/composer.lock"
		]));
	}
	protected function syncSite($site, $server, $exclude = null){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		$syncOpts = "-Dilprt --copy-unsafe-links --delete";
		if($exclude){
			$syncOpts .= " --exclude-from={$exclude}";
		}
		return $this->shellRunner->run([
			'command'=> "rsync {$syncOpts} {$sitesPath}/{$site}/ {$server}:/var/www/sites/{$site}/"
		]);
	}
	protected function setSitePermissions($site, $server, $additional = null){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		//-! user should come from config
		$command = "sudo chown -R ubuntu:ubuntu .";
		$command .= " && sudo find . -type f -exec chmod go-wx {} \+";
		$command .= " && sudo find . -type d -exec chmod go-w {} \+";
		if($additional){
			if(is_array($additional)){
				$additional = implode(' && ', $additional);
			}
			$command .= " && {$additional}";
		}

		return $this->shellRunner->run([
			'command'=> $command
			,"host"=> $server
			,"path"=> "/var/www/sites/{$site}/"
		]);
	}
}
