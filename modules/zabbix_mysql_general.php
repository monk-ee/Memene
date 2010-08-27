<?php
/**
Changelog:
	Version 0.8:   15:28 20100406 look for not-always set variables in the globals list - and handle gracefully
    Version 0.7:   14:42 20100406 Added tcp and socket checks
    Version 0.6:  12:29 20100329 Made this into a class and organized it a little
	Version 0.5:  22:20 10/12/2009 Split off common functionality to zabbix_common.php
	Version 0.4:  Added round() to Joins_without_indexes_per_day to remove exponents for large query sets.
	Version 0.3:  11:57 AM 10/13/2008
		- Correct where Percent_innodb_cache_hit_rate and/or Percent_innodb_cache_write_waits_required could have been 0
	Version 0.2:  10:43 AM 10/13/2008
		- Corrected error where replication not enabled stopped the monitoring completely (If you had your warning on strict, the server could see things like:  Notice: Undefined variable: Slave_IO_Running)
		- Ditto for Last_Errno, Last_Error
 */



 class mysql_general extends zabbixCommon {
  	private $time_zone;
  	private $type;
  	private $user;
  	private $password;
  	private $slowInnodb = 0;   // If you set this to 1, then the script is very careful about status variables to avoid http://bugs.mysql.com/bug.php?id=36600
  	private $physical_memory;
  	private $swap_memory;
  	private $bit64;
  	private $valid_local_alias = array();
  	private $dbcon;
  	private $mysql_server = "localhost";
  	private $mysql_base = "mysql"; //i know this isnt likely to change anytime soon but i couldnt help myself
  	private $mysql_version;
  	private $engines = array('have_myisam'=>'YES','have_memory'=>'YES');		// these are auto enabled.  no config necessary
  	private $mysql_globals = array();
  	private $user_data = array();
  	private $available = 1; //hmmm this seems self evident
  	private $dangerous_privileges;
  	private $data;
  	private $install_type;
  	private $statusVariables;
  	private $fragmentation_data_free_threshold = 10000;
  	//network states
	private $wait_connections;
	private $active_connections;
	private $listening_connections;
	//socket states
	private $connected_sockets;
	private $listening_sockets;
  		
  	public function __construct() {
  		if ($GLOBALS['zabbix']['debug_mode']) zabbixCommon::debugLog(get_class($this));
  		$this->setPHPGlobals();
  		$this->zabbix_config();
  		$this->getInputValues();
  		$this->setupVariables(); //@todo make this a little more sensible
  		$this->gatherLocalAliases();
  		$this->connectMysql();
  		$this->getMysqlVersion();
  		$this->mysqlEngines();
  		if ($this->slowInnodb) {
  			$this->slowInnodbGlobals();
		} else {
			$this->mysqlGlobals();
		}
		switch ($this->type) {
			case "daily":
				$this->writeDailyFile();
				$this->analyseUsers();
				$this->fragmentedTables();
				$this->enginesInUse();
				$this->dailyData();
				$this->handleMissingGlobalsGracefully();
				$this->postToZabbix();
				break;
			case "live":
				$this->checkServerRuntime();
				$this->liveData();
				$this->handleMissingGlobalsGracefully();
				$this->postToZabbix();
				break;
			default:
				throw new Exception("I should have had a type and broken before this point.");
		}
	}
	private function setPHPGlobals() {
		error_reporting(E_ALL|E_STRICT);
		$this->time_zone = $GLOBALS['mysql_general']['tz'];
		date_default_timezone_set($this->time_zone);	
	}
	private function getInputValues() {
		global $argv;
		if (isset($argv[1])) {
			$this->type = $argv[1];
		} else {
			throw new Exception("I require the type of check you need to run.",1);
		}
		$this->user = $GLOBALS['mysql_general']['monitor_mysql_user'];
		$this->password = $GLOBALS['mysql_general']['monitor_mysql_password'];
	}
	private function setupVariables() {
		$this->physical_memory = (int)`free -b | grep Mem | awk '{print \$2}'`;
		$this->swap_memory = (int)`free -b | grep Swap | awk '{print \$2}'`;	
		//network states
		$this->wait_connections = (int)`netstat -nal | grep ":3306" | grep -c "TIME_WAIT"`;
		$this->active_connections = (int)`netstat -nal | grep ":3306" | grep -c "ESTABLISHED"`;
		$this->listening_connections = (int)`netstat -nal | grep ":3306" | grep -c "LISTEN"`;
		//socket states
		$this->connected_sockets = (int)`netstat -nal | grep "mysql.sock" | grep -c "CONNECTED"`;
		$this->listening_sockets = (int)`netstat -nal | grep "mysql.sock" | grep -c "LISTENING"`;
		
		// Is this a 64bit machine?
		$this->bit64 = ereg("/64/",`uname -m`);
			// These are dangerous privileges
		$this->dangerous_privileges = array(
											"Insert_priv"=>"Insert_priv_count",
											"Update_priv"=>"Update_priv_count",
											"Delete_priv"=>"Delete_priv_count",
											"Drop_priv"=>"Drop_priv_count",
											"Shutdown_priv"=>"Shut_down_priv_count",
											"Process_priv"=>"Process_priv_count",
											"File_priv"=>"File_priv_count",
											"Grant_priv"=>"Grant_priv_count",
											"Alter_priv"=>"Alter_priv_count",
											"Super_priv"=>"Super_priv_count",
											"Lock_tables_priv"=>"Lock_tables_priv_count",
											);
		$this->statusVariables = array(
							"Aborted_clients", 
							"Aborted_connects", 
							"Binlog_cache_disk_use", 
							"Binlog_cache_use", 
							"Bytes_received", 
							"Bytes_sent", 
							"Com_alter_db", 
							"Com_alter_table", 
							"Com_create_db", 
							"Com_create_function", 
							"Com_create_index", 
							"Com_create_table", 
							"Com_delete", 
							"Com_drop_db", 
							"Com_drop_function", 
							"Com_drop_index", 
							"Com_drop_table", 
							"Com_drop_user", 
							"Com_grant", 
							"Com_insert", 
							"Com_replace", 
							"Com_revoke", 
							"Com_revoke_all", 
							"Com_select", 
							"Com_update", 
							"Connections", 
							"Created_tmp_disk_tables", 
							"Created_tmp_tables", 
							"Handler_read_first", 
							"Handler_read_key", 
							"Handler_read_next", 
							"Handler_read_prev", 
							"Handler_read_rnd", 
							"Handler_read_rnd_next", 
							"Innodb_buffer_pool_read_requests", 
							"Innodb_buffer_pool_reads", 
							"Innodb_buffer_pool_wait_free", 
							"Innodb_buffer_pool_write_requests", 
							"Innodb_log_waits", 
							"Innodb_log_writes", 
							"Key_blocks_unused", 
							"Key_read_requests", 
							"Key_reads", 
							"Key_write_requests", 
							"Key_writes", 
							"Max_used_connections", 
							"Open_files", 
							"Open_tables", 
							"Opened_tables", 
							"Qcache_free_blocks", 
							"Qcache_free_memory", 
							"Qcache_hits", 
							"Qcache_inserts", 
							"Qcache_lowmem_prunes", 
							"Qcache_not_cached", 
							"Qcache_queries_in_cache", 
							"Qcache_total_blocks", 
							"Questions", 
							"Select_full_join", 
							"Select_range", 
							"Select_range_check", 
							"Select_scan", 
							"Slave_running", 
							"Slow_launch_threads", 
							"Slow_queries", 
							"Sort_merge_passes", 
							"Sort_range", 
							"Sort_rows", 
							"Sort_scan", 
							"Table_locks_immediate", 
							"Table_locks_waited", 
							"Threads_cached", 
							"Threads_connected", 
							"Threads_created", 
							"Threads_running", 
							"Uptime"
							);
	}
	private function handleMissingGlobalsGracefully() {
		// a bit dumb but push data into temp array
		$temp = array();
		foreach($this->data as $value) {
			foreach ($value as $subkey=>$subval) {
				$temp[$subkey] = $subval;
			}
		}
		//if bin log is not set - there is no data! - bin_log
		//sync to disk as well - sync_binlog
		if (!array_key_exists("log_bin",$temp)) {
			$this->data[] = array("log_bin"=>0);	
			$this->data[] = array("sync_binlog"=>0);	
		}
		//innodb_flush_log_at_trx_commit
		if (!array_key_exists("innodb_flush_log_at_trx_commit",$temp)) {
			$this->data[] = array("innodb_flush_log_at_trx_commit"=>0);	
		}
		//local_infile
		if (!array_key_exists("local_infile",$temp)) {
			$this->data[] = array("local_infile"=>0);	
		}
		//expire_logs_days
		if (!array_key_exists("expire_logs_days",$temp)) {
			$this->data[] = array("expire_logs_days"=>0);	
		}
		//log_slow_queries
		if (!array_key_exists("log_slow_queries",$temp)) {
			$this->data[] = array("log_slow_queries"=>0);	
		}
		//long_query_time
		if (!array_key_exists("long_query_time",$temp)) {
			$this->data[] = array("long_query_time"=>0);	
		}
		//myisam_recover_options
		if (!array_key_exists("myisam_recover_options",$temp)) {
			$this->data[] = array("myisam_recover_options"=>0);	
		}
		//sql_mode
		if (!array_key_exists("sql_mode",$temp)) {
			$this->data[] = array("sql_mode"=>0);	
		}
		//have_query_cache
		if (!array_key_exists("have_query_cache",$temp)) {
			$this->data[] = array("have_query_cache"=>0);	
		}
		//have_symlink
		if (!array_key_exists("have_symlink",$temp)) {
			$this->data[] = array("have_symlink"=>0);	
		}
		//skip_show_database
		if (!array_key_exists("skip_show_database",$temp)) {
			$this->data[] = array("skip_show_database"=>1);	
		}
		//old_passwords
		if (!array_key_exists("old_passwords",$temp)) {
			$this->data[] = array("old_passwords"=>0);	
		}
	}
	private function gatherLocalAliases() {
		// Gather localhost aliases
		$this->valid_local_alias = array("localhost","127.0.0.1", "%");
		$hosts = `grep 127.0.0.1 /etc/hosts`;
		$lines = explode("\n",$hosts);
		foreach ( $lines as $line ){
			$parts = preg_split("/[ \t,]+/",$line);
			for ( $i=1; $i<count($parts); $i++ ) {
				if ( $parts[$i] > "" ) {
					$this->valid_local_alias[] = $parts[$i];
				}
			}
		}
	}
	private function connectMysql() {
		if (!($this->dbcon = mysql_connect($this->mysql_server,$this->user, $this->password))) {
			throw new Exception("I cannot connect to the MySQL Server with the credentials you have supplied.",1);
		}
		if (!(mysql_select_db($this->mysql_base, $this->dbcon))) {
			throw new Exception("MySQL did not like that and had this to say about it: MySQL Error number - ". mysql_errno() ." MySQL Error - ". mysql_error());
		}
	}
	private function getMysqlVersion() {
		$parts = explode(" ",`mysql --version`);
		$this->mysql_version = substr($parts[5],0,strlen($parts[4])-1);	
		
	}
	private function mysqlEngines() {
		$result = mysql_query("show global variables;");
		if ( $result ) {
			while ($row = mysql_fetch_assoc($result)) {
				$var = $row["Variable_name"];
				$val = $row["Value"];
				$$var = $val;
				if ( substr($var,0,5) == "have_" && $val == "YES" ) {
					$this->engines[$var] = $val;
				}
			}	
		}
	}
	private function slowInnodbGlobals() {
		foreach ( $this->statusVariables as $var ) {
			$result = mysql_query("show global status like '$var';");
			if ( $result )
				while ($row = mysql_fetch_assoc($result)) {
					$var = $row["Variable_name"];
					$val = $row["Value"];
					$this->mysql_globals[$var] = $val;
				}
		}
	}
	private function mysqlGlobals() {
		$result = mysql_query("show global status;");
		if ( $result ) {
			while ($row = mysql_fetch_assoc($result)) {
				$var = $row["Variable_name"];
				$val = $row["Value"];
				$this->mysql_globals[$var] = $val;
			}
		}
	}
	private function writeDailyFile() {
		if ( file_exists($this->dtime) ) {
			$Diff = (time() - filectime($this->dtime))/60/60/24;
			if ( $Diff < 1 ) {
				echo "Daily File Exit Condition";
				exit(0);
			}
			unlink($this->dtime);
		}
		file_put_contents($this->dtime,"Ran at ".date("Y-m-d H:i")."\n");	
	}
	private function analyseUsers() {
		// Now, load users and let's see what's there
		$result = mysql_query("select * from mysql.user");
		//set all counters to zero without being a smartass about it
		$this->user_data['accounts_root'] = 0;
		$this->user_data['accounts_without_password'] = 0;
		$this->user_data['accounts_with_broad_host_specifier'] = 0;
		$this->user_data['accounts_anonymous'] = 0;
		while ($row = mysql_fetch_assoc($result)) {
			//broad host check
			if ( $row['Host'] == "" || $row['Host']=='%' ) {
				$this->user_data['accounts_with_broad_host_specifier']++;
			}
			//root accounts	
			if ( $row["User"] == "root" ){
				$this->user_data['accounts_root']++;
				$invalid = false;
				if ( $row["Host"] == "" || $row["Host"]=="%" || !in_array($row["Host"],$this->valid_local_alias) ) {
					$this->user_data['root_remote_access'] = 1;
				} else {
						$this->user_data['root_remote_access'] = 0;
				}
				if ( $row["Password"] == "" ) {
					$this->user_data['root_no_password']  = 1;
				} else {
					$this->user_data['root_no_password']  = 0;
				}
			}
			//no password accounts
			if ( $row['Password'] == "" ) {
				$this->user_data['accounts_without_password']++;
			}
			//anonymous aliens
			if ( $row['User'] == "" ) {
				$this->user_data['accounts_anonymous']++;
			}
			//dangerous privileges
			foreach ( $this->dangerous_privileges as $key => $var ) {
				if ( $row[$key] == "Y" ) {
					if (isset($this->user_data[$var])) {
						$this->user_data[$var]++;
					} else {
						$this->user_data[$var] = 0;
					}
				}
			}
		}	
	}
	private function fragmentedTables() {
		// How many fragmented tables to we have?
		$result = mysql_query("SELECT 
									COUNT(TABLE_NAME) as Frag 
		                       FROM 
		                       		information_schema.TABLES 
		                       WHERE 
		                       		TABLE_SCHEMA NOT IN ('information_schema','mysql') 
		                       		AND Data_free > ".$this->fragmentation_data_free_threshold);
		//@todo this is stupid there is no real need for a while loop here
		while ($row = mysql_fetch_assoc($result)) {
			$this->data[] = array('Fragmented_table_count' => $row["Frag"]);
		}
	}
	private function enginesInUse() {
		// Get the engines in use
		$result = mysql_query("SELECT DISTINCT ENGINE FROM information_schema.TABLES");
		if ( $result ) {
			while ($row = mysql_fetch_assoc($result)) {
				foreach ( $row as $key => $var ) {
					$key = "have_".strtolower($var);
					if ( array_key_exists( $key,$this->engines ) )
						$this->engines[$key] = "USED";
				}
			}
			//install type @todo not sure whether we need this or additional states
			if ( $this->engines['have_myisam'] == "YES" && $this->engines['have_innodb'] == "USED" ) {
				$this->install_type = "innodb";
			} elseif ( $this->engines['have_myisam'] == "USED" && $this->engines['have_innodb'] == "YES" ) {
				$this->install_type = "myisam";
			} elseif ( $this->engines['have_myisam'] == "USED" && $this->engines['have_innodb'] == "YES" && $this->engines['have_myisam'] == "YES" && $this->engines['have_innodb'] == "USED") {
				$this->install_type = "mixed";	
			} else {
				$this->install_type = "unknown";
			}
		} else {
			throw new Exception("I could not determine any information about the engines that are currently in use on this server.");
		}
	}
	private function dailyData() {
		$this->data[] = array('Architecture_handles_all_memory' => ($this->physical_memory <= 2147483648 || $this->bit64 ? 1 : 0));
		if ($this->bit64) {
			$this->data[] = array('architecture' => '64bit');
		} else {
			$this->data[] = array('architecture' => '32bit');
		}
		$this->data[] = array('physical_memory' => $this->physical_memory);
		$this->data[] = array('mysql_version' => $this->mysql_version);
		$this->data[] = array('mysql_server' => $this->mysql_server);
		$this->data[] = array('install_type' => $this->install_type);
		//network layer stuff
		$this->data[] = array('wait_connections' => $this->wait_connections);
		$this->data[] = array('active_connections' => $this->active_connections);
		$this->data[] = array('listening_connections' => $this->listening_connections);
		//socket layer stuff
		$this->data[] = array('connected_sockets' => $this->connected_sockets);
		$this->data[] = array('listening_sockets' => $this->listening_sockets);
		//mysql globals
		foreach ($this->mysql_globals as $key=>$value) {
			$this->data[] = array($key => $value);
		}
		//user data
		foreach ($this->user_data as $key=>$value) {
			$this->data[] = array($key => $value);
		}
		//some basic computations
		$this->data[] = array('Excessive_revokes' => $this->mysql_globals['Com_revoke'] + $this->mysql_globals['Com_revoke_all']);
		$this->data[] = array('Percent_writes_vs_total' => $this->percent( ($this->mysql_globals['Com_insert'] + $this->mysql_globals['Com_replace'] + $this->mysql_globals['Com_update'] + $this->mysql_globals['Com_delete']) / $this->mysql_globals['Questions'] ));
		$this->data[] = array('Percent_inserts_vs_total' => $this->percent( ($this->mysql_globals['Com_insert'] + $this->mysql_globals['Com_replace']) / $this->mysql_globals['Questions'] ));
		$this->data[] = array('Percent_selects_vs_total' => $this->percent( ($this->mysql_globals['Com_select'] + $this->mysql_globals['Qcache_hits']) / $this->mysql_globals['Questions'] ));
		$this->data[] = array('Percent_deletes_vs_total' => $this->percent( $this->mysql_globals['Com_delete'] / $this->mysql_globals['Questions'] ));
		$this->data[] = array('Percent_updates_vs_total' => $this->percent( $this->mysql_globals['Com_update'] / $this->mysql_globals['Questions'] ));
		$this->data[] = array('Recent_schema_changes' => $this->mysql_globals['Com_create_db'] > 0 || $this->mysql_globals['Com_alter_db'] > 0 || $this->mysql_globals['Com_drop_db'] > 0 || $this->mysql_globals['Com_create_function'] > 0 || $this->mysql_globals['Com_drop_function'] > 0 || $this->mysql_globals['Com_create_index'] > 0 || $this->mysql_globals['Com_drop_index'] > 0 || $this->mysql_globals['Com_alter_table'] > 0 || $this->mysql_globals['Com_create_table'] > 0 || $this->mysql_globals['Com_drop_table'] > 0 || $this->mysql_globals['Com_drop_user'] > 0 );
		//var_dump($this->data);
 	}
 	private function checkServerRuntime() {
 		if ((int)$this->mysql_globals['Uptime'] < 3600) {
 			echo 1;
			exit(0);
		}
	}
	private function prettyUptimeString() {
		// Make a pretty uptime string
		$seconds = $this->mysql_globals['Uptime'] % 60;
		$minutes = floor(($this->mysql_globals['Uptime'] % 3600) / 60);
		$hours = floor(($this->mysql_globals['Uptime'] % 86400) / (3600));
		$days = floor($this->mysql_globals['Uptime'] / (86400));
		if ($days > 0) {
			$Uptimestring = "${days}d ${hours}h ${minutes}m ${seconds}s";
		} elseif ($hours > 0) {
			$Uptimestring = "${hours}h ${minutes}m ${seconds}s";
		} elseif ($minutes > 0) {
			$Uptimestring = "${minutes}m ${seconds}s";
		} else {
			$Uptimestring = "${seconds}s";
		}
		$this->data[] = array('Uptimestring' => $Uptimestring);	
		return $days;
	}
	private function liveData() {
		//uptime
		$days = $this->prettyUptimeString();
		if ( $days == 0 ) $days = 100000000;		// force percentage to be low on calculation
		//innodb cache hit rate
		if ( !isset( $this->mysql_globals['Innodb_buffer_pool_read_requests']) || $this->mysql_globals['Innodb_buffer_pool_read_requests']== 0 ) {
			$this->data[] = array('Percent_innodb_cache_hit_rate' => 0 );
		} else {
			$this->data[] = array('Percent_innodb_cache_hit_rate' => $this->pct2hi( 1 - ( $this->mysql_globals['Innodb_buffer_pool_reads'] / $this->mysql_globals['Innodb_buffer_pool_read_requests']),$this->mysql_globals['Uptime']));
		}
		//innodb cache write waits
		if ( !isset($this->mysql_globals['Innodb_buffer_pool_write_requests']) || $this->mysql_globals['Innodb_buffer_pool_write_requests'] == 0 ) {
				$this->data[] = array('Percent_innodb_cache_write_waits_required' => 0);
		} else {
			$this->data[] = array('Percent_innodb_cache_write_waits_required' => $this->percent( $this->mysql_globals['Innodb_buffer_pool_wait_free'] / $this->mysql_globals['Innodb_buffer_pool_write_requests'] ));
		}
		//the usual suspects
		foreach ($this->mysql_globals as $key=>$value) {
			$this->data[] = array($key => $value);
		}
		//network layer stuff
		$this->data[] = array('wait_connections' => $this->wait_connections);
		$this->data[] = array('active_connections' => $this->active_connections);
		$this->data[] = array('listening_connections' => $this->listening_connections);
		//socket layer stuff
		$this->data[] = array('connected_sockets' => $this->connected_sockets);
		$this->data[] = array('listening_sockets' => $this->listening_sockets);
		//some more simple calcs
		$this->data[] = array('Last_Errno' => isset($this->mysql_globals['Last_Errno']) ? $this->mysql_globals['Last_Errno'] : 0);
		$this->data[] = array('Last_Error' => isset($this->mysql_globals['Last_Error']) ? $this->mysql_globals['Last_Error'] : "");
		$this->data[] = array('Queries_per_sec' => $this->elapsed((float)$this->mysql_globals['Questions']));
		$this->data[] = array('Qcache_lowmem_prunes_per_day' => $this->mysql_globals['Qcache_lowmem_prunes']/$days);
		$this->data[] = array('Average_rows_per_query' => ($this->mysql_globals['Handler_read_first'] + $this->mysql_globals['Handler_read_key'] + $this->mysql_globals['Handler_read_next'] + $this->mysql_globals['Handler_read_prev'] + $this->mysql_globals['Handler_read_rnd'] + $this->mysql_globals['Handler_read_rnd_next'] + $this->mysql_globals['Sort_rows'])/$this->mysql_globals['Questions']);
		$this->data[] = array('Total_rows_returned' => $this->mysql_globals['Handler_read_first'] + $this->mysql_globals['Handler_read_key'] + $this->mysql_globals['Handler_read_next'] + $this->mysql_globals['Handler_read_prev'] + $this->mysql_globals['Handler_read_rnd'] + $this->mysql_globals['Handler_read_rnd_next'] + $this->mysql_globals['Sort_rows']);
		$this->data[] = array('Indexed_rows_returned' => $this->mysql_globals['Handler_read_first'] + $this->mysql_globals['Handler_read_key'] + $this->mysql_globals['Handler_read_next'] + $this->mysql_globals['Handler_read_prev']);
		$this->data[] = array('Total_sort' => $this->mysql_globals['Sort_range']+$this->mysql_globals['Sort_scan']);
		$this->data[] = array('Joins_without_indexes' => $this->mysql_globals['Select_range_check'] + $this->mysql_globals['Select_full_join']);
		$this->data[] = array('Joins_without_indexes_per_day' => round(($this->mysql_globals['Select_range_check'] + $this->mysql_globals['Select_full_join'])/$days,0));
		$this->data[] = array('Percent_full_table_scans' => $this->percent( ($this->mysql_globals['Handler_read_rnd_next'] + $this->mysql_globals['Handler_read_rnd']) / ($this->mysql_globals['Handler_read_rnd_next'] + $this->mysql_globals['Handler_read_rnd'] + $this->mysql_globals['Handler_read_first'] + $this->mysql_globals['Handler_read_next'] + $this->mysql_globals['Handler_read_key'] + $this->mysql_globals['Handler_read_prev']) ));
		if ($this->mysql_globals['Qcache_total_blocks'] != 0 ) {
			$this->data[] = array('Percent_query_cache_fragmentation' => $this->percent( $this->mysql_globals['Qcache_free_blocks'] / $this->mysql_globals['Qcache_total_blocks'] ));
		} else {
			$this->data[] = array('Percent_query_cache_fragmentation' => 0 );
		}
		//need to wrap error handler - it gets a divide by zero error on new servers {
		if (($this->mysql_globals['Qcache_inserts'] + $this->mysql_globals['Qcache_hits']) != 0) {
			$this->data[] = array('Percent_query_cache_hit_rate' => $this->percent( $this->mysql_globals['Qcache_hits'] / ($this->mysql_globals['Qcache_inserts'] + $this->mysql_globals['Qcache_hits']) ));
		} else {
			$this->data[] = array('Percent_query_cache_hit_rate' => 0);
		}
		//same here
		if ($this->mysql_globals['Qcache_inserts'] != 0) {
			$this->data[] = array('Percent_query_cache_pruned_from_inserts' => $this->percent( $this->mysql_globals['Qcache_lowmem_prunes'] / $this->mysql_globals['Qcache_inserts'] ));
		} else {
			$this->data[] = array('Percent_query_cache_pruned_from_inserts' => 0);
		}
		
		//hmmm for some reason this was not set in my version - key_buffer_size
		if (isset($this->mysql_globals['key_buffer_size'])) {
			$this->data[] = array('Percent_myisam_key_cache_in_use' => $this->percent( (1 - ($this->mysql_globals['Key_blocks_unused'] / ($this->mysql_globals['key_buffer_size'] / $this->mysql_globals['key_cache_block_size']))) ));
			$this->data[] = array('Percent_myisam_key_cache_hit_rate' => 0); /*pct2hi( (1 - ($Key_reads / ($Key_read_requests))) ),*/

			$this->data[] = array('Number_myisam_key_blocks' => $this->mysql_globals['key_buffer_size'] / $this->mysql_globals['Key_cache_block_size']);
			$this->data[] = array('Used_myisam_key_cache_blocks' => ($this->mysql_globals['key_buffer_size'] / $this->mysql_globals['Key_cache_block_size']) - $this->mysql_globals['Key_blocks_unused']);
		} else {
			$this->data[] = array('Percent_myisam_key_cache_in_use' => 0);
			$this->data[] = array('Number_myisam_key_blocks' => 0);
			$this->data[] = array('Percent_myisam_key_cache_hit_rate' => 0); /*pct2hi( (1 - ($Key_reads / ($Key_read_requests))) ),*/
			$this->data[] = array('Used_myisam_key_cache_blocks' => 0);
		}
		$this->data[] = array('Percent_table_cache_hit_rate' => $this->mysql_globals['Opened_tables'] > 0 ? $this->pct2hi($this->mysql_globals['Open_tables']/$this->mysql_globals['Opened_tables'], $this->mysql_globals['Uptime']) : 100);
		$this->data[] = array('Percent_table_lock_contention' => ($this->mysql_globals['Table_locks_waited'] + $this->mysql_globals['Table_locks_immediate']) > 0 ? $this->percent( $this->mysql_globals['Table_locks_waited'] / ($this->mysql_globals['Table_locks_waited'] + $this->mysql_globals['Table_locks_immediate'])) : 0);
		$this->data[] = array('Percent_tmp_tables_on_disk' => ($this->mysql_globals['Created_tmp_disk_tables'] + $this->mysql_globals['Created_tmp_tables']) > 0 ? $this->percent( $this->mysql_globals['Created_tmp_disk_tables'] / ($this->mysql_globals['Created_tmp_disk_tables'] +  $this->mysql_globals['Created_tmp_tables'])) : 0);
		$this->data[] = array('Percent_transactions_saved_tmp_file' => ($this->mysql_globals['Binlog_cache_use'] == 0 ? 0 : $this->percent( $this->mysql_globals['Binlog_cache_disk_use'] / $this->mysql_globals['Binlog_cache_use']) ));
		$this->data[] = array('Percent_tmp_sort_tables' => ($this->mysql_globals['Sort_range'] + $this->mysql_globals['Sort_scan'] > 0 ? $this->percent( $this->mysql_globals['Sort_merge_passes']/($this->mysql_globals['Sort_range'] + $this->mysql_globals['Sort_scan'])) : 0 ));
		//hmmm for some reason this was not set in my version - open_files_limit
		if (isset($this->mysql_globals['open_files_limit'])) {
			$this->data[] = array('Percent_files_open' => $this->mysql_globals['open_files_limit'] > 0 ? $this->percent( $this->mysql_globals['Open_files']/$this->mysql_globals['open_files_limit']) : 0);
		} else {
			$this->data[] = array('Percent_files_open' => 0);
		}
		$this->data[] = array('Successful_connects' => $this->mysql_globals['Connections'] - $this->mysql_globals['Aborted_connects']);
		$this->data[] = array('Percent_thread_cache_hit_rate' => $this->pct2hi( (1-$this->mysql_globals['Threads_created']/$this->mysql_globals['Connections']),$this->mysql_globals['Uptime']) );
		//hmmm for some reason this was not set in my version - max_connections
		if (isset($this->mysql_globals['max_connections'])) {
			$this->data[] = array('Percent_connections_used' => $this->percent( $this->mysql_globals['Threads_connected'] / $this->mysql_globals['max_connections'] ));
			$this->data[] = array('Percent_maximum_connections_ever' => $this->percent( $this->mysql_globals['Max_used_connections'] / $this->mysql_globals['max_connections'] ));
		} else {
			$this->data[] = array('Percent_connections_used' => 0);
			$this->data[] = array('Percent_maximum_connections_ever' => 0);
		}
		$this->data[] = array('Percent_aborted_connections' => $this->percent( $this->mysql_globals['Aborted_connects'] / $this->mysql_globals['Connections'] ));
		//hmmm for some reason this was not set in my version - innodb_log_files_in_group
		if (isset($this->mysql_globals['innodb_log_files_in_group'])) {
			$this->data[] = array('Percent_innodb_log_size_vs_buffer_pool' => $this->percent( ($this->mysql_globals['innodb_log_files_in_group'] * $this->mysql_globals['innodb_log_file_size']) / $this->mysql_globals['innodb_buffer_pool_size'] ));
			$this->data[] = array('Innodb_log_file_size_total' => $this->mysql_globals['innodb_log_files_in_group'] * $this->mysql_globals['innodb_log_file_size']);
		} else {
			$this->data[] = array('Percent_innodb_log_size_vs_buffer_pool' => 0);
			$this->data[] = array('Innodb_log_file_size_total' => 0);
		}
		$this->data[] = array('Percent_innodb_log_write_waits_required' => $this->percent( $this->mysql_globals['Innodb_log_waits'] / $this->mysql_globals['Innodb_log_writes'] ));
		
		$this->data[] = array('Slave_running' => $this->mysql_globals['Slave_IO_Running']="Yes" && $this->mysql_globals['Slave_SQL_Running']="Yes" ? 1 : 0);
	}
	private function postToZabbix() {
		foreach ( $this->data as $key => $var ) {
			foreach ($var as $subkey=>$subval) {
				if ($GLOBALS['zabbix']['debug_mode']) zabbixCommon::debugLog("mysql_general: $subkey | $subval");
				$this->zabbix_post('mysql_general',$subkey,$subval);
			}			
		}
		//echo 1;
		//exit(0);	
	}	
 }




