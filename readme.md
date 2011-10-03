# README
The following code forms the basis for a modular PHP zabbiz trapper framework. Most of the templates are for an active agent, but they can work as passive too.

## Monitoring scripts are included for:
* DBMAIL
* Postfix via qshape
*

##INSTALL (Quick and Dirty)
* Install Files in the /etc/zabbix directory
* Open up the config file eg. vim /etc/zabbix/config/memene_config.php
* Set the location for your zabbix_agent/zabbix_agentd. The script needs access to this file so it can zabbix post.

<code>
	$GLOBALS['zabbix']['config_location'] = "/etc/zabbix/zabbix_agentd.conf";
</code>

* Enable the modules you want to use:

<code>
 	$GLOBALS['modules'][] = "mysql_general";
 	//$GLOBALS['modules'][] = "mysql_replication";
 	//$GLOBALS['modules'][] = "mysql_mmm";
	//$GLOBALS['modules'][] = "apache_status";
	//$GLOBALS['modules'][] = "nginx_status";
</code>

* Configure each enabled module. Currently the mysql_general is the only module enabled by default, so fill out your mysql user details here. You should probably create your own monitor user with the relevant permissions at this point. Replication privilges should be enough. Below is a summary of the permssions you can use fro your zabbix user. Never ever ever ever use root!!!!

<code>
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
</code>

You can set the timezone that is most relevant to you. Remember, you should have taught your mysql server to understand text timezones, see this article for more info http://dev.mysql.com/doc/refman/5.1/en/time-zone-support.html

<code>
	$GLOBALS['mysql_general']['tz'] = "America/New_York";
	$GLOBALS['mysql_general']['monitor_mysql_user'] = "";
	$GLOBALS['mysql_general']['monitor_mysql_password'] = "";
</code>

* Add the following details to the zabbix_agentd.conf (If you are using the Daemon)

<code>
	UserParameter=memene.daily,php /etc/zabbix/memene.php daily     //only use this with mysql scripts
	UserParameter=memene.live,php /etc/zabbix/memene.php live  //you usually only need this one
</code>

* You need to import the memene_live_controller.xml template - this is the template that triggers the user parameter checks defined in agent configuration.
* It is then a manual process to add the templates (in the templates directory of your zabbix monitor install) to the servers you want to monitor. 

### Note:
You need the following packages
PHP-CLI
PHP-MYSQL //mysql only
PHP-CURL //nginx apache

## INSTALL for DBMAIL Module
There are some additional instructions for the dbmail module:

* You will need to have the sudo package installed and add the following line

<code>
 zabbix ALL=NOPASSWD: /usr/bin/lsof
</code>

* Add the following line to the Memene configuration file:

<code>
$GLOBALS['modules'][] = "dbmail";
</code>

## INSTALL for Postfix Module

There are some additional instructions for the dbmail module:

* You will need to have the sudo package installed and add the following line

<code>
zabbix ALL=NOPASSWD: /usr/sbin/qshape
</code>

* Add the following line to the Memene configuration file:

<code>
$GLOBALS['modules'][] = "postfix_mailq";
</code>

## TROUBLESHOOTING
* All log files must be owned by the local Zabbix user, if you have done everything right and results arent posting this is usually where it has gone wrong.
* You may need to touch a zabbix.dat file into the Zabbix configuration directory, ownership must be zabbix. I plan to phase this out but the MySQL module still uses the file.
* If you use the dbmail and postfix modules at the same time, the sudo config should be:

 <code>
 zabbix ALL=NOPASSWD: /usr/bin/lsof, /usr/sbin/qshape 
</code>