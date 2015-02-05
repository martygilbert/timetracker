<?php


define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

global $CFG, $DB, $USER;

/**

    README: To make your life easier, simply use the 'format' function
    of Excel to make the columns appear like you need to.

    The purpose of this script is to import all student schedules
    after clearing out the existing schedules

    CSV file needs the following format
        studentID,Course,days*,start time**,startdate,enddate*** 
        (Military format, end time (military format)

    *days will be in the format: M or MWF or MW or T or TR or MtoF or R etc
    **start time will be a 3/4 digit number i.e. 1200 130 1330 etc
    ***dates are mm/dd/yyyy
*/

//$file='2012SpringStudentSchedules.csv';
//$file='updatedCourseSchedules.csv';
//$file='2012FallStudentSchedules.csv';
//$file='2012FallSchedules_2.csv';
//$file='2013SpringStudentSchedules.csv';
//$file='2013SpringStudentSchedulesB.csv';
//$file='2013SchedulesFinal.csv';
//$file='/tmp/2013FallSchedules.csv';
//$file='2013FallStudentSchedules_final.csv';
//$file='2014SpringInitial.csv';
$file='2014SpringFinal.csv';

$count = 0;
if(($handle = fopen($file, "r")) !== FALSE){
    $scheduleitems = array();
    while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){

        $studentid  = strtolower($data[0]); 
        $coursedesc = $data[1];
        $days       = $data[2];
        $start      = $data[3];
        $end        = $data[4];
        $sdate      = $data[5];
        $edate      = $data[6];

        if(strtolower($days) == 'tba'){
            continue;
        }

        $entry              = new stdClass();
        $entry->studentid   = $studentid;
        $entry->course_code = $coursedesc;
        $entry->days        = $days;
        $entry->begin_time  = fixTime($start);
        $entry->end_time    = fixTime($end);
        $entry->begin_date  = fixDate($sdate);
        $entry->end_date    = fixDate($edate);




        $scheduleitems[] = $entry;
    }
    
    if(sizeof($scheduleitems) > 0){
        echo 'About to process '.sizeof($scheduleitems).' schedule items'."\n";
        //if we have some, then wipe the old entries, and add the new
        $DB->delete_records('block_timetracker_schedules');
        
        foreach($scheduleitems as $item){
            //print_object($item);
            $res = $DB->insert_record('block_timetracker_schedules', $item, true, true);
            if($res) $count++;
        }
    } else {
        echo "No schedule items found\n";
    }
} else {
    echo "Cannot open file for reading\n";
}
echo "Handled $count schedule items\n";


function fixTime($badTime){
    $newTime = str_replace(':', '', $badTime);

    $isPM = stripos($newTime, 'pm');

    $newTime = str_ireplace('pm', '', $newTime);
    $newTime = str_ireplace('am', '', $newTime);

    $newTime = str_replace(' ', '', $newTime);

    if($isPM) $newTime = $newTime + 1200;

    $newTime = str_pad($newTime, 4, '0', STR_PAD_LEFT);

    return $newTime;
}

function fixDate($strDate){
    return strtotime($strDate);
}
