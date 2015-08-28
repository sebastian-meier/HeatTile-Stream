<?php

	require_once("config.php");

	$first = false;

	if($handle = opendir('./demos/earthquake//Rohdaten')){
		while (false !== ($entry = readdir($handle))) {
			if ($entry != "." && $entry != ".." && $entry != ".DS_Store") {
				$s1 = explode("_", $entry);
				echo $s1[0];
				$s2 = explode("-", $s1[1]);
				echo ' ('.$s2[0].') ';
				$s3 = explode("-", $s1[2]);
				$m = date("m", strtotime($s2[2]));
				echo $s2[3].'-'.$m.'-'.$s2[1].' '.$s3[0].':'.$s3[1].':'.$s3[2];
				$date = new DateTime($s2[3].'-'.$m.'-'.$s2[1].' '.$s3[0].':'.$s3[1].':'.$s3[2]);
				echo ' / '.$date->format('Y-m-d H:i:s');
				echo "<br />$entry<br /><br />\n";
				flush();

    			$sql = 'INSERT INTO `quake_quakes` (name, code, time)VALUES("'.$s1[0].'", "'.$s2[0].'", "'.$date->format('Y-m-d H:i:s').'")';
				$link->query($sql);
				$quake_id = $link->insert_id;
				echo $quake_id."<br /><br />";

				$min_lng = $max_lng = $max_lat = $min_lat = null;

				$sql = "";
				$sc = 0;

				if($shandle = opendir('./demos/earthquake//Rohdaten/'.$entry.'/SEMBLANCE')){
					while (false !== ($sentry = readdir($shandle))) {
						if ( is_numeric(substr($sentry, 0,1))) {
							$sql = "";
							$sc = 0;

							echo "<br />$sentry<br /><br />\n";
							flush();

							$n = explode(".", $sentry);
							$fhandle = fopen('./demos/earthquake//Rohdaten/'.$entry.'/SEMBLANCE/'.$sentry, "r");
							if ($fhandle) {
								while (($line = fgets($fhandle)) !== false) {
									if(substr($line, 0,1)!="#"){
										$l = explode(" ", $line);
										$sql .= 'INSERT INTO `quake_locations` (`quake_id`, `time`, `latitude`, `longitude`, `intensity`)VALUES('.$quake_id.', '.intval($n[0]).', '.number_format(floatval($l[0]),30).', '.number_format(floatval($l[1]),30).', '.number_format(floatval($l[2]),30).');';
										$sc++;
										//$sql = 'INSERT INTO `quake_locations` (quake_id, time, latitude, longitude, intensity)VALUES('.$quake_id.', '.intval($n[0]).', '.number_format(floatval($l[0]),30).', '.number_format(floatval($l[1]),30).', '.number_format(floatval($l[2]),30).')';
										//echo $sql;
										//query_mysql($sql, $link);
									}

									if($sc == 500){
										$sc = 0;
										flush();

										$link->multi_query($sql);
										while ($link->next_result()) {;}

										$sql = "";	
									}
								}
							}
							fclose($fhandle);
							$link->multi_query($sql);
							while ($link->next_result()) {;}
							$sql = "";
							$sc = 0;
						}
					}
					closedir($shandle);
				}
			}
		}
		closedir($handle);
	}

?>