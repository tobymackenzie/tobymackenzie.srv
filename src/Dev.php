<?php
namespace TJM\TMCom;
use TJM\ShellRunner\ShellRunner;
use TJM\TMCom\Dev;
use TJM\TMCom\Sites;
class Dev{
	protected ShellRunner $shellRunner;
	protected Sites $sites;
	protected string $path = __DIR__ . '/..';
	public function __construct(
		string $projectPath,
		ShellRunner $shellRunner,
		Sites $sites
	){
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
		array $tasks = []
	){
		$site = $this->sites->getKey($site);
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
		}
		$this->shellRunner->run([
			'command'=> $command,
			"host"=> '2b@192.168.56.7',
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
			$command = "sudo fallocate -l 2G /tmp/_swapfile && sudo chmod 600 /tmp/_swapfile && sudo mkswap /tmp/_swapfile && sudo swapon /tmp/_swapfile && php -d memory_limit=-1 `which composer` update; sudo swapoff /tmp/_swapfile && sudo rm -f /tmp/_swapfile";
			if(!$interactive){
				$command = "export COMPOSER_DISCARD_CHANGES='stash' && {$command}";
			}
			$this->shellRunner->run([
				'command'=> $command,
				"host"=> '2b@tm.t',
				'interactive'=> $interactive,
				"path"=> "/var/www/sites/{$site}/",
			]);
		}
	}

	/*=====
	==server
	=====*/
	public function controlSrv(array|string $server, string $do, bool $interactive = true){
		if(is_array($server)){
			$server = implode(' ', $server);
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
