<?php
	while ( true ) 
	{
		$startDayTime ="15:00:00";
		$endDayTime = "16.00.00";
		if (time() >= strtotime($startDayTime) && time() <= strtotime($endDayTime)) {
			echo "ok " . date ('H:i') ."\n ";
		} else {
			echo "finish";
		}

		sleep (5);	
	}
?>
