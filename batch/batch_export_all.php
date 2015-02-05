<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to backup *everything* from a category
*/
global $CFG, $DB, $USER;


$catid=2; //2 is Work Study
$courses = get_courses($catid, 'fullname ASC', 'c.id,c.shortname');

if(!$courses){
    echo "No courses exist for $catid";
}


$str = implode (',', array_keys($courses));

$sql = 'SELECT 
    unit.*,
    info.firstname,
    info.lastname,
    info.dept
    from mdl_block_timetracker_workunit as unit, mdl_block_timetracker_workerinfo as info where unit.userid=info.id AND unit.courseid in ('.$str.') ORDER BY info.lastname,info.firstname,unit.timein';


$records = $DB->get_records_sql($sql);

if(!$records){
    echo "Error getting records.";
    echo "$sql";
} else {
    echo "Last,First,Dept,Date in,Time in,Date out,Time out,Elapsed,Rate,Earnings,On timesheet\n";
    foreach ($records as $record){
        $hours = get_hours(($record->timeout - $record->timein), $record->courseid);
        echo $record->lastname.','.
            $record->firstname.','.
            $record->dept.','.
            userdate($record->timein, '%m/%d/%y').','.
            userdate($record->timein, '%H:%M').','.
            userdate($record->timeout, '%m/%d/%y').','.
            userdate($record->timeout, '%H:%M').','.
            $hours.','.
            $record->payrate.','.
            number_format($record->payrate * $hours, 2).',';
            if($record->submitted){
                echo "submitted\n";
            } else {
                echo "NOT submitted\n";
            }
    }

}
