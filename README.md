# GLPI integration with Nagios

### Version
- Nagios 3
- GLPI 0.84.8

## Instructions

1- Modify your Nagios commands.cfg (/etc/nagios3/commands.cfg) to include the following commands:

'manage-host-tickets' command definition
```sh
define command{
		command_name manage-host-tickets
        command_line php /usr/share/nagios3/plugins/eventhandlers/manage-host-tickets.php event="$HOSTSTATE$" state="$HOSTSTATETYPE$" eventhost="$HOSTNAME$" hostattempts="$HOSTATTEMPT$" maxhostattempts="$MAXHOSTATTEMPTS$" hostproblemid="$HOSTPROBLEMID$" lasthostproblemid="$LASTHOSTPROBLEMID$"
}
```	 

'manage-service-tickets' command definition
```sh
define command{
		command_name manage-service-tickets
		command_line php /usr/share/nagios3/plugins/eventhandlers/manage-service-tickets.php event="$SERVICESTATE$" state="$SERVICESTATETYPE$" hoststate="$HOSTSTATE$" eventhost="$HOSTNAME$" service="$SERVICEDISPLAYNAME$" serviceattempts="$SERVICEATTEMPT$" maxserviceattempts="$MAXSERVICEATTEMPTS$" servicestate="$SERVICESTATE$" lastservicestate="$LASTSERVICESTATE$" servicecheckcommand="$SERVICECHECKCOMMAND$" serviceoutput="$SERVICEOUTPUT$" longserviceoutput="$LONGSERVICEOUTPUT$"
}
```

2- Modify your generic-host_nagios2.cfg (/etc/nagios3/conf.d):
```sh
define host{
        name                            generic-host    ; The name of this host template
        notifications_enabled           1       	; Host notifications are enabled
        event_handler_enabled           1       	; Host event handler is enabled
        flap_detection_enabled          1       	; Flap detection is enabled
        failure_prediction_enabled      1       	; Failure prediction is enabled
        process_perf_data               1       	; Process performance data
        retain_status_information       1       	; Retain status information across program restarts
        retain_nonstatus_information    1       	; Retain non-status information across program restarts
		check_command                   check-host-alive
		event_handler		            manage-host-tickets
		max_check_attempts      		1
		notification_interval   		0
		notification_period     		24x7
		notification_options    		d,u,r
		contact_groups          		admins
        register                        0       	; DONT REGISTER THIS DEFINITION - ITS NOT A REAL HOST, JUST A TEMPLATE!
}
```

3- Modify the generic-service-nagios2.cfg (/etc/nagios3/conf.d):
```sh
define service{
        name                            generic-service ; The 'name' of this service template
        active_checks_enabled           1       ; Active service checks are enabled
        passive_checks_enabled          1       ; Passive service checks are enabled/accepted
        parallelize_check               1       ; Active service checks should be parallelized (disabling this can lead to major performance problems)
        obsess_over_service             1       ; We should obsess over this service (if necessary)
        check_freshness                 0       ; Default is to NOT check service 'freshness'
        notifications_enabled           1       ; Service notifications are enabled
        event_handler_enabled           1       ; Service event handler is enabled
        flap_detection_enabled          1       ; Flap detection is enabled
        failure_prediction_enabled      1       ; Failure prediction is enabled
        process_perf_data               1       ; Process performance data
        retain_status_information       1       ; Retain status information across program restarts
        retain_nonstatus_information    1       ; Retain non-status information across program restarts
		notification_interval           0		; Only send notifications on status change by default.
		event_handler		            manage-service-tickets
		is_volatile                     0
		check_period                    24x7
		normal_check_interval           5
		retry_check_interval            1
		max_check_attempts              4
		notification_period             24x7
		notification_options            w,u,c,r
		contact_groups                  admins
        register                        0       ; DONT REGISTER THIS DEFINITION - ITS NOT A REAL SERVICE, JUST A TEMPLATE!
    }
```

4- Modify the event handler files to include a GLPI username, password and GPLI server IP. Then move the files to your (/usr/share/nagios/event_handlers/) folder.

	- manage-host-tickets.php
	
	- manage-service-tickets.php

5º Now restart Nagios Services to apply to the new changes:

	#/etc/init.d/nagios3 reload
	

6º Downloading the plug -in webservice of GLPI:

	# cd /tmp
	
	# wgethttps://forge.indepnet.net/attachments/download/1907/glpi-webservices-1.4.3.tar.gz
	
	# tar -xvzfglpi-webservices-1.4.3.tar.gz

7º Move the folder to the directory:	

	# mv webservices/ /var/www/glpi/plugins/

8º Make the installation of support xmlrpcpara PHP5 using the following command:

	# apt-get install php5-xmlrpc	

9º Installing and activating the plugin via the web interface GLPI:

	- Configurar > Plugins;
	
	- Instalar;
	
	- Habilitar.

10º Okay, now it's just simulate stop some host / service, a new ticket must be
open. By restoring the host / service the ticket is automatically closed.
