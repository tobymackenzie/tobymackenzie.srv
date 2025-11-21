<?php
namespace TJM\TMCom\Command;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\TMCom\Service\Sites;
use TJM\ShellRunner\ShellRunner;

class DeployCommand extends Command{
	static public $defaultName = 'deploy';
	protected string $projectPath;
	protected ShellRunner $shellRunner;
	protected Sites $sites;
	protected string $sitesPath;
	protected function configure(){
		$this
			->setDescription('Deploy one or all sites.')
			->addArgument('site', InputArgument::IS_ARRAY, 'Site to deploy.  Matches name of site in sites folder, or an alias.', ['tobymackenzie.com', 'dev.tobymackenzie.com'])
			->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Name of server group to deploy to.  Matches YAML file in "provision" directory.', 'public')
		;
	}

	public function __construct(
		string $projectPath,
		Sites $sites,
		ShellRunner $shellRunner,
		string $sitesPath
	){
		$this->projectPath = $projectPath;
		$this->sites = $sites;
		$this->shellRunner = $shellRunner;
		$this->sitesPath = $sitesPath;
		parent::__construct();
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$group = $input->getOption('group');
		switch($group){
			case 'dev':
				$server = 'ubuntu@deploy.t';
			break;
			case 'public':
			case 'prod':
				$server = '2b@tobymackenzie.com';
			break;
			default:
				throw new Exception("Unknown group {$group}");
			break;
		}
		foreach($input->getArgument('site') as $site){
			$site = $this->sites->getKey($site);
			switch($site){
				//==personal
				case 'tobymackenzie.com':
					$isComposerChanged = $this->isComposerChanged($site, $server);
					$output->writeln($this->syncSite($site, $server));
					//-! users should come from config
					$output->writeln($this->setSitePermissions($site, $server, [
						"setfacl -dR -m u:www-data:rwX -m u:2b:rwX var && setfacl -R -m u:www-data:rwX -m u:2b:rwX var"
						,"setfacl -dR -m u:www-data:rwX -m u:2b:rwX app/files/wp-uploads && setfacl -R -m u:www-data:rwX -m u:2b:rwX app/files/wp-uploads"
					]));
					if($isComposerChanged){
						$this->runComposer($site, $server);
					}else{
						$this->runComposer($site, $server, 'run post');
					}
				break;
				case 'dev.tobymackenzie.com':
					$output->writeln($this->syncSite($site, $server));
					$output->writeln($this->setSitePermissions($site, $server));
				break;
				//==personal - etc
				case 'tmprivate':
					$output->writeln($this->syncSite($site, $server));
					$output->writeln($this->setSitePermissions($site, $server, [
						"setfacl -dR -m u:www-data:rwX -m u:2b:rwX var && setfacl -R -m u:www-data:rwX -m u:2b:rwX var"
						,"setfacl -dR -m u:www-data:rwX -m u:2b:rwX files && setfacl -R -m u:www-data:rwX -m u:2b:rwX files"
					]));
				break;
				case '10k-gol.site':
					$output->writeln($this->syncSite($site, $server));
					$output->writeln($this->setSitePermissions($site, $server));
				break;
				//==clients
				case 'dw':
					$isComposerChanged = $this->isComposerChanged($site, $server);
					$output->writeln($this->syncSite($site, $server));
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
		return 0;
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
		try{
			$syncTest = $this->shellRunner->run([
				'command'=> "rsync -aiz --dry-run {$this->sitesPath}/{$site}/composer.lock {$server}:/var/www/sites/{$site}/composer.lock"
			]);
		}catch(Exception $e){
			return true;
		}
		return (bool) preg_match('/[<>].* composer\.lock/', $syncTest);
	}
	protected function syncSite($site, $server, $exclude = null){
		$paths = ["/"];
		if($site === 'tobymackenzie.com'){
			$paths[] = "/dist/public/_assets/svgs/";
		}
		$syncOpts = "-Dilprtz --copy-unsafe-links --delete";

		//--check for default site exclude file locations
		if(!isset($exclude)){
			foreach([
				'/config/srv/deploy.exclude',
				'/config/srv.exclude',
				'/srv/deploy.exclude',
			] as $path){
				$path = "{$this->sitesPath}/{$site}{$path}";
				if(file_exists($path)){
					$exclude = $path;
				}
			}
		}

		//--if no exclude is set, use default
		if(!isset($exclude)){
			$exclude = $this->projectPath . "/config/sync/site.exclude";
		}

		if($exclude){
			$syncOpts .= " --exclude-from={$exclude}";
		}
		$result = [];
		foreach($paths as $path){
			$result[] = $this->shellRunner->run([
				'command'=> "rsync {$syncOpts} {$this->sitesPath}/{$site}{$path} {$server}:/var/www/sites/{$site}{$path}"
			]);
		}
		return implode("\n", $result);
	}
	protected function setSitePermissions($site, $server, $additional = null){
		//-! user / group should come from config
		$command = "sudo chown -R 2b:2b .";
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
