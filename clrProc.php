<?php
require_once (dirname(__FILE__) . "/lib/getBusInfo.class.php");

$config = parse_ini_file (dirname (__FILE__) . "/settings.ini.php" );

$getBusInfo = new getBusInfo ($config);
$getBusInfo->clrProc ();

?>

