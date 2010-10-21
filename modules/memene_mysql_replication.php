<?php

 class mysql_replication extends zabbixCommon {
  	private $time_zone;
  	private $user;
  	private $password;
  	private $dbcon;
  	private $mysql_server = "localhost";
  	private $mysql_base = "mysql"; //i know this isnt likely to change anytime soon but i couldnt help myself
  	private $data;

	private $replication_role; //available states must be NONE,MASTER,SLAVE,BOTH,UNKNOWN
	private $replication_status = false;
	private $master_status;
	private $slave_status;
	private $master_enabled = false;
	private $master_binlog_position = 0;
	private $slave_enabled = false;
	private $slave_hosts = array();
	private $slave_host_count = 0;
	private $slave_io_state = false;
	private $slave_io_state_msg = "";
	private $slave_io_running = false;
	private $slave_sql_running = false;
	private $read_master_log_pos = 0;
	private $seconds_behind_master = 0;
	private $skip_counter = 0;
	private $exec_master_log_pos = 0;
	private $relay_log_space = 0;
	private $err_no =  0;
	private $err_msg = '';
	private $slave_errors = false;

  	public function __construct() {
  		if ($GLOBALS['zabbix']['debug_mode']) zabbixCommon::debugLog(get_class($this));
  		$this->dat = $GLOBALS['memene']['config_directory']."zabbix.dat";
		$this->utime = $GLOBALS['memene']['config_directory']."zabbix.utime";
		$this->dtime = $GLOBALS['memene']['config_directory']."zabbix.dtime";
  		$this->setPHPGlobals();
  		$this->zabbix_config();
  		$this->getInputValues();
  		$this->connectMysql();
  		if ($this->checkBinaryLogsEnabled()) {
  			$this->determineReplicationRole();
		}
  		switch($this->replication_role) {
  			case "NONE":
  				//do nothing we have nothing to check
  				break;
  			case "MASTER":
  				//i guess all we can check is whether we have slaves connected
  				$this->checkSlaveHosts();
  				$this->masterBinlogPosition();
  				break;
  			case "SLAVE":
  				//ok this is where all the magic happens
  				$this->slaveIOState();
  				$this->slaveIORunning();
  				$this->slaveSQLRunning();
  				$this->readMasterLogPos();
  				$this->secondsBehindMaster();
  				$this->skipCounter();
  				$this->execMasterLogPos();
  				$this->relayLogSpace();
  				$this->slaveErrors();
  				break;
  			case "BOTH":
  				$this->checkSlaveHosts();
  				$this->masterBinlogPosition();
  				$this->slaveIOState();
  				$this->slaveIORunning();
  				$this->slaveSQLRunning();
  				$this->readMasterLogPos();
  				$this->secondsBehindMaster();
  				$this->skipCounter();
  				$this->execMasterLogPos();
  				$this->relayLogSpace();
  				$this->slaveErrors();
  				break;
  			default:
  				//unknown
		}
		$this->setupData();
		$this->postToZabbix();

	}
	private function slaveIOState() {
		//possible states
		//Waiting for master to send event - (GOOD) The initial state before Connecting to master.
		//Waiting for master update -(GOOD) The thread is attempting to connect to the master.
		 //Connecting to master -(GOOD) The thread is attempting to connect to the master.
		//Checking master version -(GOOD) A state that occurs very briefly, after the connection to the master is established.
		//Registering slave on master -(GOOD) A state that occurs very briefly after the connection to the master is established.
		//Requesting binlog dump -(GOOD) A state that occurs very briefly, after the connection to the master is established. The thread sends to the master a request for the contents of its binary logs,starting from the requested binary log file name and position.
		//Waiting to reconnect after a failed binlog dump request -(BAD)If the binary log dump request failed (due to disconnection), the thread goes into this state while it sleeps, then tries to reconnect periodically. The interval between retries can be specified using the CHANGE MASTER TO statement or the --master-connect-retry option.
		//Reconnecting after a failed binlog dump request -(BAD) The thread is trying to reconnect to the master.
		//Waiting for master to send event -(GOOD) The thread has connected to the master and is waiting for binary log events to arrive. This can last for a long time if the master is idle. If the wait lasts for slave_net_timeout seconds, a timeout occurs. At that point, the thread considers the connection to be broken and makes an attempt to reconnect.
		//Queueing master event to the relay log -(GOOD) The thread has read an event and is copying it to the relay log so that the SQL thread can process it.
		//Waiting to reconnect after a failed master event read -(BAD)An error occurred while reading (due to disconnection). The thread is sleeping for the number of seconds set by the CHANGE MASTER TO statement or --master-connect-retry option (default 60) before attempting to reconnect.
		//Reconnecting after a failed master event read - (BAD) The thread is trying to reconnect to the master. When connection is established again, the state becomes Waiting for master to send event.
		//Waiting for the slave SQL thread to free enough relay log space -(BAD) You are using a nonzero relay_log_space_limit value, and the relay logs have grown large enough that their combined size exceeds this value. The I/O thread is waiting until the SQL thread frees enough space by processing relay log contents so that it can delete some relay log files.
		//Waiting for slave mutex on exit -(BAD) A state that occurs briefly as the thread is stopping.
		$this->slave_io_state_msg = $this->slave_status['Slave_IO_State'];

		switch ($this->slave_io_state_msg) {
			case "Waiting for master to send event":
			case "Waiting for master update":
			case "Connecting to master":
			case "Checking master version":
			case "Registering slave on master":
			case "Requesting binlog dump":
			case "Queueing master event to the relay log":
			case "Waiting for master to send event ":
				$this->slave_io_state = true;
				break;
			case "Waiting to reconnect after a failed binlog dump request":
			case "Reconnecting after a failed binlog dump request":
			case "Waiting to reconnect after a failed master event read":
			case "Reconnecting after a failed master event read":
			case "Waiting for the slave SQL thread to free enough relay log space":
			case "Waiting for slave mutex on exit ":
				$this->slave_io_state = false; //really this should be do nothing the value is already false
				break;
			default:
				$this->slave_io_state = false; //really this should be do nothing the value is already false
		}

	}
	private function masterBinlogPosition() {
		$this->master_binlog_position = $this->master_status['Position'];
	}
	private function  slaveIORunning() {
		if ($this->slave_status['Slave_IO_Running'] == "Yes") {
				$this->slave_io_running = true;
		}
		$this->slave_io_running = false;

	}
	private function slaveSQLRunning() {
		if ($this->slave_status['Slave_SQL_Running'] == "Yes") {
				$this->slave_sql_running = true;
		}
		$this->slave_sql_running = false;
	}
	private function readMasterLogPos() {
		$this->read_master_log_pos = $this->slave_status['Read_Master_Log_Pos'];
	}
	private function secondsBehindMaster() {
		$this->seconds_behind_master = $this->slave_status['Seconds_Behind_Master'];
	}
	private function skipCounter() {
		$this->skip_counter = $this->slave_status['Skip_Counter'];
	}
	private function execMasterLogPos() {
		$this->exec_master_log_pos = $this->slave_status['Exec_Master_Log_Pos'];
	}
	private function relayLogSpace() {
		$this->relay_log_space = $this->slave_status['Relay_Log_Space'];
	}
	private function slaveErrors() {
		//Last_Errno: 0
	    //Last_Error:
	    //just check if these are blank and o
	    $this->err_no = $this->slave_status['Last_Errno'];
	    $this->err_msg = $this->slave_status['Last_Error'];
	    if ($this->err_no == 0 && $this->err_msg == '') {
	    	$this->slave_errors = false;
		}
		$this->slave_errors = true;
	}
	private function checkSlaveHosts() {
		//needs report-host=<hostname or ip>  in my.cnf for each slave connecting
		/*
		> show slave hosts;
		+-----------+-------+------+-------------------+-----------+
		| Server_id | Host  | Port | Rpl_recovery_rank | Master_id |
		+-----------+-------+------+-------------------+-----------+
		|      1020 | bitey | 3306 |                 0 |       110 |
		+-----------+-------+------+-------------------+-----------+
		*/
		$result = mysql_query("show slave hosts;");
		if ( $result ) {
			while ($row = mysql_fetch_assoc($result)) {
				$this->slave_hosts[] = $row;
				$this->slave_host_count++;
			}
		}
	}
	private function setupData() {
		$this->data[] = array('replication_status' => (int) ($this->replication_status));
		$this->data[] = array('master_enabled' => (int) ($this->master_enabled));
		$this->data[] = array('master_binlog_position' => (int) ($this->master_binlog_position));
		$this->data[] = array('slave_enabled' => (int) ($this->slave_enabled));
		$this->data[] = array('slave_host_count' => (int) ($this->slave_host_count));
		$this->data[] = array('slave_io_state' => (int) ($this->slave_io_state));
		$this->data[] = array('slave_io_running' => (int) ($this->slave_io_running));
		$this->data[] = array('slave_sql_running' => (int) ($this->slave_io_running));
		$this->data[] = array('read_master_log_pos' => (int) ($this->read_master_log_pos));
		$this->data[] = array('seconds_behind_master' => (int) ($this->seconds_behind_master));
		$this->data[] = array('skip_counter' => (int) ($this->skip_counter));
		$this->data[] = array('exec_master_log_pos' => (int) ($this->exec_master_log_pos));
		$this->data[] = array('relay_log_space' => (int) ($this->relay_log_space));
		$this->data[] = array('slave_errors' => (int) ($this->slave_errors));
	}
	private function checkBinaryLogsEnabled() {
		//working on the assumption that if these are not turned on we cant have replication and bail early
		$result = mysql_query("show global variables where Variable_name = 'log_bin';");
		if ( $result ) {
			$row = mysql_fetch_assoc($result);
			if ($row['Value'] == "ON") {
				return true;
			} else {
				$this->replication_role = "NONE";
				return false;
			}
		} else {
			$this->replication_role = "NONE";
			return false;
		}
	}
	private function determineReplicationRole() {
		//probably should just check if slave status and master status are not empty recordsets
		$slave = $this->gatherSlaveStatus();
		$master = $this->gatherMasterStatus();
		if ($slave && $master) {
			$this->replication_role = "BOTH";
			$this->master_enabled = true;
			$this->slave_enabled = true;
			$this->replication_status = true;
			return;
		}
		if ($slave && !$master) {
			$this->replication_role = "SLAVE";
			$this->slave_enabled = true;
			$this->replication_status = true;
			return;
		}
		if (!$slave && $master) {
			$this->replication_role = "MASTER";
			$this->master_enabled = true;
			$this->replication_status = true;
			return;
		}
		$this->replication_role = "UNKNOWN";
	}

	private function gatherMasterStatus() {
		/*
		mysql> show master status;
		+---------------+----------+--------------+------------------+
		| File          | Position | Binlog_Do_DB | Binlog_Ignore_DB |
		+---------------+----------+--------------+------------------+
		| binlog.004227 |  6969597 |              |                  |
		+---------------+----------+--------------+------------------+
		*/
		$result = mysql_query("show master status;");
		if ( $result ) {
			$this->master_status = mysql_fetch_assoc($result);
			if(!$this->master_status) {
				return false;
			}
			return true;
		}
		return false;
	}
	private function gatherSlaveStatus() {
		/*
			mysql> show slave status\G
			*************************** 1. row ***************************
	             Slave_IO_State: Waiting for master to send event
	                Master_Host: stampy.womf.com
	                Master_User: replication
	                Master_Port: 3306
	              Connect_Retry: 60
	            Master_Log_File: binlog.004227
	        Read_Master_Log_Pos: 10104747
	             Relay_Log_File: bitey-relay-bin.000952
	              Relay_Log_Pos: 10104881
	      Relay_Master_Log_File: binlog.004227
	           Slave_IO_Running: Yes
	          Slave_SQL_Running: Yes
	            Replicate_Do_DB:
	        Replicate_Ignore_DB:
	         Replicate_Do_Table:
	     Replicate_Ignore_Table:
	    Replicate_Wild_Do_Table:
	Replicate_Wild_Ignore_Table:
	                 Last_Errno: 0
	                 Last_Error:
	               Skip_Counter: 0
	        Exec_Master_Log_Pos: 10104747
	            Relay_Log_Space: 114962616
	            Until_Condition: None
	             Until_Log_File:
	              Until_Log_Pos: 0
	         Master_SSL_Allowed: No
	         Master_SSL_CA_File:
	         Master_SSL_CA_Path:
	            Master_SSL_Cert:
	          Master_SSL_Cipher:
	             Master_SSL_Key:
	      Seconds_Behind_Master: 0
      */
      $result = mysql_query("show slave status;");
		if ( $result ) {
			$this->slave_status = mysql_fetch_assoc($result);
			if(!$this->slave_status) {
				return false;
			}
			return true;
		}
		return false;
	}
	private function setPHPGlobals() {
		error_reporting(E_ALL|E_STRICT);
		$this->time_zone = $GLOBALS['mysql_general']['tz'];
		date_default_timezone_set($this->time_zone);
	}
	private function getInputValues() {
		$this->user = $GLOBALS['mysql_general']['monitor_mysql_user'];
		$this->password = $GLOBALS['mysql_general']['monitor_mysql_password'];
	}
	private function connectMysql() {
		if (!($this->dbcon = mysql_connect($this->mysql_server,$this->user, $this->password))) {
			throw new Exception("I cannot connect to the MySQL Server with the credentials you have supplied.",1);
		}
		if (!(mysql_select_db($this->mysql_base, $this->dbcon))) {
			throw new Exception("MySQL did not like that and had this to say about it: MySQL Error number - ". mysql_errno() ." MySQL Error - ". mysql_error());
		}
	}
	private function postToZabbix() {
		foreach ( $this->data as $key => $var ) {
			foreach ($var as $subkey=>$subval) {
				if ($GLOBALS['memene']['debug_mode']) zabbixCommon::debugLog("mysql_replication: $subkey | $subval");
				$this->zabbix_post('mysql_replication',$subkey,$subval);
			}
		}
		//echo 1;
		//exit(0);
	}
 }
