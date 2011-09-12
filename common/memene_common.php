<?php

class zabbixCommon {
	protected $software_version = 1;
	protected $zabbix_config;
	protected $host;
	protected $server;
	protected $dat;
	protected $log;
	protected $utime;
	protected $dtime;

	public function close($a,$b) {
		if ( $a == 0 && $b > 1 ) return 0;
		if ( $b == 0 && $a > 1 ) return 0;
		$delta = abs($b-$a)*100/$a;
		return $delta < 90;
	}
	public function kb($a) {
		return $a*1024;
	}
	public function mb($a) {
		return $a*1024*1024;
	}
	public function gb($a) {
		return $a*1024*1024*1024;
	}
	public function byte_size($size) {
		$filesizename = array("", "K", "M", "G", "T", "P", "E", "Z", "Y");
		return $size ? round($size/pow(1024, ($i = floor(log($size, 1024)))), 0) . $filesizename[$i] : '0';
	}
	public function pct2hi($a,$Uptime) {
		return $Uptime < 86400 ? 100 : $this->percent($a);
	}
	public function percent($a) {
		return round(100*$a);
	}
	public function zabbix_config() {
		//BE WARNED preg_match will match any reference at all to hostname / server you must remove defaults or commented out values so they are not picked up
		//if ( file_exists($this->dat) ) unlink($this->dat); //@todo figure out what this is for
		//if ( file_exists($this->log) ) unlink($this->log);
		// Get server information for zabbix_sender
		$this->zabbix_config = file_get_contents($GLOBALS['memene']['config_location']);
		if (!$this->zabbix_config) {
			throw new Exception("Failed to get file contents for the zabbix configuration file. You told me the location was ". $GLOBALS['memene']['config_location']);
		}
		preg_match("/Hostname\s*=\s*(.*)/i",$this->zabbix_config,$parts);
		$this->host = $parts[1];
		preg_match("/Server\s*=\s*(.*)/i",$this->zabbix_config,$parts);
		$this->server = $parts[1];
	}
	public function returnZabbixConfiguration() {
		return $this->zabbix_config;
	}
	public function zabbix_post($system,$var,$val) {
		switch ( strtolower($val) ) {
			case "yes":
			case "on":
				$val = 1;
				break;
			case "no":
			case "":
			case "off":
				$val = 0;
				break;
		}
		if ( !is_numeric($val) ) {
			$val = '"'.$val.'"';
		}
		file_put_contents($this->dat,"$this->server $this->host ". $GLOBALS['memene']['sender_port'] ." $system.$var $val\n",FILE_APPEND);
		$cmd = $GLOBALS['memene']['sender_path'] ."zabbix_sender -z $this->server -p ". $GLOBALS['memene']['sender_port'] ." -s $this->host -k $system.$var -o $val";
		system("$cmd 2>&1 >> ".$GLOBALS['memene']['log_file']);
	}
	public function elapsed($val) {
		$now = microtime(true);
		if ( !file_exists($this->utime) ) // first time
			file_put_contents($this->utime,serialize(array( "value" => $val, "start" => $now )));
		$data = unserialize(file_get_contents($this->utime));
		file_put_contents($this->utime,serialize(array( "value" => $val, "start" => $now )));
		$seconds = $now-$data["start"];
		$elapsed = (float)($val - $data["value"])/( !$seconds || $seconds==0 ? 1 : $seconds);
		return $elapsed < 0 ? 0 : $elapsed;
	}
	public function debugLog($message="") {
        $serverdate = date("d/m/Y:h:i:s");
        $error_file = fopen($GLOBALS['memene']['log_file'], "a");
        fputs($error_file, $serverdate . ": " .$message ."\n");
        fclose($error_file);
	}
}

?>
