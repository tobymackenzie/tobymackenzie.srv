<?php
namespace TJM\TMCom;
use TJM\ShellRunner\ShellRunner;
use TJM\TMCom\Dev;
use TJM\TMCom\Sites;
class Dev{
	const SRV_ALIASES = [
		'down'=> 'halt',
		'shell'=> 'ssh',
		'stat'=> 'status',
		'stop'=> 'halt',
		'sync'=> 'rsync',
	];
	protected string $adminUser;
	protected string $buildSrv;
	protected string $devSrv;
	protected ShellRunner $shellRunner;
	protected Sites $sites;
	protected string $path = __DIR__ . '/..';
	public function __construct(
		string $projectPath,
		ShellRunner $shellRunner,
		Sites $sites,
		string $adminUser,
		string $buildSrv,
		string $devSrv
	){
		$this->adminUser = $adminUser;
		$this->buildSrv = $buildSrv;
		$this->devSrv = $devSrv;
		$this->path = $projectPath;
		$this->shellRunner = $shellRunner;
		$this->sites = $sites;
	}

	/*=====
	==build
	=====*/
	public function build(
		string $site,
		?string $dist = null,
		bool $force = false,
		bool $interactive = false,
		array $tasks = [],
		int|bool $verbosity = false
	){
		$site = $this->sites->getKey($site);
		if(!$this->isSrvRunning('build')){
			$this->startSrv('build');
		}
		if($site === '10k-gol.site'){
			$command = 'bin/build ' . implode(' ', $tasks);
		}else{
			$command = "bin/console build";
			foreach($tasks as $task){
				if($task){
					$command .= ' -t ' . $task;
				}
			}
			if($dist){
				$command .= ' -d ' . $dist;
			}
			if($force){
				$command .= ' -f';
			}
			if($verbosity === true || $verbosity > 32){
				if($verbosity > 128){
					$command .= ' -vvv';
				}elseif($verbosity > 64){
					$command .= ' -vv';
				}else{
					$command .= ' -v';
				}
			}
		}
		$this->shellRunner->run([
			'command'=> $command,
			"host"=> $this->adminUser . '@' . $this->buildSrv,
			'interactive'=> $interactive,
			"path"=> "/var/www/sites/{$site}/",
		]);
	}

	/*=====
	==provision
	=====*/
	public function provision(){
		$startingPath = getcwd();
		if($startingPath !== $this->projectPath){
			chdir($this->projectPath);
		}
		passthru('vagrant provision');
		if($startingPath !== $this->projectPath){
			chdir($startingPath);
		}
	}

	/*=====
	==update
	=====*/
	public function update(string $site, bool $interactive = false){
		$site = $this->sites->getKey($site);
		if($this->sites->hasComposer($site)){
			if(!$this->isSrvRunning('dev')){
				$this->startSrv('dev');
			}
			$command = "sudo fallocate -l 2G /tmp/_swapfile && sudo chmod 600 /tmp/_swapfile && sudo mkswap /tmp/_swapfile && sudo swapon /tmp/_swapfile && php -d memory_limit=-1 `which composer` update; sudo swapoff /tmp/_swapfile && sudo rm -f /tmp/_swapfile";
			if(!$interactive){
				$command = "export COMPOSER_DISCARD_CHANGES='stash' && {$command}";
			}
			$this->shellRunner->run([
				'command'=> $command,
				"host"=> $this->adminUser . '@' . $this->devSrv,
				'interactive'=> $interactive,
				"path"=> "/var/www/sites/{$site}/",
			]);
		}
	}

	/*====
	==server
	=====*/
	public function controlSrv(array|string $server, string $do, bool $interactive = true){
		if(is_array($server)){
			$server = implode(' ', $server);
		}
		if(!empty(self::SRV_ALIASES[$do])){
			$do = self::SRV_ALIASES[$do];
		}
		$cmd = 'cd ' . escapeshellarg($this->path) . ' && vagrant ' . $do . ' ' . $server;
		if($interactive){
			passthru($cmd);
		}else{
			return shell_exec($cmd);
		}
	}
	public function isSrvRunning(string $server){
		$output = $this->controlSrv($server, 'status', false);
		return strpos($output, 'is running') !== false;
	}
	public function srvStatus(array|string $server){
		$this->controlSrv($server, 'status');
	}
	public function startSrv(array|string $server){
		$this->controlSrv($server, 'up');
	}
	public function stopSrv(array|string $server){
		$this->controlSrv($server, 'halt');
	}
}
