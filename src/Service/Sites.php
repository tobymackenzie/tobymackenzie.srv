<?php
namespace TJM\TMCom\Service;
class Sites{
	protected array $aliases = [];
	protected $sitesPath = __DIR__ . '/../../sites';
	public function __construct($sitesPath, $aliases = null){
		if($aliases){
			$this->aliases = $aliases;
		}
		$this->sitesPath = $sitesPath;
	}
	public function getKey($site){
		if(!empty($this->aliases[$site])){
			$site = $this->aliases[$site];
		}
		return $site;
	}
	public function getPath($site = null){
		if(!empty($site)){
			$site = $this->getKey($site);
			return $this->sitesPath . '/' . $site;
		}else{
			return $this->sitesPath;
		}
	}
}
