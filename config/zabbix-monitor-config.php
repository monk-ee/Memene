<?php
/**
* truly common settings
*/
$GLOBALS['zabbix']['config_directory'] = "/etc/zabbix/";
$GLOBALS['zabbix']['config_location'] = $GLOBALS['zabbix']['config_directory']."zabbix_agentd.conf";
$GLOBALS['zabbix']['sender_path'] = "/usr/bin/";
$GLOBALS['zabbix']['sender_port'] = 10051;
$GLOBALS['zabbix']['debug_mode'] = false;
$GLOBALS['zabbix']['log_file'] = "/tmp/zabbix-monitor.log";


/**
* definitions file for all available modules and configurations
* 
*/

/**
* module definitions
*/
$GLOBALS['modules'][] = "mysql_general";
$GLOBALS['modules'][] = "mysql_replication";
$GLOBALS['modules'][] = "mysql_mmm";
//$GLOBALS['modules'][] = "openvz_user_beancounter";    
//$GLOBALS['modules'][] = "apache_status";
//$GLOBALS['modules'][] = "nginx_status";


/**
*  mysql-commmon configuration
*/
$GLOBALS['mysql_general']['tz'] = "Australia/Sydney";
$GLOBALS['mysql_general']['monitor_mysql_user'] = "zabbix_mysql_mon";
$GLOBALS['mysql_general']['monitor_mysql_password'] = "";
  
/**
* mmm config
*/
$GLOBALS['mysql_mmm']['config_path'] = "/etc/mysql-mmm/";
$GLOBALS['mysql_mmm']['agent_config'] = $GLOBALS['mysql_mmm']['config_path']."mmm_agent.conf";
$GLOBALS['mysql_mmm']['monitor_config'] = $GLOBALS['mysql_mmm']['config_path']."mmm_mon.conf";
  
/**
* apache-status
*/
//$GLOBALS['apache_status']['host'] = "localhost";

/**
* nginx-status
*/
//$GLOBALS['nginx_status']['host'] = "http://localhost/stub_status;

/**
* user beancounters
*/
$GLOBALS['openvz_user_beancounter']['file'] = $GLOBALS['zabbix']['config_directory']."user_beancounter.txt";
?>
