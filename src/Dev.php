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
	public function controlSrv($server, $do){
		if(is_array($server)){
			$server = implode(' ', $server);
		}
		passthru('cd ' . escapeshellarg($this->path) . ' && vagrant ' . $do . ' ' . $server);
	}
	public function srvStatus($server){
		$this->controlSrv($server, 'status');
	}
	public function startSrv($server){
		$this->controlSrv($server, 'up');
	}
	public function stopSrv($server){
		$this->controlSrv($server, 'halt');
	}
}
