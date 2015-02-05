<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to add a single work unit to each worker in a course.
*/
global $CFG, $DB, $USER;

//ONLY 1 digit month-do not zero-pad. Screws up otherwise
$startmonth=4;
$startyear=2014;

//ONLY 1 digit month-do not zero-pad. Screws up otherwise
$endmonth=4;
$endyear=2014;

$courseid = 95; //residential living
//$courseid = 111; //SGA
//$courseid = 105;//Judicial
//$courseid = 112; //test site
//$courseid = 119; //Dept_Gateway

$duration = 1 * 3600; //1 hour
//$date = 1; //put unit on first day of month


$courseworkers = $DB->get_records('block_timetracker_workerinfo',
    array('courseid'=>$courseid, 'active'=>1,'deleted'=>0), 'lastname, firstname');

$newunit = new stdClass();
$newunit->courseid = $courseid;
$newunit->lasteditedby = 0;
$newunit->lastedited = time();

$startinfo = get_month_info($startmonth, $startyear);
$endinfo = get_month_info($endmonth, $endyear);

$starttime = $startinfo['firstdaytimestamp'];
//echo $starttime."\t".($starttime+$duration)."\n";

if($startinfo['firstdaytimestamp'] <= $endinfo['firstdaytimestamp']){
    do {
        $newunit->timein = $starttime;
        $newunit->timeout = $starttime + $duration;
    
        foreach($courseworkers as $worker){
            $newunit->payrate = $worker->currpayrate;
            $newunit->userid = $worker->id;
            echo "adding a unit for $worker->firstname $worker->lastname $worker->id\n";
            $res = $DB->insert_record('block_timetracker_workunit', $newunit);
            if(!$res){
                error_log("failed inserting new work unit for ".
                    "$worker->firstname $worker->lastname");
                exit;
            }
        }
    
        $starttime = strtotime('+ 1 month', $starttime);
    } while ($starttime <= $endinfo['firstdaytimestamp']);
} 
