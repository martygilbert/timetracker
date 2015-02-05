<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to find the official earnings over a given time period
*/
global $CFG, $DB, $USER;

$STARTMONTH = 8;
$STARTYEAR = 2013;
$STARTDAY = 1;

$ENDMONTH = 5;
$ENDYEAR = 2014;
$ENDDAY = 30;

$START=mktime(null,null,null,$STARTMONTH,$STARTDAY,$STARTYEAR);
$END=mktime(null,null,null,$ENDMONTH,$ENDDAY,$ENDYEAR);

$courses = get_courses(2, 'fullname ASC', 'c.id, c.shortname');

$str = implode (',', array_keys($courses));

$users = $DB->get_records_select('block_timetracker_workerinfo',
    'courseid in ('.$str.')');

$timesheets = $DB->get_records_select('block_timetracker_timesheet',
    'courseid in ('.$str.') AND workersignature between '.$START.' AND '.$END.
    //'courseid = 36 AND workersignature between '.$START.' AND '.$END.
    ' AND submitted != 0');

foreach ($timesheets as $timesheet){
    $worker = $users[$timesheet->userid];
    if(isset($worker->totalearnings)){
        $worker->totalearnings += ($timesheet->regpay+$timesheet->otpay);
    } else {
        $worker->totalearnings = ($timesheet->regpay+$timesheet->otpay);
    }
}

foreach($users as $worker){
    if(isset($worker->totalearnings)){
        echo "$worker->firstname,$worker->lastname,$worker->email,";
        echo "$worker->dept,$worker->totalearnings\n";
    }
    

}
