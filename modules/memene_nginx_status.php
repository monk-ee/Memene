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


class nginx_status extends zabbixCommon {
	private $_curl_response;
	private $_stats_array;
	private $_data = array();

 	public function __construct() {  
 	    $this->curlToStats();
 	    $this->explodeData();
 	    $this->logData();
 	    $this->postToZabbix();
	}
	public function curlToStats(){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$GLOBALS['nginx_status']['host']);
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
	private function explodeData() {
	 	$this->_stats_array = explode(" ",$this->_curl_response);	
	}
	private function logData() {
		$this->data[] = array("active"=>$this->_stats_array[2]);
		$this->data[] = array("reading"=>$this->_stats_array[11]);
		$this->data[] = array("writing"=>$this->_stats_array[13]);
		$this->data[] = array("waiting"=>$this->_stats_array[15]);
	}
	private function postToZabbix() {
		foreach ( $this->data as $key => $var ) {
			foreach ($var as $subkey=>$subval) {
				echo "$subkey | $subval \r\n";
				$this->zabbix_post('nginx',$subkey,$subval);
			}			
		}
		echo 1;
		exit(0);	
	}		
}

?>
