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


class dbmail extends zabbixCommon {
        private $_exec_response;
    private $_ports = array("POP3"=>110,"IMAP"=>143,"LMTP"=>24,"SMTP"=>25,"IMAPS"=>993,"SSL-POP"=>995);
        private $_data = array();

        public function __construct() {
        $this->dat = $GLOBALS['memene']['config_directory']."zabbix.dat";
        $this->zabbix_config();
            $this->execStats();
            $this->postToZabbix();
        }
        public function execStats(){
        foreach($this->_ports as $portkey=>$port) {
                $exec_response = 0;
            exec("sudo /usr/bin/lsof -i :" . $port . " | grep -c TCP",$exec_response,$return);
            $this->_data[] = array($portkey=>$exec_response[0]);
        }
        }
        private function postToZabbix() {
                foreach ( $this->_data as $key => $var ) {
                        foreach ($var as $subkey=>$subval) {
                                //echo "$subkey | $subval \r\n";
                                $this->zabbix_post('dbmail',$subkey,$subval);
                        }
                }
                //echo 1;
                //exit(0);
        }
}

?>
