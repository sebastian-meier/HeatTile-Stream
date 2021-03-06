<?php

$session_name = 'heattile_conversion';
session_name($session_name);
session_start();

//This feature is really important
//If the data later on added to your database
//seams to be not precise enough, it might be
//due to the lag of this feature
ini_set("precision", 20);

//ATTENTION !!!!!
//Before adding your database credentials here,
//please make a backup of your data or first try
//this conversion-tool on a subset of your database
//the conversion is just adding data and not deleting
//or modifing data, but well, you never know.

//Database Connection
$db_server = "YOUR_MYSQL_SERVER";
$db_user = "YOUR_MYSQL_USER";
$db_pass = "YOUR_MYSQL_PASSWORD";
$db_database = "YOUR_MYSQL_DATABASE";
$db_table = "quake_locations";
$db_prefix = "quake_";

//For the conversion we need a latitude & longitude column
//If you have only one column which holds both numbers,
//you need to modify the code accordingly.
$db_lat = "latitude";
$db_lng = "longitude";

//Also you table needs to have a column with a unique id
$db_id = "id";

//We need an additional table to store the conversions
$db_conversion_table = "conversions_table";

//We need an additional table to store the maximum amount
//of locations per square per zoom-level
$db_max_table = "locations_max_table";

$db_max_table = $db_prefix.$db_max_table;
$db_conversion_table = $db_prefix.$db_conversion_table;

//If the conversion process taking longer then your server
//timeout, modify the following value, making it less rows
//per cycle to be modified
$db_max = 1000;

//If you use bigger numbers for the variable above you 
//should also increase the maximum execution timeout
set_time_limit(30000);

$redirect = false;
$message = false;

/*------------------- VISUALIZATION DATA -------------------*/

//The data is rasterized into a tiled grid
//The tile-size in zoom-level "0" is set here:
$gridsize = 2.5;

//For the visualization you need to choose if you want to cluster locations similar to marker clusters
//Or create a value based clustering, this requires that you have $cluster_value_col defined and the according grouping functions
//$vis_type defines which data is displayed in the visualization
//"value" value based
//"location" location clustering
$vis_type = "value_max";

//Visualization type
//Available options: square, hex, circle, raw, clustered (raw)
$vis = "square";

//The color range that should be applied to the data range
//This array also defines the range-size: count($colors)
$colors = array("0a109a","2c4dac","3e7eba","5ea8c0","98cebc","fbea9c","ffbf54","ff951a","f16a0f","da3203","b91b03");

/*------------------- ZOOM LEVEL DATA -------------------*/

$zoom_min = 0;
$zoom_max = 21;

//The visualizations have the option to deliver individual marker data at a specific zoom level
//If you always want to have heatmaps, keep zoom_single to 99
$zoom_single = 99;

//Based on the gridsize the higher zoom-levels
//can be calculated automatically, as every
//zoom-level always doubles (gridsize * pow(2, zoomlevel))
$max = 20037508;
$step_size = array();
$steps = array();
for($i = $zoom_min; $i<$zoom_max; $i++){
	array_push($step_size, ($gridsize * pow(2, $i)));
	array_push($steps, round($max*2.0/$step_size[$i]));
}

/*------------------- TIMESERIES DATA -------------------*/

$time = true;
$time_col = "time";

/*------------------- MULTIPLE DATASETS -------------------*/

//If your dataset holds multiple subdatasets, that you want to visualize seperately 
//You need to have another database table at hand that holds unique identifiers to filter the main location database

$multiple = true;
$multiple_ref_col = "quake_id";

/*------------------- GROUPING / CLUSTERING -------------------*/

//You can use a set of clustering methods
//The names of the methods are equivalent to the SQL methods used in the queries (maybe this helps to understand what is happening here)

//"COUNT" will add "COUNT_MAX", "COUNT_MIN", "COUNT_AVG"
//"COUNT_MAX"
//"COUNT_MIN"
//"COUNT_AVG"
//Count all Locations per Grid-Cell

/*---------*/
//If you want to use one of the following methods you need to provide the "cluster_value_col"
$cluster_value_col = "intensity";

//"SUM"
//Sum up all values
//Example: Crimes commited per location, returns the number of crimes per Grid-Cell

//"MAX"
//The highest value of one location per Grid-Cell
//Example: Earthquakes - each location has the current magnitude, returns the highest magnitude per Grid-Cell

//"MIN"
//The lowest value of one location per Grid-Cell
//Example: Groundwater level - each location has the current groundwater level, returns the lowest level per Grid-Cell

//"AVG"
//The average value of one location per Grid-Cell
//Example: Average Income - each location has the average income, returns the average income per Grid-Cell

$clustering = array("COUNT", "MAX", "SUM", "MIN", "AVG");

if(in_array("COUNT", $clustering)){
    array_push($clustering, "COUNT_MAX");
    array_push($clustering, "COUNT_MIN");
    array_push($clustering, "COUNT_AVG");
}

/*------------------- MYSQL -------------------*/

$mysqli = mysqli_connect($db_server, $db_user, $db_pass, $db_database);

if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}

function sql_request($request, $requestArray){
    global $mysqli;
    
    if(!$requestArray||(is_array($requestArray)&&(count($requestArray)<1))){
        
        $result = $mysqli->query($request);

    }else{

        if(!is_array($requestArray)){
            $requestArray = array($requestArray);
        }

        $stmt = $mysqli->stmt_init();
        $r = $stmt->prepare($request);
        if(!$r){
            printf("Error: %s\n", $mysqli->error);
            exit();
        }

        $types = "";
        foreach ($requestArray as $value) {
            if(($value === 0)||($value === "0")){
                $types .= "i";
            }else{
                $iv = (int)$value;
                $dv = (double)$value;
                if(($iv != 0)&&($iv == $dv)&&(strlen($value)==strlen($iv))){
                    $types .= "i";
                }else{
                    if (($dv != 0)&&(strlen($value)==strlen($dv))) {
                        $types .= "d";
                    }else{    
                        $types .= "s";
                    }
                }
            }
        }
        $params = array($types);

        foreach ($requestArray as $value) {
            array_push($params, $value);
        }

        $r = call_user_func_array(array($stmt, "bind_param"), refValues($params));
        if(!$r){
            printf("Error: %s\n", $mysqli->error);
            exit();
        }

        $stmt->execute();

        //if mysqlnd is installed
        //$result = $stmt->get_result();

        $result = iimysqli_stmt_get_result($stmt);

    }

    if(!$result){
        printf("Error: %s\n", $mysqli->error);
        //To make sure big data conversion don't pile up errors we will exit upon the first error
        //If for some reason you know what you are doing, remove the exit();
        exit();
    }

    return($result);
}

function refValues($arr){
        $refs = array();

        foreach ($arr as $key => $value)
        {
            $refs[$key] = &$arr[$key]; 
        }

        return $refs; 
}

function exec_multi($query){
    global $mysqli;
    if ($mysqli->multi_query($query)) {
        do {
            if (!$mysqli->more_results()) {
                break;
            }
            if (!$mysqli->next_result()) {
                printf("Error: %s\n", $mysqli->error);
                exit();
            }
        } while (true);
    }else{
        printf("Error: %s\n", $mysqli->error);
        exit();
    }
}

//if mysqlnd is not installed
//http://php.net/manual/en/mysqli-stmt.get-result.php
class iimysqli_result
{
    public $stmt, $nCols;
} 

function iimysqli_stmt_get_result($stmt){
    $metadata = mysqli_stmt_result_metadata($stmt);
    $ret = new iimysqli_result;
    if (!$ret) return NULL;

    $ret->nCols = mysqli_num_fields($metadata);
    $ret->stmt = $stmt;

    mysqli_free_result($metadata);
    return $ret;
}

function iimysqli_result_fetch_array(&$result)
{
    $ret = array();
    $code = "return mysqli_stmt_bind_result(\$result->stmt ";

    for ($i=0; $i<$result->nCols; $i++)
    {
        $ret[$i] = NULL;
        $code .= ", \$ret['" .$i ."']";
    };

    $code .= ");";
    if (!eval($code)) { return NULL; };

    // This should advance the "$stmt" cursor.
    if (!mysqli_stmt_fetch($result->stmt)) { return NULL; };

    // Return the array we built.
    return $ret;
}

function sql_fetch_row($result){
    if(get_class($result)=="iimysqli_result"){
        return iimysqli_result_fetch_array($result);
    }else{
        return $result->fetch_row();
    }
}

function sql_close($result){
    if(get_class($result)=="iimysqli_result"){
        $result->stmt->close();
    }else{
        $result->close();
    }
}

/*------------------- CONVERSION FUNCTIONS -------------------*/

function ToWebMercator($mercatorY_lat, $mercatorX_lng){
    if((abs($mercatorX_lng) > 180 || abs($mercatorY_lat) > 90)){
        return;
	}

    $x = 6378137.0 * ($mercatorX_lng * 0.017453292519943295);
    $a = $mercatorY_lat * 0.017453292519943295;
    $y = 3189068.5 * log((1.0 + sin($a)) / (1.0 - sin($a)));

    return array($y, $x);
}

function ToGeographic($mercatorY_lat, $mercatorX_lng){
	global $max;
    if ((abs($mercatorX_lng) > $max) || (abs($mercatorY_lat) > $max)){
    	return;
    }

    $x = $mercatorX_lng;
    $y = $mercatorY_lat;
    $num3 = $x / 6378137.0;
    $num4 = $num3 * 57.295779513082323;
    $num5 = floor((($num4 + 180.0) / 360.0));
    $num6 = $num4 - ($num5 * 360.0);
    $num7 = 1.5707963267948966 - (2.0 * atan(exp((-1.0 * $y) / 6378137.0)));
    $mercatorX_lng = $num6;
    $mercatorY_lat = $num7 * 57.295779513082323;

    return array($mercatorY_lat, $mercatorX_lng);
}

?>