<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to find enrolled users in WS category
*/
global $CFG, $DB, $USER;

$wscourses = get_courses(2, 'fullname ASC', 'c.id, c.shortname');

$wsworkers = array();

$str = implode (',', array_keys($wscourses));

$wsusers = $DB->get_records_select('block_timetracker_workerinfo', 
    'courseid in ('.$str.')', null, 'email ASC');

foreach($wsusers as $user){
    $wsworkers[strtolower($user->email)] = $user;
}

$deptcourses = get_courses(4, 'fullname ASC', 'c.id, c.shortname');
$str = implode (',', array_keys($deptcourses));

$deptusers = $DB->get_records_select('block_timetracker_workerinfo', 
    'courseid in ('.$str.')', null, 'email ASC');

foreach($deptusers as $user){
    if(array_key_exists(strtolower($user->email),$wsworkers)){
        //if in work-study, print out each deptartmental and workstudy
        print_worker($user);
        echo ','.$deptcourses[$user->courseid]->shortname."\n";
        print_worker($wsworkers[strtolower($user->email)]);
        echo ','.$wscourses[$wsworkers[strtolower($user->email)]->courseid]->shortname."\n";
    }
}

function print_worker($worker){
    $str = $worker->idnum.','.
        $worker->lastname.','.
        $worker->firstname;
    echo $str;
}
