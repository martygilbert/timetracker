<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This block will display a summary of hours and earnings for the worker.
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');


require_login();

$courseid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$urlparams['id'] = $courseid;
$urlparams['userid'] = $userid;

if($courseid){
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    #print_object($course);
    $PAGE->set_course($course);
    $context = $PAGE->context;
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
    $PAGE->set_context($context);
}


$manageworkerurl = new moodle_url($CFG->wwwroot.
    '/blocks/timetracker/manageworkers.php', $urlparams);

//$PAGE->set_url($manageworkerurl);
//$PAGE->set_pagelayout('base');

//$strtitle = get_string('manageworkertitle','block_timetracker');

//$PAGE->set_title($strtitle);
//$PAGE->set_heading($strtitle);

#print_object($urlparams);
$timetrackerurl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php',$urlparams);

//$PAGE->navbar->add(get_string('blocks'));
//$PAGE->navbar->add(get_string('pluginname', 'block_timetracker'), $timetrackerurl);
//$PAGE->navbar->add($strtitle);

//echo $OUTPUT->header();
//echo $OUTPUT->heading($strtitle, 2);


//$PAGE->print_header('Delete TimeTracker Worker', 'Delete Worker');

if (!has_capability('block/timetracker:manageworkers', $context)) {
    print_error('notpermissible','block_timetracker',
        $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$COURSE->id);
} else {
    if($userid && confirm_sesskey()){

        //used to remove all traces - now, let's just set deleted=1
        $user = $DB->get_record('block_timetracker_workerinfo',
            array('id'=>$userid));
        if(!$user){
            print_error('user does not exist');
        } else {
            $user->deleted=1;
            $user->active=0;
            $DB->update_record('block_timetracker_workerinfo', $user);
        }

        //also, let's delete pending records
        $DB->delete_records('block_timetracker_pending',array('userid'=>$userid,
            'courseid'=>$courseid));    

        //and the alerts
        $alerts = $DB->get_records('block_timetracker_alertunits', 
            array('userid'=>$userid));
        if($alerts){

            foreach($alerts as $alert){
                $DB->delete_records(
                    'block_timetracker_alert_com',array('alertid'=>$alert->id));    
                $DB->delete_records(
                    'block_timetracker_alertunits',array('id'=>$alert->id));    
            }
        }

        //next, we need to un-enroll them from the course
        unenroll_worker($courseid, $user->mdluserid);
        
        //should be done, no?

        /*
        //purge them from the db
        //print_object($userid);
        $DB->delete_records('block_timetracker_workerinfo',array('id'=>$userid));    
        $DB->delete_records('block_timetracker_workunit',array('userid'=>$userid,
            'courseid'=>$courseid));    
        $DB->delete_records('block_timetracker_pending',array('userid'=>$userid,
            'courseid'=>$courseid));    
        $alerts = $DB->get_records('block_timetracker_alertunits', 
            array('userid'=>$userid));
        if($alerts){

            foreach($alerts as $alert){
                $DB->delete_records(
                    'block_timetracker_alert_com',array('alertid'=>$alert->id));    
                $DB->delete_records(
                    'block_timetracker_alertunits',array('id'=>$alert->id));    
            }
        }
        */

        redirect($manageworkerurl, 'Worker deleted successfully', 2);

    } else {
        print_error('errordeleting','block_timetracker', 
            $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$COURSE->id);
    }
}

//echo $OUTPUT->footer();
