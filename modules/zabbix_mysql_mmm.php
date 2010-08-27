<?php
/**
* a bit of a reworking of the original mmm monitor script
* there have been no improvements just a a faithful reproduction of the original script
* 
*  You will need to allow the zabbix user access to read the files in the /etc/mysql-mmm directory
*  Something like chgrp zabbix *
*  then set permissions chmod 640 *
* 
*  You will need to make sure zabbix user has access to read pid agent/monitor file
*  This varies from package to package OS to OS
*  eg /var/run/mysql-mmm
*  Something like chgrp -R zabbix at the folder level
*/

class mysql_mmm extends zabbixCommon {
	private $daemon_type_agent = "AGENT";
	private $daemon_type_monitor = "MONITOR";
	private $data;
	private $daemon_type;
	private $mmm_agent_config;
	private $mmm_monitor_config;
	private $included_config;
	private $included_config_contents;
	private $pid_path_monitor;
	private $pid_path_agent;
	private $agent_running;
	private $monitor_running;
	
	public function __construct() {
		if ($GLOBALS['zabbix']['debug_mode']) zabbixCommon::debugLog(get_class($this));
		$this->setPHPGlobals();
		$this->zabbix_config();	
		$this->lookForAgent();
		$this->lookForMonitor();
		$this->determineMonitorType();
		switch($this->daemon_type) {
			case $this->daemon_type_agent:
				$this->agentType();
				$this->gatherAgentData();
				$this->fakeMonitorData();
				break;
			case $this->daemon_type_monitor:
				$this->monitorType();
				$this->gatherAgentData();
				$this->gatherMonitorData();
				break;
			default:
				throw new Exception("Could not determine daemon type, kaboom!");	
		}
		$this->postToZabbix();
	}
	private function setPHPGlobals() {
		error_reporting(E_ALL|E_STRICT);
	}
	private function postToZabbix() {
		foreach ( $this->data as $key => $var ) {
			foreach ($var as $subkey=>$subval) {
				if ($GLOBALS['zabbix']['debug_mode']) zabbixCommon::debugLog("mysql_mmm: $subkey | $subval");
				$this->zabbix_post('mysql_mmm',$subkey,$subval);
			}			
		}
		echo 1;
		exit(0);	
	}
	private function lookForAgent() {
		// check
        if (!file_exists($GLOBALS['mysql_mmm']['agent_config'])) {
        	$this->mmm_agent_config = false;
        	$this->agent_running = false;
        	return;
		} 
		$this->mmm_agent_config = file_get_contents($GLOBALS['mysql_mmm']['agent_config']);
        preg_match("/\s*include\s*(\S*)/i",$this->mmm_agent_config,$parts);
        $this->included_config = $GLOBALS['mysql_mmm']['config_path'] . $parts[1];	
		//check again
		if (!file_exists($this->included_config)) {
			throw new Exception("Could not locate included configuration on filesystem.");
		}
		$this->included_config_contents = file_get_contents($this->included_config);
		$this->mmm_agent_config = preg_replace("/\s*include\s*(\S*)/i", $this->included_config_contents, $this->mmm_agent_config);
	}
	private function lookForMonitor() {
		if (file_exists($GLOBALS['mysql_mmm']['monitor_config'])) {
			$this->mmm_monitor_config = file_get_contents($GLOBALS['mysql_mmm']['monitor_config']);
		} else {
			$this->monitor_running = false;
			$this->mmm_monitor_config = false;
		}
	}			
	private function determineMonitorType() {
		if (!$this->mmm_agent_config && !$this->mmm_monitor_config) {
			throw new Exception("Neither monitor or agent configs can be found, please check configuration!");
		}
		if ($this->mmm_agent_config && $this->mmm_monitor_config) {
			//if ( DEBUG ) echo "This machine has configuration on it for both monitor and agent!\n"; - not sure what to do with this info
			$this->daemon_type = $this->daemon_type_monitor;
			return;
		}
		if ($this->mmm_agent_config) {
			$this->daemon_type = $this->daemon_type_agent;
			return;
		}
		if ($this->mmm_monitor_config) {
			// TODO: find a better way to determine whether this machine is a monitor by parsing the config files
			$this->daemon_type = $this->daemon_type_monitor;
		}
	}
	private function agentType() {
		//agent config found, check the pid_path
		preg_match("/\s*pid_path\s*(\S*)/i",$this->mmm_agent_config,$parts);
		$this->pid_path_agent = $parts[1];
		if (!$this->pid_path_agent) {
			// i suppose this is an error	
			$this->agent_running = false;
			return;
		}
		if (!file_exists($this->pid_path_agent)) {
			$this->agent_running = false;
			return;
		}
		$pid = file_get_contents($this->pid_path_agent);
        if (!$pid || !$this->processRunning($pid)) {
        	$this->agent_running = false;
        	return;
        }
        $this->agent_running = true;
	}
	private function monitorType() {
		// get the path of the pid file from the config
		preg_match("/\s*pid_path\s*(\S*)/i",$this->mmm_monitor_config,$parts);
		$this->pid_path_monitor = $parts[1];
		if (!$this->pid_path_monitor) {
			// i suppose this is an error	
			$this->monitor_running = false;
			return;	
		}
		// check that that file exists, get it's contents and check that the pid is running
		if (!file_exists($this->pid_path_monitor)) {
			$this->monitor_running = false;
			return; 
		}
		$pid = file_get_contents($this->pid_path_monitor);
		if (!$pid || !$this->processRunning($pid)) {
			$this->monitor_running = false;
			return;
		}
		$this->monitor_running = true;
	}
	private function processRunning($pid){
	     $cmd = "ps $pid";
	     exec($cmd, $output, $result);
	     if(count($output) >= 2){
	          return true;
	     }
	     return false;
	 }
	 private function gatherAgentData() {
	 	 $agent_running = (int)($this->agent_running);
	 	 $this->data[] = array("agent_running"=>$agent_running);	
	 }
	 private function gatherMonitorData() {
	 	// get the cluster mode (active or passive)
		$mmm_status = exec('/usr/sbin/mmm_control mode');	
		$cluster_active = (int)($mmm_status == "ACTIVE");
		$this->data[] = array("cluster_active"=>$cluster_active);	
	 	//monitor
	 	$monitor_running = (int)($this->monitor_running);
	 	$this->data[] = array("monitor_running"=>$monitor_running);	
	 }
	 private function fakeMonitorData() {
	 	 //rationale here is that values shouldnt just disappear
		$this->data[] = array("cluster_active"=>0);	
	 	$this->data[] = array("monitor_running"=>0);	
	 }
}




