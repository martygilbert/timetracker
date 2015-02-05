<?php

define('CLI_SCRIPT', true);
require_once('../../../config.php');
require_once('../lib.php');

/**
 The purpose of this script is to update a user's fileid based on their email address
*/
global $CFG, $DB, $USER;

$CATEGORY=2;
$FILE='/tmp/newIDs.csv';
/**
* FILE FORMAT: fileid,MHU email
*/

$courses = get_courses($CATEGORY, 'fullname ASC', 'c.id, c.shortname');

//foreach($workers as $worker){
if(($handle = fopen($FILE, "r")) !== FALSE){
    while(($data = fgetcsv($handle, 1000, ",")) !== FALSE){

        $fileid = $data[0];  //student ID field
        $mhuid = strtolower($data[1]);
        $email = $mhuid.'@mhu.edu';
        //$email = strtolower($data[1]);
  
        $workers = $DB->get_records('block_timetracker_workerinfo', array('email'=>$email));
    
        if(!$workers){
            error_log("No record for $email\n");
            echo "No record for $email\n";
        }

        foreach($workers as $worker){

            $course = $courses[$worker->courseid];
            if(!$course){ //not a course in this category;
                continue;
            }
            //echo "checking $worker->idnum $worker->firstname $worker->lastname\n";

            if($fileid != $worker->idnum){
                //echo "******updating $worker->idnum $worker->firstname $worker->lastname from $worker->idnum to $fileid\n";
                $worker->idnum = $fileid; 
                
                $result = $DB->update_record('block_timetracker_workerinfo', $worker);

                if(!$result){
                    error_log("Error updating $worker->id $worker->firstname
                        $worker->lastname");
                }

            }

        }
    }
}
