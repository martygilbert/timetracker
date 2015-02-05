<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to find official earnings for a list of IDs
 file format: <MHU User ID>,<MHU Email Addr>,<sport>
*/
global $CFG, $DB, $USER;


$courses = get_courses(2, 'fullname ASC', 'c.id, c.shortname');
$FILE='/tmp/athletes.csv';

if(($handle = fopen($FILE, "r")) !== FALSE){
    while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){

        $username   = $data[0];  //Moodle username
        $username   = strtolower($username);

        $email      = $data[1];  //student email field
        $email      = strtolower($email);

        $sport      = $data[2];


        $users = $DB->get_records('block_timetracker_workerinfo', array('email'=>$email));
    
        if(!$users){
            error_log("No record for $email\n");
        }
        foreach($users as $user){

            $course = $courses[$user->courseid];
            if(!$course){ //not a course in this category;
                continue;
            }

            $timesheets = $DB->get_records('block_timetracker_timesheet',
                array('userid'=>$user->id));

            foreach($timesheets as $timesheet){
                echo '"'.
                    $user->lastname     .'","'.
                    $user->firstname    .'","'.
                    $user->email        .'","'.
                    $course->shortname  .'","'.
                    $sport              .'","'.
                    $timesheet->regpay  .'","'.
                    $timesheet->otpay   .'"'.
                    "\n";

            }
        }
    }
}
