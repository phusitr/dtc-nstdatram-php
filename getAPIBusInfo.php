<?php
require_once (dirname(__FILE__) . "/lib/getBusInfo.class.php");

$config = parse_ini_file (dirname (__FILE__) . "/settings.ini.php" );

$getBusInfo = new getBusInfo ($config);

if ( $getBusInfo -> chkHday () ) 
{
   while ( true ) {
	$getBusInfo -> getBusAPI ();
	sleep (10);
   }
}
?>
