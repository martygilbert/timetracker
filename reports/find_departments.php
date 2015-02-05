<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to find earnings/max earnings for this term
*/
global $CFG, $DB, $USER;


$courses = get_courses(2, 'fullname ASC', 'c.id,c.shortname');


foreach($courses as $course){
    
    $context = get_context_instance(CONTEXT_COURSE, $course->id);

    $supervisors = get_enrolled_users($context,
        'block/timetracker:manageworkers');

    $list = $course->shortname;
    if($supervisors){
        foreach ($supervisors as $supervisor){
            $list .= ",".$supervisor->lastname." ".$supervisor->firstname;
        }
    }

    echo $list."\n";


}
