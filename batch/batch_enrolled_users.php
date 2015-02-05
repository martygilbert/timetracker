<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to find enrolled users in WS category
*/
global $CFG, $DB, $USER;

// 2 is Work-Study
$courses = get_courses(2, 'fullname ASC', 'c.id, c.shortname');

// 4 is  Departmental
//$courses = get_courses(4, 'fullname ASC', 'c.id, c.shortname');

// 5 is  Bi-Weekly
//$courses = get_courses(5, 'fullname ASC', 'c.id, c.shortname');

foreach($courses as $course){
    $id = $course->id;

    $users = $DB->get_records('block_timetracker_workerinfo', array('courseid'=>$id));

    foreach($users as $user){
        echo 
            '"'.$user->lastname.'",'.
            '"'.$user->firstname.'",'.
            '"'.$user->email.'",'.
            #'"'.$user->budget.'",'.
            #'"'.$user->active.'",'.
            #'"'.$user->idnum.'",'.
            #'"'.$user->currpayrate.'",'.
            #'"'.$course->shortname.'"'."\n";
            "\n";
    }

}
