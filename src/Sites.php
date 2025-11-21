<?php
namespace TJM\TMCom;
class Sites{
	protected array $aliases = [];
	protected string $sitesPath = __DIR__ . '/../../sites';
	public function __construct(string $sitesPath, ?array $aliases = null){
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
	public function hasComposer(string $site){
		return file_exists($this->getPath($site) . '/composer.json');
	}
}
