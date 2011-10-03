<?php
/**
* truly common settings
*/
$GLOBALS['memene']['config_directory'] = "/etc/zabbix/";
$GLOBALS['memene']['config_location'] = $GLOBALS['memene']['config_directory']."zabbix_agentd.conf";
$GLOBALS['memene']['sender_path'] = "/usr/bin/";
$GLOBALS['memene']['sender_port'] = 10051;
$GLOBALS['memene']['debug_mode'] = false;
$GLOBALS['memene']['log_file_directory'] = "/var/log/zabbix-agent/";
$GLOBALS['memene']['log_file'] = $GLOBALS['memene']['log_file_directory']."memene_monitor.log";


/**
* definitions file for all available modules and configurations
*
*/

/**
* module definitions
*/
$GLOBALS['modules'][] = "mysql_general";
//$GLOBALS['modules'][] = "mysql_replication";
//$GLOBALS['modules'][] = "mysql_mmm";
//$GLOBALS['modules'][] = "openvz_user_beancounter";
//$GLOBALS['modules'][] = "apache_status";
//$GLOBALS['modules'][] = "nginx_status";
//$GLOBALS['modules'][] = "postfix_mailq"; 


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

?>
