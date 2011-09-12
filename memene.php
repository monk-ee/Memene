<?php
//we have to declare this here
$GLOBALS['memene']['config_directory'] = "/etc/zabbix/";
//require all include files
require_once($GLOBALS['memene']['config_directory']."/config/memene_config.php");
require_once($GLOBALS['memene']['config_directory']."/common/memene_common.php");

class zabbixMonitor {
	protected $software_version = 1;
	private $module;
	private $api_version;

	public function __construct() {
		if ($GLOBALS['memene']['debug_mode']) zabbixCommon::debugLog(get_class($this));
		$this->dat = $GLOBALS['memene']['config_directory']."zabbix.dat";
		$this->utime = $GLOBALS['memene']['config_directory']."zabbix.utime";
		$this->dtime = $GLOBALS['memene']['config_directory']."zabbix.dtime";
		$this->includeModuleFiles();
		$this->initializeModuleClasses();
		$this->versionCheck();
	}

	private function includeModuleFiles() {
		//read modules global and include accordingly
		if (!isset($GLOBALS['modules'])) {
			$error = "Hmmm, I cannot see my module configuration.";
			if ($GLOBALS['memene']['debug_mode']) {
				zabbixCommon::debugLog($error);
			} else {
				throw new Exception($error);
			}
		}
		if (!is_array($GLOBALS['modules'])) {
			$error = "Modules global is not an array, I give up!";
			if ($GLOBALS['memene']['debug_mode']) {
				zabbixCommon::debugLog($error);
			} else {
				throw new Exception($error);
			}
		}
		if (count($GLOBALS['modules']) < 1) {
			$error = "No modules defined, you need to specify at least one module in my configuration.";
			if ($GLOBALS['memene']['debug_mode']) {
				zabbixCommon::debugLog($error);
			} else {
				throw new Exception($error);
			}
		}
		foreach($GLOBALS['modules'] as $value) {
			$module_file = $GLOBALS['memene']['config_directory']."modules/memene_". $value .".php";
			if(is_file($module_file)) {
				include_once($module_file);
			} else {
				$error = "You have defined a module that is not in the modules directory.";
				if ($GLOBALS['memene']['debug_mode']) {
					zabbixCommon::debugLog($error);
				} else {
					throw new Exception($error);
				}
			}
		}
	}
	private function initializeModuleClasses() {
		foreach($GLOBALS['modules'] as $value) {
			eval("\$this->module['".$value."'] = new ".$value."();");
		}
		echo 1;
		exit(0);
	}
	private function versionCheck() {
		//this function is ready for the zabbix api
	}

}

$zm = new zabbixMonitor();
?>
