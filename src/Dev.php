<?php
namespace TJM\TMCom;
class Dev{
	protected string $path = __DIR__ . '/..';
	public function __construct(string $projectPath){
		$this->path = $projectPath;
	}

	/*=====
	==server
	=====*/
	public function controlSrv(array|string $server, string $do){
		if(is_array($server)){
			$server = implode(' ', $server);
		}
		passthru('cd ' . escapeshellarg($this->path) . ' && vagrant ' . $do . ' ' . $server);
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
