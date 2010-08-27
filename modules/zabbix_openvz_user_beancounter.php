<?php
/**
 * @desc openvzuser bean counter 
 * @author monkee
 * 
 * 
 * Changelog: Version 0.1:   16:40 20109816 first bash at this script
 *        uid  resource                     held              maxheld              barrier                limit              failcnt
      116:  kmemsize                  5486754              7904792             14372700             14790164                    0
            lockedpages                     0                    0                  256                  256                    0
            privvmpages                 38083                66526                65536                69632                   11
            shmpages                        2                    2                21504                21504                    0
            dummy                           0                    0                    0                    0                    0
            numproc                        21                   36                  240                  240                    0
            physpages                   29013                56238                    0  9223372036854775807                    0
            vmguarpages                     0                    0                33792  9223372036854775807                    0
            oomguarpages                29013                56238                26112  9223372036854775807                    0
            numtcpsock                      7                   47                  360                  360                    0
            numflock                        3                   16                  188                  206                    0
            numpty                          1                    1                   16                   16                    0
            numsiginfo                      0                    4                  256                  256                    0
            tcpsndbuf                  214360               946120              1720320              2703360                    0
            tcprcvbuf                  114688               324536              1720320              2703360                    0
            othersockbuf               166464               248920              1126080              2097152                    0
            dgramrcvbuf                     0                17440               262144               262144                    0
            numothersock                  175                  224                 1000                 1000                    0
            dcachesize                 306114               385929              3409920              3624960                    0
            numfile                       607                 1044                 9312                 9312                    0
            dummy                           0                    0                    0                    0                    0
            dummy                           0                    0                    0                    0                    0
            dummy                           0                    0                    0                    0                    0
            numiptent                      10                   10                  128                  128                    0         
 */

class openvz_user_beancounter extends zabbixCommon {
	private $_bean_file;
	private $_stats_array;
	private $_data = array();

 	public function __construct() {  
        if ($GLOBALS['zabbix']['debug_mode']) zabbixCommon::debugLog(get_class($this)); 
        $this->setPHPGlobals();
        $this->zabbix_config();
 	    $this->getBeanFile();
 	    $this->explodeData();
 	    $this->logData();
 	    $this->postToZabbix();
	}
    private function setPHPGlobals() {
        error_reporting(E_ALL|E_STRICT);
        $this->time_zone = $GLOBALS['mysql_general']['tz'];
        date_default_timezone_set($this->time_zone);    
    }
	private function getBeanFile() {
		$this->_bean_file = @file_get_contents($GLOBALS['openvz_user_beancounter']['file']);
	}
	private function explodeData() {
        $this->_bean_file = preg_replace('/\s+/','|',$this->_bean_file);
	 	$this->_stats_array = explode("|",$this->_bean_file);	    
	}
	private function logData() {
        //resource                     held              maxheld              barrier                limit              failcnt  
		//kmemsize  10
        //Size of unswappable memory in bytes, allocated by the operating system kernel.
        $this->data[] = array("kmemsize_held"=>$this->_stats_array[11]);
		$this->data[] = array("kmemsize_maxheld"=>$this->_stats_array[12]);
		$this->data[] = array("kmemsize_barrier"=>$this->_stats_array[13]);
		$this->data[] = array("kmemsize_limit"=>$this->_stats_array[14]);
        $this->data[] = array("kmemsize_failcnt"=>$this->_stats_array[15]);
        //locked pages  16
        //Process pages not allowed to be swapped out (pages locked by mlock(2)).
        $this->data[] = array("lockedpages_held"=>$this->_stats_array[17]);
        $this->data[] = array("lockedpages_maxheld"=>$this->_stats_array[18]);
        $this->data[] = array("lockedpages_barrier"=>$this->_stats_array[19]);
        $this->data[] = array("lockedpages_limit"=>$this->_stats_array[20]);
        $this->data[] = array("lockedpages_failcnt"=>$this->_stats_array[21]);
        //privvmpages 22
        //Memory allocation limit.
        $this->data[] = array("privvmpages_held"=>$this->_stats_array[23]);
        $this->data[] = array("privvmpages_maxheld"=>$this->_stats_array[24]);
        $this->data[] = array("privvmpages_barrier"=>$this->_stats_array[25]);
        $this->data[] = array("privvmpages_limit"=>$this->_stats_array[26]);
        $this->data[] = array("privvmpages_failcnt"=>$this->_stats_array[27]);
        //shmpages 28
        //The total size of shared memory (IPC, shared anonymous mappings and tmpfs objects).
        $this->data[] = array("shmpages_held"=>$this->_stats_array[29]);
        $this->data[] = array("shmpages_maxheld"=>$this->_stats_array[30]);
        $this->data[] = array("shmpages_barrier"=>$this->_stats_array[31]);
        $this->data[] = array("shmpages_limit"=>$this->_stats_array[32]);
        $this->data[] = array("shmpages_failcnt"=>$this->_stats_array[33]);
        // dummy 34-39
        //numproc 40
        //Maximum number of processes and kernel-level threads allowed for this container.
        $this->data[] = array("numproc_held"=>$this->_stats_array[41]);
        $this->data[] = array("numproc_maxheld"=>$this->_stats_array[42]);
        $this->data[] = array("numproc_barrier"=>$this->_stats_array[43]);
        $this->data[] = array("numproc_limit"=>$this->_stats_array[44]);
        $this->data[] = array("numproc_failcnt"=>$this->_stats_array[45]);
        //physpages 46
        //Total number of RAM pages used by processes in this container.
        $this->data[] = array("physpages_held"=>$this->_stats_array[47]);
        $this->data[] = array("physpages_maxheld"=>$this->_stats_array[48]);
        $this->data[] = array("physpages_barrier"=>$this->_stats_array[49]);
        $this->data[] = array("physpages_limit"=>$this->_stats_array[50]);
        $this->data[] = array("physpages_failcnt"=>$this->_stats_array[51]);
        //vmguarpages 52
        //Memory allocation guarantee. This parameter controls how much memory is available to the Virtual Environment
        $this->data[] = array("vmguarpages_held"=>$this->_stats_array[53]);
        $this->data[] = array("vmguarpages_maxheld"=>$this->_stats_array[54]);
        $this->data[] = array("vmguarpages_barrier"=>$this->_stats_array[55]);
        $this->data[] = array("vmguarpages_limit"=>$this->_stats_array[56]);
        $this->data[] = array("vmguarpages_failcnt"=>$this->_stats_array[57]);
        //oomguarpages 58
        //The guaranteed amount of memory for the case the memory is ?over-booked? (out-of-memory kill guarantee).
        $this->data[] = array("oomguarpages_held"=>$this->_stats_array[59]);
        $this->data[] = array("oomguarpages_maxheld"=>$this->_stats_array[60]);
        $this->data[] = array("oomguarpages_barrier"=>$this->_stats_array[61]);
        $this->data[] = array("oomguarpages_limit"=>$this->_stats_array[62]);
        $this->data[] = array("oomguarpages_failcnt"=>$this->_stats_array[63]);
        //numtcpsock 64
        //Maximum number of TCP sockets.
        $this->data[] = array("numtcpsock_held"=>$this->_stats_array[65]);
        $this->data[] = array("numtcpsock_maxheld"=>$this->_stats_array[66]);
        $this->data[] = array("numtcpsock_barrier"=>$this->_stats_array[67]);
        $this->data[] = array("numtcpsock_limit"=>$this->_stats_array[68]);
        $this->data[] = array("numtcpsock_failcnt"=>$this->_stats_array[69]);
        //numflock 70
        //Number of file locks.
        $this->data[] = array("numflock_held"=>$this->_stats_array[71]);
        $this->data[] = array("numflock_maxheld"=>$this->_stats_array[72]);
        $this->data[] = array("numflock_barrier"=>$this->_stats_array[73]);
        $this->data[] = array("numflock_limit"=>$this->_stats_array[74]);
        $this->data[] = array("numflock_failcnt"=>$this->_stats_array[75]);
        //numpty 76
        //Number of pseudo-terminals.
        $this->data[] = array("numpty_held"=>$this->_stats_array[77]);
        $this->data[] = array("numpty_maxheld"=>$this->_stats_array[78]);
        $this->data[] = array("numpty_barrier"=>$this->_stats_array[79]);
        $this->data[] = array("numpty_limit"=>$this->_stats_array[80]);
        $this->data[] = array("numpty_failcnt"=>$this->_stats_array[81]);
        //numsiginfo 82
        //Number of siginfo structures.
        $this->data[] = array("numsiginfo_held"=>$this->_stats_array[83]);
        $this->data[] = array("numsiginfo_maxheld"=>$this->_stats_array[84]);
        $this->data[] = array("numsiginfo_barrier"=>$this->_stats_array[85]);
        $this->data[] = array("numsiginfo_limit"=>$this->_stats_array[86]);
        $this->data[] = array("numsiginfo_failcnt"=>$this->_stats_array[87]);
        //tcpsndbuf 88
        //The total size of buffers used to send data over TCP network connections. These socket buffers reside in ?low memory?.
        $this->data[] = array("tcpsndbuf_held"=>$this->_stats_array[89]);
        $this->data[] = array("tcpsndbuf_maxheld"=>$this->_stats_array[90]);
        $this->data[] = array("tcpsndbuf_barrier"=>$this->_stats_array[91]);
        $this->data[] = array("tcpsndbuf_limit"=>$this->_stats_array[92]);
        $this->data[] = array("tcpsndbuf_failcnt"=>$this->_stats_array[93]);
        //tcprcvbuf 94
        //The total size of buffers used to temporary store the data coming from TCP network connections. These socket buffers also reside in ?low memory?.
        $this->data[] = array("tcprcvbuf_held"=>$this->_stats_array[95]);
        $this->data[] = array("tcprcvbuf_maxheld"=>$this->_stats_array[96]);
        $this->data[] = array("tcprcvbuf_barrier"=>$this->_stats_array[97]);
        $this->data[] = array("tcprcvbuf_limit"=>$this->_stats_array[98]);
        $this->data[] = array("tcprcvbuf_failcnt"=>$this->_stats_array[99]);
        //othersockbuf 100
        //The total size of buffers used by local (UNIX-domain) connections between processes inside the system (such as connections to a local database server) and send buffers of UDP and other datagram protocols.
        $this->data[] = array("othersockbuf_held"=>$this->_stats_array[101]);
        $this->data[] = array("othersockbuf_maxheld"=>$this->_stats_array[102]);
        $this->data[] = array("othersockbuf_barrier"=>$this->_stats_array[103]);
        $this->data[] = array("othersockbuf_limit"=>$this->_stats_array[104]);
        $this->data[] = array("othersockbuf_failcnt"=>$this->_stats_array[105]);
        //numothersock 106
        //The total size of buffers used to temporary store the incoming packets of UDP and other datagram protocols.
        $this->data[] = array("numothersock_held"=>$this->_stats_array[107]);
        $this->data[] = array("numothersock_maxheld"=>$this->_stats_array[108]);
        $this->data[] = array("numothersock_barrier"=>$this->_stats_array[109]);
        $this->data[] = array("numothersock_limit"=>$this->_stats_array[110]);
        $this->data[] = array("numothersock_failcnt"=>$this->_stats_array[111]);
        //numothersock 112
        //Maximum number of non-TCP sockets (local sockets, UDP and other types of sockets).
        $this->data[] = array("numothersock_held"=>$this->_stats_array[113]);
        $this->data[] = array("numothersock_maxheld"=>$this->_stats_array[114]);
        $this->data[] = array("numothersock_barrier"=>$this->_stats_array[115]);
        $this->data[] = array("numothersock_limit"=>$this->_stats_array[116]);
        $this->data[] = array("numothersock_failcnt"=>$this->_stats_array[117]);
        //dcachesize 118
        //The total size of dentry and inode structures locked in memory.
        $this->data[] = array("dcachesize_held"=>$this->_stats_array[119]);
        $this->data[] = array("dcachesize_maxheld"=>$this->_stats_array[120]);
        $this->data[] = array("dcachesize_barrier"=>$this->_stats_array[121]);
        $this->data[] = array("dcachesize_limit"=>$this->_stats_array[122]);
        $this->data[] = array("dcachesize_failcnt"=>$this->_stats_array[123]);
        //numfile 124
        //Number of open files.
        $this->data[] = array("numfile_held"=>$this->_stats_array[125]);
        $this->data[] = array("numfile_maxheld"=>$this->_stats_array[126]);
        $this->data[] = array("numfile_barrier"=>$this->_stats_array[127]);
        $this->data[] = array("numfile_limit"=>$this->_stats_array[128]);
        $this->data[] = array("numfile_failcnt"=>$this->_stats_array[129]);
        //dummy
        //dummy
        //dummy
        //numiptent 148 
        //The number of NETFILTER (IP packet filtering) entries.   Also, large numiptent cause considerable slowdown of processing of network packets. It is not recommended to allow containers to create more than 200?300 numiptent.
        $this->data[] = array("numiptent_held"=>$this->_stats_array[149]);
        $this->data[] = array("numiptent_maxheld"=>$this->_stats_array[150]);
        $this->data[] = array("numiptent_barrier"=>$this->_stats_array[151]);
        $this->data[] = array("numiptent_limit"=>$this->_stats_array[152]);
        $this->data[] = array("numiptent_failcnt"=>$this->_stats_array[153]);
	}
	private function postToZabbix() {
		foreach ( $this->data as $key => $var ) {
			foreach ($var as $subkey=>$subval) {
				//echo "$subkey | $subval \r\n";
				$this->zabbix_post('openvz',$subkey,$subval);
			}			
		}
		echo 1;
		exit(0);	
	}		
}

?>
