<?php
// ----------------------------------------------------------------------------------------
// Script Name:			manage-service-tickets.php
// Script Location:		/usr/share/nagios3/plugins/eventhandlers/
// Description:			Creates or closes glpi tickets according to service UP/Down states.
// Dependancies:		GLPI Webservices plugin.
//
// Required CommandLine Variables:
//		eventhost=$HOSTNAME$
//		event=$SERVICESTATE$
//		state=$SERVICESTATETYPE$
//		service=$SERVICEDISPLAYNAME$
//		serviceattempts=$SERVICEATTEMPTS$
//		maxserviceattempts=$MAXSERVICEATTEMPTS$
//		serviceproblemid=$SERVICEPROBLEMID$
//		lastserviceproblemid=$LASTSERVICEPROBLEMID$

// -----------------------------------------------------------------------------------------
// Configurables:
// -----------------------------------------------------------------------------------------
$user =     	"glpi";							//GLPI User Account - REQUIRED
$password = 	"glpi";						//GLPI User Password - REQUIRED   
$xmlhost =  	"localhost/";						//GLPI Server HOSTNAME/IP - REQUIRED
$xmlurl =   	"plugins/webservices/xmlrpc.php";	//Path to xmlrpc on GLPI server - REQUIRED


// -----------------------------------------------------------------------------------------
// Do Not Edit Below!
// -----------------------------------------------------------------------------------------

$arg['method'] = "glpi.test";
$arg['url'] = $xmlurl;
$arg['host'] = $xmlhost;
$response = call_glpi($arg);
unset($arg);
$webservices_version = $response['webservices'];
	//0.2.0 Method added
	//1.2.0 Added type, source, requester and observer options

$eventval=array();
	if ($argv>1) {
	   for ($i=1 ; $i<count($argv) ; $i++) {
		  $it = explode("=",$argv[$i],2);
		  $it[0] = preg_replace('/^--/','',$it[0]);
		  $eventval[$it[0]] = (isset($it[1]) ? $it[1] : true);
	   }
	}

print_r($eventval);

$eventhost=$eventval['eventhost'];
$event=$eventval['event'];
$hoststate=$eventval['hoststate'];
$service=$eventval['service'];
$servicestate=$eventval['state'];
$serviceattempts=$eventval['serviceattempts'];
$maxserviceattempts=$eventval['maxserviceattempts'];
$servicestate=$eventval['servicestate'];
$lastservicestate=$eventval['lastservicestate'];
$servicecheckcommand=$eventval['servicecheckcommand'];
$longserviceoutput=$eventval['longserviceoutput'];

unset($eventval);



function call_glpi($args) {
   global $deflate,$base64;
   $url=$args['url'];
   $host=$args['host'];

   echo "+ Calling {$args['method']} on http://$host/$url\n";

   if (isset($args['session'])) {
      $url_session = $url.'?session='.$args['session'];
   } else {
      $url_session = $url;
   }

   $header = "Content-Type: text/xml";

   if (isset($deflate)) {
      $header .= "\nAccept-Encoding: deflate";
   }
   

   $request = xmlrpc_encode_request($args['method'], $args);
   $context = stream_context_create(array('http' => array('method'  => "POST",
                                                          'header'  => $header,
                                                          'content' => $request)));

   $file = file_get_contents("http://$host/$url", false, $context);
   if (!$file) {
      die("+ No response\n");
   }

   if (in_array('Content-Encoding: deflate', $http_response_header)) {
      $lenc=strlen($file);
      echo "+ Compressed response : $lenc\n";
      $file = gzuncompress($file);
      $lend=strlen($file);
      echo "+ Uncompressed response : $lend (".round(100.0*$lenc/$lend)."%)\n";
   }
   
   $response = xmlrpc_decode($file);
   if (!is_array($response)) {
      echo $file;
      die ("+ Bad response\n");
   }
   
   if (xmlrpc_is_fault($response)) {
       echo("xmlrpc error(".$response['faultCode']."): ".$response['faultString']."\n");
   } else {
      return $response;
   }
}

if (!extension_loaded("xmlrpc")) {
   die("Extension xmlrpc not loaded\n");
}



// What state is the SERVICE in?
if (($hoststate == "UP")) {  // Only open tickets for services on hosts that are UP
	echo "Host is UP \n";
	switch ($event) {
		case "OK":
			echo "Event is OK \n";
			if (($lastservicestate == "CRITICAL")) { 
				// The service just came back up - perhaps we should close the ticket...
				$arg['method'] = "glpi.doLogin";
				$arg['url'] = $xmlurl;
				$arg['host'] = $xmlhost;
				$arg['login_password'] = $password;
				$arg['login_name'] = $user;

				$response = call_glpi($arg);
				$session = $response['session'];

				unset($arg);
				unset($response);
				
				if (!empty($session)) {
					$arg['method'] = "glpi.listTickets";
					$arg['url'] = $xmlurl;
					$arg['host'] = $xmlhost;
					$arg['session'] = $session;
					$arg['order'] = "id";
					$arg['status'] = 1;

					$response = call_glpi($arg);
					echo "XXX1: "; print_r($arg);

					unset($arg);

					foreach ($response as $ticket) {

						echo $ticket['name']."\n";
						echo "$service on $eventhost is in a Critical State!\n";

						if ($ticket['name'] == "$service on $eventhost is in a Critical State!") {
							$fields = array ('Ticket' => array (array ('id' => $ticket['id'], 'status' => '6'))); 
								
							$arg['method'] = "glpi.updateObjects";
							$arg['url'] = $xmlurl;
							$arg['host'] = $xmlhost;
							$arg['session'] = $session;
							$arg['fields'] = $fields;

							echo "XXX2: ";print_r($arg);

							$response = call_glpi($arg);	

							echo "XXX2: ";print_r($arg);

							unset($arg);
							unset($response);
							
						}
					}
				}
				
				$arg['method'] = "glpi.doLogout";
				$arg['url'] = $xmlurl;
				$arg['host'] = $xmlhost;
				$arg['session'] = $session;

				$response = call_glpi($arg);
				unset($arg);
				unset($response);
			}
			break;
		case "CRITICAL":
			echo "Event is Critical \n";
			# Aha!  The service appears to have a problem - perhaps we should open a ticket...
			# Is this a "soft" or a "hard" state?
			echo "servicestate=".$servicestate."\n";
			switch ($servicestate) {	
				//case "HARD":
				case "CRITICAL":
					echo "$serviceattempts == $maxserviceattempts\n";

					if ($serviceattempts == $maxserviceattempts){
						$arg['method'] = "glpi.doLogin";
						$arg['url'] = $xmlurl;
						$arg['host'] = $xmlhost;
						$arg['login_password'] = $password;
						$arg['login_name'] = $user;

						$response = call_glpi($arg);
						$session = $response['session'];

						unset($arg);
						unset($response);
						if (!empty($session)) {
							$title = "$service on $eventhost is in a Critical State!";
							$content = "$service on $eventhost is in a Critical State.  Please check that the service or check is running and responding correctly \n
										Host \t\t\t = $eventhost \r
										Service Check \t = $service \r
										State \t\t\t = $event \r
										Check Attempts \t = $serviceattempts/$maxserviceattempts \r
										Check Command \t = $servicecheckcommand \r
										Check Output \t = $serviceoutput \r
										$longserviceoutput \r
							";
							
							echo $content."\n";

							$arg['method'] = "glpi.createTicket";
							$arg['url'] = $xmlurl;
							$arg['host'] = $xmlhost;
							$arg['session'] = $session;
							$arg['title'] = $title;
							$arg['content'] = $content;
							$arg['urgancy'] = 5;
							$arg['use_email_notification'] = 1;
								
							$response = call_glpi($arg);
							unset($arg);
							unset($response);
						}
						
						$arg['method'] = "glpi.doLogout";
						$arg['url'] = $xmlurl;
						$arg['host'] = $xmlhost;
						$arg['session'] = $session;

						$response = call_glpi($arg);
						unset($arg);
						unset($response);
					}
					break;
			}
			break;
	} //end event cases	
}


?>
