<?php
// ----------------------------------------------------------------------------------------
// Script Name:			manage-host-tickets.php
// Script Location:		/usr/share/nagios3/plugins/eventhandlers/
// Description:			Creates or closes glpi tickets according to host UP/Down states.
// Dependancies:		GLPI Webservices plugin.
//
// Required CommandLine Variables:
//		eventhost=$HOSTNAME$
//		event=$HOSTSTATE$
//		state=$HOSTSTATETYPE$
//		hostattempts=$HOSTATTEMPTS$
//		maxhostattempts=$MAXHOSTATTEMPTS$
//		hostproblemid=$HOSTPROBLEMID$
//		lasthostproblemid=$LASTHOSTPROBLEMID$

// Email Notifications:  For email notifications to work you must assign either a category name 

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

$eventhost=$eventval['eventhost'];
$event=$eventval['event'];
$state=$eventval['state'];
$hostattempts=$eventval['hostattempts'];
$maxhostattempts=$eventval['maxhostattempts'];
$hostproblemid=$eventval['hostproblemid'];
$lasthostproblemid=$eventval['lasthostproblemid'];
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

# What state is the HOST in?
switch ($event) {
	case "UP":
		# The host just came back up - perhaps we should close the ticket...
		if ($lasthostproblemid != 0) { 
			$arg['method'] = "glpi.doLogin";
			$arg['url'] = $xmlurl;
			$arg['host'] = $xmlhost;
			$arg['login_password'] = $password;
			$arg['login_name'] = $user;

			$response = call_glpi($arg);
			
			$session = $response['session'];

			unset($arg);
			unset($response);
			
			if (!empty($session)){	
				$arg['method'] = "glpi.listTickets";
				$arg['url'] = $xmlurl;
				$arg['host'] = $xmlhost;
				$arg['session'] = $session;
				$arg['order'] = "id";
				$arg['status'] = '1';

				$response = call_glpi($arg);

				unset($arg);
				
				foreach ($response as $ticket) {
					if ($ticket['name'] == "$eventhost is down!") {
						$fields = array ('Ticket' => array (array ('id' => $ticket['id'], 'status' => '6'))); 
								
						$arg['method'] = "glpi.updateObjects";
						$arg['url'] = $xmlurl;
						$arg['host'] = $xmlhost;
						$arg['session'] = $session;
						$arg['fields'] = $fields;

						$response = call_glpi($arg);	
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
	//case "UNREACHABLE":
		# We don't really care about warning states, since the host is probably still running...
	
	case "DOWN":
		# Aha!  The host appears to have a problem - perhaps we should open a ticket...
		# Is this a "soft" or a "hard" state?
			switch ($state) {
				# We're in a "soft" state, meaning that Nagios is in the middle of retrying the
				# check before it turns into a "hard" state and contacts get notified...

				//case "SOFT":		
					# We don't want to open a ticket on a "soft" state.
				

				case "HARD":
					if ($lasthostproblemid != 1) {
						if ($hostattempts == $maxhostattempts){
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
								
								$title = "$eventhost is down!";
								$content = "$eventhost is down.  Please check that the server is up and responding";
								
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
					}
				//end state cases
			}
	//end event cases
}


?>
