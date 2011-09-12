<?php
/**
 * @desc zabbix nginx statitics collector 
 * @author monkee
 * 
 * Prerequisites:
 * NginxHttpStubStatusModule
 * libcurl
 * 
 * Changelog:
	Version 0.1:   12:06 20100408 first bash at this script - based on stats module from another project
 */


require_once("zabbix-common.php");

class apacheZabbix extends zabbixCommon {
	private $_server_name = "http://localhost/nginx_status";
	private $_curl_response;
	private $_stats_array;
	private $_data = array();

 	public function __construct() {  
 	    //$this->curlToStats();
 	    $this->fakeCurl();
 	    $this->parentServerGeneration();
 	    $this->serverUptime();
 	    //$this->logData();
 	    //$this->postToZabbix();
 	    $this->dumpData();
	}
	public function curlToStats(){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$this->_server_name);
       	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       	curl_setopt($ch, CURLOPT_TIMEOUT, 36000);
		$this->_curl_response = curl_exec($ch);        
		if (curl_errno($ch)) {
        	//print curl_error($ch);
			return false;
       	} else {
        	curl_close($ch);
			return true;
		}
	}
	private function fakeCurl() {
		$this->_curl_response = @file_get_contents("./working/apache_status.html");
	}
	private function parentServerGeneration() {
		//<dt>Parent Server Generation: 5</dt> 
	 	$pattern = '/Parent Server Generation:\s([0-9]+)/';
		preg_match($pattern, $this->_curl_response, $matches);
		$this->_data[] = array("parent_server_generation",$matches[1]);	
	}
	private function serverUptime() {
		//<dt>Server uptime:  2 minutes 12 seconds</dt> 
	 	$pattern = '/Server uptime:\s([0-9a-z\ ]+)\<\/dt\>/';
		preg_match($pattern, $this->_curl_response, $matches);
		$this->_data[] = array("server_uptime",$matches[1]);	
	}
	private function logData() {
		$this->data[] = array("active"=>$this->_stats_array[2]);
		$this->data[] = array("reading"=>$this->_stats_array[11]);
		$this->data[] = array("writing"=>$this->_stats_array[13]);
		$this->data[] = array("waiting"=>$this->_stats_array[15]);
	}
	private function dumpData() {
		foreach($this->_data as $key=>$value) {
			var_dump($value);
		}	
	}
	private function postToZabbix() {
		foreach ( $this->data as $key => $var ) {
			foreach ($var as $subkey=>$subval) {
				echo "$subkey | $subval \r\n";
				$this->zabbix_post('apache_status',$subkey,$subval);
			}			
		}
		echo 1;
		exit(0);	
	}		
}

$ss = new apacheZabbix();
?>
