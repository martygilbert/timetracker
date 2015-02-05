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
 * This form will allow the worker to sign a timesheet electronically.
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
require('timetracker_workersig_form.php');

require_login();

$courseid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$start = required_param('start', PARAM_INT);
$end = required_param('end', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

$canmanage = false;
if (has_capability('block/timetracker:manageworkers', $context)) { //supervisor
    $canmanage = true;
}

$thisurl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/workersig.php',
    array('id'=>$courseid, 'userid'=>$userid, 'start'=>$start, 'end'=>$end));

$urlparams['id'] = $courseid;
$urlparams['userid'] = $userid;
$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php',$urlparams);

$worker = $DB->get_record('block_timetracker_workerinfo', 
    array('id'=>$userid,'courseid'=>$courseid));

$PAGE->set_url($thisurl);
$PAGE->set_pagelayout('base');

$strtitle = get_string('signtsheading','block_timetracker'); 
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('pluginname','block_timetracker'), $index);
$PAGE->navbar->add($strtitle);

if($canmanage){
    print_error('supsignerror','block_timetracker');
}
if(!$worker){
    echo 'This worker does not exist in the database.';
} else if($worker->mdluserid != $USER->id){
    print_error('notpermissible','block_timetracker',
        $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$courseid);
} else {
    $hasalerts = has_course_alerts($COURSE->id, $worker->id);
    
    if($hasalerts){

        $redirectparams['id'] = $courseid;
        $redirectparams['userid'] = $userid;
        $redirecturl = new moodle_url('/blocks/timetracker/viewtimesheets.php', 
            $redirectparams);
        $alertsurl = new moodle_url('/blocks/timetracker/managealerts.php',
            $redirectparams);
            
        $status = '<h2 style="color: red">'.
        'You are unable to sign a timesheet if alerts exist.</h2>'.
        '<br /><strong>Please contact your supervisor and ask them to handle these '.
        'alerts. </strong><br/><br />'.
        $OUTPUT->action_link($alertsurl, 'View your pending alerts ('.
        $hasalerts.')').'<br /><br />';

        redirect($redirecturl, $status, 200);
    }

    $numunits = $DB->count_records_select('block_timetracker_workunit',
        '((timein BETWEEN '.$start.' AND '.$end.') OR (timeout BETWEEN '.$start.
        ' AND '.$end.')) AND userid='.$userid.' AND courseid='.
        $courseid.' AND timesheetid=0');

    if($numunits){
        $mform = new timetracker_workersig_form($courseid, $userid, $start, $end);

        if ($mform->is_cancelled()){ //user clicked cancel
            //redirect($nextpage);
            redirect($index);
    
        } else if ($formdata=$mform->get_data()){
            /*
                Look for units that straddle the pay period boundary
                TODO Check for course conflicts against submitted work units
                Create timesheet entry
                Assign all of the work units the timesheet id
                Set all of the work units canedit=0
            */
            
            split_boundary_units($start, $end, $userid, $courseid);
       
            $sql = 'SELECT * FROM '.$CFG->prefix.
                'block_timetracker_workunit WHERE timein BETWEEN '.
                $start.' AND '.$end.' AND timeout BETWEEN '.$start.' AND '.$end.' AND userid='.
                $userid.' AND courseid='.$courseid.' AND timesheetid=0 ORDER BY timein';
            
            $units = $DB->get_records_sql($sql);
            
            if($units){
    
                $earnings = break_down_earnings($units);
    
                if(($earnings['regearnings'] + $earnings['ovtearnings']) > 0){
                
                    //Create entry in timesheet table
                    $newtimesheet = new stdClass();
                    $newtimesheet->userid = $userid;
                    $newtimesheet->courseid = $courseid;
                    $newtimesheet->submitted = 0;
                    $newtimesheet->workersignature = time();
                    $newtimesheet->reghours = $earnings['reghours'];
                    $newtimesheet->regpay = $earnings['regearnings'];
                    $newtimesheet->othours = $earnings['ovthours'];
                    $newtimesheet->otpay = $earnings['ovtearnings'];
                    
    
                    /*
                    error_log("Reg: ".$newtimesheet->regpay);
                    error_log("Ovt: ".$newtimesheet->otpay);
                    error_log("RGH: ".$newtimesheet->reghours);
                    error_log("OTH: ".$newtimesheet->othours);
                    */

            
                    $timesheetid = $DB->insert_record('block_timetracker_timesheet', 
                        $newtimesheet);
                    
                    foreach ($units as $unit){
                        $unit->timesheetid = $timesheetid; 
                        $unit->canedit = 0;
                        $result = $DB->update_record('block_timetracker_workunit', $unit);    
    
                        if(!$result){
                            error_log("workersig.php - ERROR updating unit w/id: $unit->id");
                        }
                    }
    
                    //Feature 181 - Send email to supervisor(s) upon signing
    
                    //find the course supervisor(s), first. If none, don't try.
                    $supers = get_enrolled_users($context,
                        'mod/assignment:grade'); //supervisors
                    if($supers){
                        //construct a simple message.
                        $subject = 'Timesheet has been signed by '.$worker->firstname.
                            ' '.$worker->lastname;
                        $from = $DB->get_record('user', array('id'=>$worker->mdluserid));
                        $messagebody='<p>I have just signed my timesheet and it is now
                            awaiting your approval.</p><p>Please visit <a href="'.
                            $CFG->wwwroot.'/blocks/timetracker/supervisorsig.php?id='.
                            $courseid.'">this link</a> to approve/reject my hours at your
                            earliest convenience.</p><p>Thank you,</p>'.$worker->firstname.
                            ' '.$worker->lastname.'<br />'.$worker->email;
    
                        //email them a message
                        foreach($supers as $super){
                            $messagebody = 'Hello '.$super->firstname.'
                            '.$super->lastname.',<br /><br />'.$messagebody;
    
                            $mailok = email_to_user($super, $from, $subject,
                                format_text_email($messagebody, FORMAT_HTML),
                                $messagebody);
                            if(!$mailok){
                                error_log('error sending mail to '.
                                    $super->firstname.' '.$super->lastname.
                                    ' to notify them of a signed timesheet');
                            }
                        }
                    }
                    // done w/Feature 181 - Send email to supervisor(s) upon signing
    
    

                $status = 'You have successfully signed the official timesheet.';
                } else { // no pay for these units
                    $status = 'There are no earnings for you to sign, given the date range.';
                }
            } else {
                $status = 'There are no units to sign for the given date range.';
            }
            $redirectparams['id'] = $courseid;
            $redirectparams['userid'] = $userid;
            $redirecturl = new moodle_url('/blocks/timetracker/viewtimesheets.php?', 
                $redirectparams);
            redirect($redirecturl, $status, 2);
        } else {
            //form is shown for the first time
            echo $OUTPUT->header();
            $mform->display();
            echo $OUTPUT->footer();
        }
    } else { //no units
        $status = 'There are no units to sign for the given date range.';
        $redirectparams['id'] = $courseid;
        $redirectparams['userid'] = $userid;
        $redirecturl = new moodle_url('/blocks/timetracker/viewtimesheets.php?', 
            $redirectparams);
        redirect($redirecturl, $status, 2);
    }
}
