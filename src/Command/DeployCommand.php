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
				throw new Exception("'dev' group cannot currently be deployed with this command.  Deployment is handled automatically via Vagrant, with sites mounted into place.");
				//-! we need a way to test deployment locally while still supporting dev with local files
				$server = 'ubuntu@10.9.9.9';
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
				//==personal
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
					}else{
						$this->runComposer($site, $server, 'run post');
					}
				break;
				case 'dev.tobymackenzie.com':
				case 'dev':
					$site = 'dev.tobymackenzie.com';
					$output->writeln($this->syncSite($site, $server, $container->getParameter('paths.project') . "/config/sync/site.exclude"));
					$output->writeln($this->setSitePermissions($site, $server));
				break;
				//==personal - etc
				case '10kgol':
					$site = '10k-gol.site';
					$output->writeln($this->syncSite($site, $server, $container->getParameter('paths.project') . "/config/sync/10kgol.exclude"));
					$output->writeln($this->setSitePermissions($site, $server));
				break;
				//==clients
				case 'ctm':
				case 'cheftiffanymiller.com':
					$site = 'cheftiffanymiller.com';
					$isComposerChanged = $this->isComposerChanged($site, $server);
					$output->writeln($this->syncSite($site, $server, $container->getParameter('paths.project') . "/config/sync/ctm.exclude"));
					$output->writeln($this->setSitePermissions($site, $server, [
						//-! special permissions coming from site `bin/ready` script. should be a better way to do this that works for both local and remote and can account for site specific stuff without allowing the site to run arbitrary code as root
					]));
					if($isComposerChanged){
						$this->runComposer($site, $server);
					}
					$this->runForSite($site, $server, 'bin/ready');
				break;
				case 'dw':
					$site = 'dw';
					$isComposerChanged = $this->isComposerChanged($site, $server);
					$output->writeln($this->syncSite($site, $server, $container->getParameter('paths.project') . "/config/sync/dw.exclude"));
					$output->writeln($this->setSitePermissions($site, $server, [
						'sudo setfacl -R -m u:www-data:rwx tmp',
						'sudo setfacl -dR -m u:www-data:rwx tmp',
						'sudo setfacl -R -m u:www-data:rwx logs',
						'sudo setfacl -dR -m u:www-data:rwx logs',
					]));
					if($isComposerChanged){
						$this->runComposer($site, $server);
					}
					//-! ideally we'd set database config on first run only and then run migrations
					//-! `vi config/app_local.php`
					$this->runForSite($site, $server, 'bin/cake migrations migrate');
				break;
				default:
					throw new Exception("Site {$site} unknown");
				break;
			}
		}
	}

	//==deployment
	//-! should move all this out to service(s)
	protected function runForSite($site, $server, $command, $interactive = true){
		return $this->shellRunner->run([
			'command'=> $command
			,"host"=> $server
			,'interactive'=> $interactive
			,"path"=> "/var/www/sites/{$site}/"
		]);
	}
	protected function runComposer($site, $server, $subcommand = 'install'){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		$interactive = true; //-! should be an option from input
		if($server === 'ubuntu@10.9.9.9'){
			$env = 'dev';
		}else{
			$env = 'prod';
		}
		$command = "composer {$subcommand}";
		if($env === 'prod'){
			$command = "export SYMFONY_ENV='prod' && {$command} --no-dev";
			if($subcommand === 'install'){
				$command .= " --optimize-autoloader";
			}
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
		try{
			$syncTest = $this->shellRunner->run([
				'command'=> "rsync -aiz --dry-run {$sitesPath}/{$site}/composer.lock {$server}:/var/www/sites/{$site}/composer.lock"
			]);
		}catch(Exception $e){
			return true;
		}
		return (bool) preg_match('/[<>].* composer\.lock/', $syncTest);
	}
	protected function syncSite($site, $server, $exclude = null){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		$paths = ["/"];
		if($site === 'tobymackenzie.com'){
			$paths[] = "/dist/public/_assets/svgs/";
		}
		$syncOpts = "-Dilprtz --copy-unsafe-links --delete";
		if($exclude){
			$syncOpts .= " --exclude-from={$exclude}";
		}
		$result = [];
		foreach($paths as $path){
			$result[] = $this->shellRunner->run([
				'command'=> "rsync {$syncOpts} {$sitesPath}/{$site}{$path} {$server}:/var/www/sites/{$site}{$path}"
			]);
		}
		return implode("\n", $result);
	}
	protected function setSitePermissions($site, $server, $additional = null){
		$sitesPath = $this->getContainer()->getParameter('paths.sites');
		//-! user / group should come from config
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
