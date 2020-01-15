<?php

require_once (dirname(__FILE__) . '/phpMQTT.php');

class getBusInfo 
{
	private $data = array ();
	private $config = array ();
	private $busnumber_mapping = array ();
	private $dsn;



	public function __construct ($config)
	{
		$this->config = $config;
		$this->busnumber_mapping = array (
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
		$this->data = array('api_token_key' => $this->config['TOKEN'], 
			'gps_list' => '['. $this->config['BUS_GPS_LIST'] . ']');
		
		$this->dsn = "pgsql:host=localhost;port=5432;dbname=".
                        $this->config['PG_DBNAME'].";user=".
                        $this->config['PG_USER'].";password=".
                        $this->config['PG_PASSWD'];
	}



	public function chkHday  ()
	{
	        if ( file_exists ( $this->config['GPS_INFO'] . "/hday.data" ) ) {
			$hday_data = file ( $this->config['GPS_INFO'] . "/hday.data" );
			if ( trim ( $hday_data[0] ) == date('Y') ) {
				$def_hday = strtolower(date("l", strtotime(date('Y-m-d') ) ) ) ;
				if ( ($def_hday != 'saturday') &&  ($def_hday != 'sunday') &&
					(! in_array(date('d-m') . "\n" , $hday_data ) ) )  {
					return true;
				}	
			}
		}
		return false;
	}


	public function clrProc ()
	{
		$buffer = explode (" " , shell_exec( $this->config['PIDOF']  . " " . $this->config['PHP'] ));
		$i = 1;
		while ( $i < count ( $buffer ) ) {
			shell_exec ($this->config['KILL'] . " -9 " . trim( $buffer [$i] ) );
			$i++;
		}
		
	}

	public function updateSTPoint ()
	{
		try {
		   $pdo = new PDO ( $this->dsn ) ;
		    if ( $pdo ) {
			    $stmt = $pdo->prepare ( "UPDATE tbl_bus_info SET geopoint = ST_POINT(longitude,latitude) 
				    WHERE date_rec BETWEEN '".date('Y-m-d') ." 05:00:00' AND
				     '". date('Y-m-d') ." 21:00:00';");
			$stmt->execute ();
		    }
		} catch ( PDOException $e ) {
			echo $e->getMessage ();
		}
	}


	public function saveLog ()
	{
		if ( file_exists ( $this->config['GPS_INFO'] .'/businfo.data' ) )
		{
		  $data = file (  $this->config['GPS_INFO'] .'/businfo.data' );
		  $i = 0;
		  while ( $i < count ( $data ) ) {
			$buffer = explode ("," , $data[$i] );
			try {
			$chkDupPdo = new PDO ( $this->dsn );
			if ( $chkDupPdo ) {
				$stmtchkDupPdo = $chkDupPdo->prepare(
				"SELECT COUNT(*) AS cnt FROM public.tbl_bus_info WHERE nstda_bus_no = '". $buffer[0] . "' 
				AND timestamp_rec = '" . strtotime( $buffer[3] ) . "'");
				$stmtchkDupPdo -> execute ();
				$d = $stmtchkDupPdo-> fetch ();
				if ( ! $d['cnt']  ) {
				    try {
					$pdo = new PDO ( $this->dsn );
					if ( $pdo ) {
					    $sql = "INSERT INTO public.tbl_bus_info VALUES('".$buffer[0]."','". 
					    $buffer[1]."','".$buffer[2]."','". strtotime( $buffer[3] ) ."',". 
			                    $buffer[8].",".$buffer[9].",". $buffer[4].",'" . $buffer[3] . "',NULL);";

					    /* prepare and execute sql command for insert log */
					    $stmt = $pdo->prepare($sql);
					    $stmt->execute ();
					}
				    }   catch (PDOException $e) {
					//report error message
					echo $e->getMessage ();
				    }   
				}
			}
		   } catch (PDOException $e) {
		   	echo $e->getMessage ();
		   }
	           $i++;
	        }
	    }
	}	


	public function publishMQTT ()
	{
		if (  file_exists ( $this->config['GPS_INFO'] .'/businfo.data') ) 
		{
			$data = file ( $this->config['GPS_INFO'] .'/businfo.data') ;
			if (count ( $data ) > 0 ) 
			{
				$i = 0;
				$mqtt = new bluerhinos\phpMQTT($this->config['HOST'], $this->config['PORT'], "ClientID".rand());
				while ( $i < count ( $data ) ) 
				{
					$buffer = explode ( "," , trim ( $data[$i] ) ) ;
					/* set topic for publish data to mqtt */
					$topicName = 'tramLocation/' . trim( $buffer[0] );

					/* adjust data from dtc to json format and select important field only */
                        		$message['DATETIME'] =  strtotime( $buffer[3] );
                        		$message['TRAM_ID'] =  trim( $buffer[0] );
                        		$message['LAT'] = trim ( $buffer[8]  );
                        		$message['LON'] = trim ( $buffer[9]  );
                        		$message['SPEED'] = trim ( $buffer[4] / 3.6 );
                        		$message['ACCURACY'] = 10;
                        		$message['BUS_LICENSE'] = "-";
					$message['LAST_STATION'] = trim ( $buffer[28] ) ;

					if ($mqtt->connect(true)) {
						$mqtt->publish($topicName,json_encode($message), 0);
						$mqtt->close();
					} 
					$i++;
				}
			}
		}	
		return;
	}

	public function getBusAPI ()
	{
		/* stream api */
		$options = array(
    		'http' => array(
        		'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        		'method'  => 'POST',
        		'content' => http_build_query($this->data)
    		 	)
		);
		$context  = stream_context_create($options);
		$result = @file_get_contents($this->config['API_URL'], false, $context);
		if ($result === FALSE) {
			/* Handle error */
		} else {
			$data = json_decode ( $result , true );
			if ( ( $data['status'] == 200 ) && ( $data['message'] = 'ok' )) {
				$fp = fopen ($this->config['GPS_INFO'] . '/businfo.data','w');
				$i = 0;
				while ( $i < count ( $data['data'] ) ) 
				{
					fwrite($fp, 
					$this->busnumber_mapping [ trim( $data['data'][$i]['gps_id']) ]."-0001-nbus," . 
					trim($data['data'][$i]['gps_id']).",".trim($data['data'][$i]['truck_name']) .",".
					trim($data['data'][$i]['time']).",".trim($data['data'][$i]['gps_speed']).",".
                    			trim($data['data'][$i]['status_code']).",".trim($data['data'][$i]['status_name_th']) .",".
                    			trim($data['data'][$i]['status_name_en']).",".trim($data['data'][$i]['lat']) .",".
                    			trim($data['data'][$i]['lon']).",".trim($data['data'][$i]['mileage']) .",".
                    			trim($data['data'][$i]['heading']).",".trim($data['data'][$i]['io_ch']) .",".
      					trim($data['data'][$i]['ecu_speed']).",".trim($data['data'][$i]['ecu_rpm']) .",".
                    			trim($data['data'][$i]['ecu_fuel_collect']).",".trim($data['data'][$i]['ecu_distance_count']) .",".
                    			trim($data['data'][$i]['car_volt']).",".trim($data['data'][$i]['batt_volt']).",".
                    			trim($data['data'][$i]['driver_card_id']).",".  trim($data['data'][$i]['driver_full_name']).",".  
                    			trim($data['data'][$i]['driver_personalcard']).",".trim($data['data'][$i]['sub_district_th']).",".
                    			trim($data['data'][$i]['sub_district_en']).",".trim($data['data'][$i]['district_th']).",".
                    			trim($data['data'][$i]['district_en']).",".trim($data['data'][$i]['province_th']).",".
                    			trim($data['data'][$i]['province_en']).",".trim($data['data'][$i]['station_id']).",".
                    			trim($data['data'][$i]['station_name']) . "\n");
					$i++;
				}
				fclose ( $fp );
			}
		}	
		return;
	}

	public function __destruct ()
	{
	}
}
?>
