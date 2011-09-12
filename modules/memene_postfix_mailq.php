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


class postfix_mailq extends zabbixCommon {
	private $_exec_response;
	private $_stats_array;
    private $_queues = array("incoming","active","deferred","hold");
	private $_data = array();

 	public function __construct() {  
 	    $this->execStats();
 	    $this->postToZabbix();
	}
	public function execStats(){
        foreach($this->_queues as $queue) {
            exec("qshape " . $queue . " | grep TOTAL | awk '{print \$2}'",$response,$return);
                    $this->_data[] = array($queue=>$return); 
        }
	}
	private function postToZabbix() {
		foreach ( $this->_data as $key => $var ) {
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
