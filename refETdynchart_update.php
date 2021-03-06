<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<style type="text/css">
table#chart_table{
 display:inline;
}
</style>
<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<?php  
//Obtain metadata for requested station (displayed directly on webpage, see below), and compute the reference ET for period of record at that station. 

require_once( './cronos.php' );
require_once( './ETfunctionAPI.php' );
require_once('./passwords.php');

// Replace with your API key or include in your own passwords.php file.
$c = new CRONOS( $cronosAPIkey );

// Collect data for requested station.
$results = $c->listStations( array(), array(), array($_REQUEST['station']), array(), true );

// Collect the station and metadata.
$stations=array();
$stninfo=array();

foreach ($results as $r){
  
  $stations[] = $r['station'];
  $stninfo = $r;
  //specific formatting for certain elements (e.g. remove apostrophes from name and city)
  $stninfo['elev'] = $r['elev(ft)'];
  $stninfo['type'] = $r['network'];
  $stninfo['name'] = str_replace("'", "", $r['name']);
  $stninfo['city'] = str_replace("'", "", $r['city']);
  
}

// Define start and enddates.
$start=date('Y-m-d',strtotime($stninfo['startdate']));
//might need to limit to past 3 years to reduce amount of time for loading the page
//$start=date('Y-m-d',strtotime($stninfo['enddate']." -3year"));
$end=date('Y-m-d',strtotime("yesterday"));

// Get some data for requested dates and station.
$daily = $c->getDailyData( $stations, $start, $end );

// Compute the reference ET per day at requested station.
foreach( $daily as $d ) {
    
  // Format the day of year for reference ET estimate
  $doy=date('z',strtotime($d['ob']));
  $doy=$doy+1;

  // Exclude the six meteorological input parameters if any are NULL (ie. do not compute reference ET if input parameters are NULL). 
  // Also, include sravg!='' argument in this if statement for ECONET and RAWS networks which record solar radiation.
  if($stninfo['type']=='ECONET' || $stninfo['type']=='RAWS'){
  if($d['sravg']!='' && $d['tempmax']!='' && $d['tempmin']!='' && $d['wsavg']!='' && $d['rhmax']!='' && $d['rhmin']!=''){
  $stninfo['data'][$d['ob']]['etavg']=HargreavesRad_ET_estimate($stninfo['type'],$d['sravg'],$d['tempmax'],$d['tempmin'],$d['wsavg'],$d['rhmax'],$d['rhmin'],$doy,$stninfo['elev'],$stninfo['lat'],$stninfo['lon']);
  $stninfo['data'][$d['ob']]['etavg_inch']=($stninfo['data'][$d['ob']]['etavg']*0.03937007874);
  }
  }else{
  //exclude sravg!='' argument for ASOS/AWOS since this parameter is always NULL for those networks (they do not record solar radiation).
  if($d['tempmax']!='' && $d['tempmin']!='' && $d['wsavg']!='' && $d['rhmax']!='' && $d['rhmin']!=''){
  $stninfo['data'][$d['ob']]['etavg']=HargreavesRad_ET_estimate($stninfo['type'],$d['sravg'],$d['tempmax'],$d['tempmin'],$d['wsavg'],$d['rhmax'],$d['rhmin'],$doy,$stninfo['elev'],$stninfo['lat'],$stninfo['lon']);
  $stninfo['data'][$d['ob']]['etavg_inch']=($stninfo['data'][$d['ob']]['etavg']*0.03937007874);
  } 
  }
  //Format the date as required by Google Annotated Vis.
  $date=date('Y-m-d',strtotime($d['ob']));
  list($Y,$M,$D)=explode("-",$date);
  $m=$M-1;
  $stninfo['data'][$d['ob']]['date']="new Date (".($Y+0).", ".($m+0).", ".($D+0).")";
}?>
<script type="text/javascript">
//Set up Google Annotated Timeline properties (date as a date and add a reference ET line).
    google.load('visualization', '1', {packages: ['annotatedtimeline']});
    google.setOnLoadCallback(drawVisualization);
    function drawVisualization() {
   var data = new google.visualization.DataTable();
  data.addColumn('date', 'Date');
  data.addColumn('number', 'Calculated Daily PM ET');
  // Output results for annotated timeline.
  data.addRows([
<?php
//Loop through results and put them into array called $data.
$row=1;
foreach($stninfo['data'] as $data){

   // If reference ET estimates are not between 0 and 10, do not show on chart (ie. continue to next iteration of loop).
   if(!array_key_exists( 'etavg', $data ) || $data['etavg']<=0 || $data['etavg']>10){
   $row++;  //be sure to increment $row anyways (to make sure the below if statement works properly)
   continue;
   }
   if($row==count($daily)){?>
   [<?php echo $data['date'];?>, <?php if($_REQUEST['unit']=='mm'){echo $data['etavg'];}
    elseif($_REQUEST['unit']=='inches'){echo $data['etavg_inch'];}?>]
    <?php }else{ ?>   
   [<?php echo $data['date'];?>, <?php if($_REQUEST['unit']=='mm'){echo $data['etavg'];}
    elseif($_REQUEST['unit']=='inches'){echo $data['etavg_inch'];}?>],
  <?php
    }
  $row++;
  } ?> //end foreach loop
  ]); //end data.addRows
      var annotatedtimeline = new google.visualization.AnnotatedTimeLine(
          document.getElementById('visualization'));
      //Specify timeline properties.
      annotatedtimeline.draw(data, { 'displayAnnotations': true,
                                    'allValuesSuffix': '<?php if($_REQUEST['unit']=='mm'){echo " mm";}
				    elseif($_REQUEST['unit']=='inches'){echo " inches";}?>', // A suffix that is added to all values
   				            'colors':['green'], // The colors to be used
                                    'displayExactValues': true, // Do not truncate values (i.e. using K suffix)
                                    'legendPosition': 'newRow', // Can be sameRow
                                    'zoomStartTime': new Date(<?php echo $_REQUEST['year'];?>, 0 ,1), 
                                     //NOTE: month 1 = Feb (javascript to blame)
                                    'zoomEndTime': new Date(<?php echo $_REQUEST['year'];?>, 11 ,31) 
                                    //NOTE: month 1 = Feb (javascript to blame)
                                   });
		  }
  </script>
</head>
<body>
<?php //Echo station metadata and link to explain station types.
echo "<p><b>Station: </b>".$stninfo['name']." (".$stninfo['station'].")<br><b>Type: </b>".$stninfo['type']." <A href=# onClick=window.open('http://www.nc-climate.ncsu.edu/dynamic_scripts/cronos/types.php','link','width=500,height=1000,scrollbars=yes')>what does this mean?</A> <br><b>Elevation: </b>".$stninfo['elev']." feet above sea level<br><b>Location: </b>".$stninfo['city'].", ".$stninfo['state']."<br><b>Start Date: </b>".$stninfo['startdate']."<br><b>End Date: </b>".$stninfo['enddate']."</p>";?>
<p><table id="chart_table"><tr>
<?php If($_REQUEST['unit']=='inches'){ ?>
  <td><form action="./refETdynchart_update.php?station=<?php echo $stninfo['station'];?>&year=<?php echo $_REQUEST['year'];?>&unit=mm" method="post">
  <input type="submit" name="units" value="Display mm">
  </form></td>
<?php }
elseif($_REQUEST['unit']=='mm'){
?>
  <td><form action="./refETdynchart_update.php?station=<?php echo $stninfo['station'];?>&year=<?php echo $_REQUEST['year'];?>&unit=inches" method="post">
  <input type="submit" name="units" value="Display inches">
  </form></td>
<?php }
?>
  </tr></table></p>
<p><u><b><?php echo "Time Series of FAO56 Penman-Monteith Estimated Reference Evapotranspiration";?></u></b></p>
<div id="visualization" style="width: 800px; height: 400px;"></div>
<br><br><p align='left'><img src='images/get_adobe_flash_player.png' width='158' height='39' border='0' usemap='#Map'><map name='Map'><area shape='rect' coords='0,0,162,44' href='http://get.adobe.com/flashplayer/?promoid=BUIGP'></map></p>
</body>
</html>