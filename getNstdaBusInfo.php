
<?php

require_once ("phpMQTT.php");

/* Basic 
 * Get api data from DTC(NSTDA) bus infomation 
 * Author : Phusit Roongroj <phusit@nectec.or.th>
 * Internet Innovation Lab (INO)
 * National Electronics and Computer Technology Center (NECTEC)
 * 07-01-2020 NSTDA Tram project v2
 */

$result = null;
$config = parse_ini_file (dirname (__FILE__) . "/settings.ini.php" );
$data = array('api_token_key' => $config['TOKEN'], 'gps_list' => '['. $config['BUS_GPS_LIST'] . ']');

$busnumber_mapping = array (
	'010376600000012' => '001',
	'010376600000028' => '002',
	'010376600000029' => '003',
	'010376600000004' => '004',
	'010376600000013' => '007',
	'010376600000001' => '006',
	'010376600000035' => '005',
	'010376600000034' => '010',
	'010376600000033' => '011'
);
/* stream api */
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);
$context  = stream_context_create($options);



/* Loop for send mqtt bus information */

while (true)
{
	$result = @file_get_contents($config['API_URL'], false, $context);

	if ($result === FALSE) { 
	/* Handle error */ 
	} else {
		$data = json_decode ( $result , true );
		if ( ( $data['status'] == 200 ) && ( $data['message'] = 'ok' )) {
		$i = 0;

		/* Connect to MQTT broker */
		$mqtt = new bluerhinos\phpMQTT($config['HOST'], $config['PORT'], "ClientID".rand());
		while ( $i < count ( $data['data'] ) )
		{
			/* set topic for publish data to mqtt */
			$topicName = 'tramLocation/' . trim( $busnumber_mapping [ $data['data'][$i]['gps_id'] ] ) . "-0001-nbus";

			/* adjust data from dtc to json format and select important field only */ 
                        $message['DATETIME'] =  strtotime( $data['data'][$i]['time'] ); 
                        $message['TRAM_ID'] =  trim( $busnumber_mapping [ $data['data'][$i]['gps_id'] ] ) . "-0001-nbus" ;
                        $message['LAT'] = trim ( $data['data'][$i]['lat']  );
                        $message['LON'] = trim ( $data['data'][$i]['lon']  );
                        $message['SPEED'] = trim ( $data['data'][$i]['gps_speed'] / 3.6 );
                        $message['ACCURACY'] = 10;
                        $message['LAST_STATION'] = trim ( $data['data'][$i]['station_name'] );

			if ($mqtt->connect(true)) {
				$mqtt->publish($topicName,json_encode($message), 0);
				$mqtt->close();
				 echo "[" . $i . "] " . json_encode ( $message ) . "\n";  
			} else  {
				/* WriteLog (date('Y-m-d H:i:s') . " [MQTT]:Connection failed!");   */
				/* echo "b"; */
			}
                        $i++;
                }
	    }
	}

	sleep (10);
}

/* Error log reporting */
function WriteLog ($errmsg) 
{
	$fp = fopen ("error_log","a") ;
	fwrite ($fp, trim ( $errmsg ) ) ;
	fclose ( $fp );
	return;
}
?>
