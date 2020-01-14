<?php
require("phpMQTT.php");
require("config.php");
$message = '{"DATETIME":1578465185,"TRAM_ID":"010-0001-nbus","LAT":"14.080249","LON":"100.599655","SPEED":"0","ACCURACY":10,"LAST_STATION":"\u0e40\u0e1a\u0e17\u0e32\u0e42\u0e01 \u0e28\u0e39\u0e19\u0e22\u0e4c\u0e27\u0e34\u0e17\u0e22\u0e4c"}';
//MQTT client id to use for the device. "" will generate a client id automatically
$mqtt = new bluerhinos\phpMQTT($host, $port, "ClientID".rand());
$topicName = 'tramLocation/010-0001-nbus';
$i = 0;
while ( $i < 3 ) {
if ($mqtt->connect(true)) {
		$mqtt->publish($topicName,$message, 0);
	$mqtt->close();
}else{
  echo "Fail or time out";
}
$i++;
}
?>
