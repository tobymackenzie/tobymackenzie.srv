<?php
namespace TJM\TMCom;
use DateTime;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\ShellRunner\ShellRunner;
use TJM\TMCom\Sites;
class Servers{
	protected array $backupPaths;
	protected string $projectPath = __DIR__ . '/../..';
	protected array $servers;
	protected ShellRunner $shellRunner;
	protected Sites $sites;
	protected string $sitesPath;
	public function __construct(
		array $backupPaths,
		string $projectPath,
		array $servers,
		ShellRunner $shellRunner,
		Sites $sites,
		string $sitesPath
	){
		$this->backupPaths = $backupPaths;
		$this->projectPath = $projectPath;
		$this->servers = $servers;
		$this->shellRunner = $shellRunner;
		$this->sites = $sites;
		$this->sitesPath = $sitesPath;
	}
	public function getServerData(string $key){
		$server = $this->servers[$key];
		if(is_string($server)){
			$server = $this->getServerData($server);
		}
		if($server && empty($server['ssh'])){
			$server['ssh'] = "{$server['user']}@{$server['host']}";
		}
		return $server;
	}

	//==backup
	public function backup(string $key, ?OutputInterface $output = null){
		$server = $this->getServerData($key);
		if(!$server){
			throw new Exception("Backing up group '{$key}' not implemented.");
		}
		$startingPath = getcwd();
		if($startingPath !== $this->projectPath){
			chdir($this->projectPath);
		}
		foreach($this->backupPaths as $destPath){
			$date = new DateTime();
			$date = $date->format('Ymd-His');
			if(!is_writable($destPath)){
				$err = 'Destination ' . $destPath . ' not writeable';
				if($output){
					$output->warn($err);
				}else{
					trigger_error($err, \E_USER_WARNING);
				}
				continue;
			}
			foreach([
				'db'=> [
					'dest'=> "{$destPath}/db"
					,'pre'=> "ssh {$server['ssh']} \"sudo -u backup /home/backup/bin/db-backup\""
					,'src'=> '/var/bu/db/'
				]
				,'letsencrypt'=> [
					'dest'=> '/Volumes/LetsEncryptBU'
					,'src'=> '/etc/letsencrypt/'
				]
				//-! should store all sites files in shared location to easily backup
				,'tmcom files'=> [
					'dest'=> "{$destPath}/tmfiles"
					,'src'=> '/var/www/sites/tobymackenzie.com/app/files/'
				]
				,'wrk'=> [
					'customOpts'=> '--exclude="/.*" --exclude="/bu/"'
					,'dest'=> "{$destPath}/cog"
					,'src'=> '/home/cog/'
				]
			] as $name=> $config){
				if(is_dir($config['dest']) && is_writable($config['dest'])){
					$customOpts = $config['customOpts'] ?? '';
					if(isset($config['pre']) && $config['pre']){
						passthru($config['pre'], $return);
						if($return){
							throw new Exception("Failed running command \`{$config['pre']}\`");
						}
					}
					passthru("rsync -e ssh -aPvxz --delete {$customOpts} --link-dest='../_latest' --modify-window=10 --rsync-path='sudo rsync' {$server['ssh']}:{$config['src']} {$config['dest']}/tmp-{$date} && mv {$config['dest']}/tmp-{$date} {$config['dest']}/{$date} && ln -nfs {$config['dest']}/{$date} {$config['dest']}/_latest", $return);
					if($return){
						$err = "Backing up {$name} failed.";
						if($output){
							$output->writeln($err);
						}else{
							trigger_error($err, \E_USER_WARNING);
						}
					}
				}else{
					trigger_error("not backing up {$name} data: path not writeable.");
				}
			}
		}
		if($startingPath !== $this->projectPath){
			chdir($startingPath);
		}
	}

	//==deploy
	public function deploy(string $site, string $key, ?OutputInterface $output = null){
		$server = $this->getServerData($key);
		if(!$server){
			throw new Exception("Backing up group '{$key}' not implemented.");
		}
		$site = $this->sites->getKey($site);
		switch($site){
			//==personal
			case 'tobymackenzie.com':
				$isComposerChanged = $this->isComposerChanged($site, $server);
				if($output){
					$output->writeln($this->syncSite($site, $server));
				}
				//-! users should come from config
				$result = $this->setSitePermissions($site, $server, [
					"setfacl -dR -m u:www-data:rwX -m u:{$server['user']}:rwX var && setfacl -R -m u:www-data:rwX -m u:{$server['user']}:rwX var"
					,"setfacl -dR -m u:www-data:rwX -m u:{$server['user']}:rwX app/files/wp-uploads && setfacl -R -m u:www-data:rwX -m u:{$server['user']}:rwX app/files/wp-uploads"
				]);
				if($output){
					$output->writeln($result);
				}
				if($isComposerChanged){
					$this->runComposer($site, $server);
				}else{
					$this->runComposer($site, $server, 'run post');
				}
			break;
			case '10k-gol.site':
			case 'dev.tobymackenzie.com':
				$result = $this->syncSite($site, $server);
				if($output){
					$output->writeln($result);
				}
				$result = $this->setSitePermissions($site, $server);
				if($output){
					$output->writeln($result);
				}
			break;
			//==personal - etc
			case 'tmprivate':
				$result = $this->syncSite($site, $server);
				if($output){
					$output->writeln($result);
				}
				$result = $this->setSitePermissions($site, $server, [
					"setfacl -dR -m u:www-data:rwX -m u:{$server['user']}:rwX var && setfacl -R -m u:www-data:rwX -m u:{$server['user']}:rwX var"
					,"setfacl -dR -m u:www-data:rwX -m u:{$server['user']}:rwX files && setfacl -R -m u:www-data:rwX -m u:{$server['user']}:rwX files"
				]);
				if($output){
					$output->writeln($result);
				}
			break;
			//==clients
			case 'dw':
				$isComposerChanged = $this->isComposerChanged($site, $server);
				$result = $this->syncSite($site, $server);
				if($output){
					$output->writeln($result);
				}
				$result = $this->setSitePermissions($site, $server, [
					'sudo setfacl -R -m u:www-data:rwx tmp',
					'sudo setfacl -dR -m u:www-data:rwx tmp',
					'sudo setfacl -R -m u:www-data:rwx logs',
					'sudo setfacl -dR -m u:www-data:rwx logs',
				]);
				if($output){
					$output->writeln($result);
				}
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
	protected function isComposerChanged($site, $server){
		try{
			$syncTest = $this->shellRunner->run([
				'command'=> "rsync -aiz --dry-run {$this->sitesPath}/{$site}/composer.lock {$server['ssh']}:/var/www/sites/{$site}/composer.lock"
			]);
		}catch(Exception $e){
			return true;
		}
		return (bool) preg_match('/[<>].* composer\.lock/', $syncTest);
	}
	protected function runComposer($site, $server, $subcommand = 'install'){
		$interactive = true; //-! should be an option from input
		$env = $server['env'] ?? 'prod';
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
			,"host"=> $server['ssh']
			,'interactive'=> $interactive
			,"path"=> "/var/www/sites/{$site}/"
		]);
	}
	protected function runForSite($site, $server, $command, $interactive = true){
		return $this->shellRunner->run([
			'command'=> $command
			,"host"=> $server['ssh']
			,'interactive'=> $interactive
			,"path"=> "/var/www/sites/{$site}/"
		]);
	}
	protected function setSitePermissions($site, $server, $additional = null){
		//-! user / group should come from config
		$command = "sudo chown -R {$server['user']}:{$server['user']} .";
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
			,"host"=> $server['ssh']
			,"path"=> "/var/www/sites/{$site}/"
		]);
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
				'command'=> "rsync {$syncOpts} {$this->sitesPath}/{$site}{$path} {$server['ssh']}:/var/www/sites/{$site}{$path}"
			]);
		}
		return implode("\n", $result);
	}

	//--provision
	public function provision(
		?string $book = null,
		bool $dryRun = false,
		bool $listTasks = false,
		?string $startAtTask = null
	){
		if($book){
			$book = $this->projectPath . "/provision/plays/{$book}.yml";
		}else{
			$book = $this->projectPath . "/provision/{$group}.yml";
		}
		$inventoryFile = $this->projectPath . "/provision/hosts/{$group}.yml";
		$command = "ansible-playbook --diff -i {$inventoryFile}";
		if($dryRun){
			$command .= ' --check';
		}
		if($listTasks){
			$command .= ' --list-tasks';
		}
		if($startAtTask){
			$command .= ' --start-at-task "' . $startAtTask . '"';
		}
		$command .= " {$book}";
		passthru($command, $return);
		if($return){
			throw new Exception("provisioning failed: running \`{$command}\`");
		}
	}
}
