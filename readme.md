README
This is our first attempt at a more modular zabbix monitor. These scripts have lived in stand-alone format for a month or so, but it's a real hassle to handle multiple scripts and configuration files.

There is support for a couple of modules but the mysql_general module is the only one that is ready.
The nginx module is not quite ready and you have to have configured/built nginx with the stub_status_module enabled.
The apache module is still dev so dont bother with it; its just here so you can see what is coming in the next release.

INSTALL (Quick and Dirty)
1. Install Files in the /etc/zabbix directory
2.  Open up the config file eg. vim /etc/zabbix/config/zabbix-monitor-config.php

3. Set the location for your zabbix_agent/zabbix_agentd. The script needs access to this file so it can zabbix post.
	$GLOBALS['zabbix']['config_location'] = "/etc/zabbix/zabbix_agentd.conf";

4.Enable the modules you want to use:
 	$GLOBALS['modules'][] = "mysql_general";
 	//$GLOBALS['modules'][] = "mysql_replication";
 	//$GLOBALS['modules'][] = "mysql_mmm";
	//$GLOBALS['modules'][] = "apache_status";
	//$GLOBALS['modules'][] = "nginx_status";
5. Configure each enabled module. Currently the mysql_general is the only module enabled, so fill out your mysql user details here. You should probably create your own monitor user with the relevant permissions at this point. Replication privilges should be enough. Below is a summary of the permssions you can use fro your zabbix user. Never ever ever ever use root!!!!
                 Host: localhost
                 User: zabbix_mysql_mon
             Password:
          Select_priv: N
          Insert_priv: N
          Update_priv: N
          Delete_priv: N
          Create_priv: N
            Drop_priv: N
          Reload_priv: N
        Shutdown_priv: N
         Process_priv: N
            File_priv: N
           Grant_priv: N
      References_priv: N
           Index_priv: N
           Alter_priv: N
         Show_db_priv: N
           Super_priv: N
Create_tmp_table_priv: N
     Lock_tables_priv: N
         Execute_priv: N
      Repl_slave_priv: N
     Repl_client_priv: Y
     Create_view_priv: N
       Show_view_priv: N
  Create_routine_priv: N
   Alter_routine_priv: N
     Create_user_priv: N
           Event_priv: N
         Trigger_priv: N
             ssl_type:
           ssl_cipher:
          x509_issuer:
         x509_subject:
        max_questions: 0
          max_updates: 0
      max_connections: 0
 max_user_connections: 0

create user 'zabbix_mysql_mon'@'localhost' identified by '';
GRANT REPLICATION CLIENT ON *.* TO 'zabbix_mysql_mon'@'localhost';
GRANT SELECT ON `mysql`.* TO 'zabbix_mysql_mon'@'localhost'

You can set the timezone that is most relevant to you. Remember, you should have taught your mysql server to understand text timezones, see this article for more info http://dev.mysql.com/doc/refman/5.1/en/time-zone-support.html
	$GLOBALS['mysql_general']['tz'] = "America/New_York";
	$GLOBALS['mysql_general']['monitor_mysql_user'] = "";
	$GLOBALS['mysql_general']['monitor_mysql_password'] = "";

6. Add the following details to the zabbix_agentd.conf (If you are using the Daemon)
	UserParameter=zabbix_monitor.daily,php /etc/zabbix/zabbix-monitor.php daily
	UserParameter=zabbix_monitor.live,php /etc/zabbix/zabbix-monitor.php live

7. You need to import the zabbix_monitor_controller.xml template - this is the template that triggers the user parameter checks defined in agent configuration.

8. It is then a manual process to add the templates (in the templates directory of your zabbix monitor install) to the servers you want to monitor. Suppport for the Zabbix 1.8 API to do this on your behalf is being considered for a future release.

Note:
You need the following packages
PHP-CLI
PHP-MYSQL
