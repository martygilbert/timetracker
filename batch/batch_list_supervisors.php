<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');

/**
 The purpose of this script is to list the supervisors in a category
*/
global $CFG, $DB, $USER;

//Work-Study
//$courses = get_courses(2, 'fullname ASC', 'c.id,c.shortname');

//Departmental
$courses = get_courses(4, 'fullname ASC', 'c.id,c.shortname');

//Bi-Weekly
//$courses = get_courses(5, 'fullname ASC', 'c.id,c.shortname');

foreach($courses as $course){

    $context = get_context_instance(CONTEXT_COURSE, $course->id);
    $supervisors = get_enrolled_users($context, 'block/timetracker:manageworkers');

    foreach ($supervisors as $supervisor){
        echo $course->shortname.','.$supervisor->lastname.','.
            $supervisor->firstname.','.strtolower($supervisor->email)."\n";
    }
    

}
