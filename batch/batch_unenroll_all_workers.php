<?php


define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

global $CFG, $DB, $USER;

/**
    The purpose of this script is to un-enroll all workers from a category
    And remove the associated data.
*/

$CATID=2; //work-study is 2


$count = 0;
$courses = get_courses($CATID, 'fullname ASC', 'c.id, c.shortname');

if(!$courses){
    echo "No courses exist for $catid";
}


$str = implode (',', array_keys($courses));

$sql = 'SELECT * from mdl_block_timetracker_workerinfo WHERE courseid in ('.$str.')';


$workers = $DB->get_records_sql($sql);
$count = 0;

echo "Workers to process: ".sizeof($workers)."\n";

if(!$workers){
    echo "Error getting records.";
    echo "$sql";
} else {
    foreach ($workers as $worker){

        //get all of the alert units for this user
        $alerts = $DB->get_records('block_timetracker_alertunits',
            array('userid'=>$worker->id));

        //delete the alertcom
        foreach($alerts as $alert){
            $DB->delete_records('block_timetracker_alert_com',
                array('alertid'=>$alert->id));
        }

        //delete all alerts
        $DB->delete_records('block_timetracker_alertunits',
            array('userid'=>$worker->id));

        //delete all of the work units
        $DB->delete_records('block_timetracker_workunit', 
            array('userid'=>$worker->id, 'courseid'=>$worker->courseid));

        //delete all of the pending work units
        $DB->delete_records('block_timetracker_pending', 
            array('userid'=>$worker->id, 'courseid'=>$worker->courseid));

        //delete from timesheets
        $DB->delete_records('block_timetracker_timesheet', 
            array('userid'=>$worker->id, 'courseid'=>$worker->courseid));

        //delete from workerinfo
        $DB->delete_records('block_timetracker_workerinfo', 
            array('id'=>$worker->id, 'courseid'=>$worker->courseid));

        //unenroll worker from course
        if(unenroll_worker($worker->courseid, $worker->mdluserid)){
            $count++;
        } else {
            echo 
            "Error un-enrolling $worker->firstname $worker->lastname from $worker->courseid\n";
        }

    }
}

echo "Processed $count workers\n";
