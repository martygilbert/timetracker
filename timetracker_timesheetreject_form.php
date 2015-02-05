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
 * This form will allow the worker to submit an alert and correction to the supervisor of an error in a work unit.
 * The supervisor will be able to approve or deny the correction.
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once ($CFG->libdir.'/formslib.php');
require_once ('lib.php');

class timetracker_timesheetreject_form  extends moodleform {

    function timetracker_timesheetreject_form($timesheetid){
        
        $this->timesheetid = $timesheetid;
        parent::__construct();
    }

    function definition() {
        global $CFG, $USER, $DB, $COURSE;

        $mform =& $this->_form; // Don't forget the underscore! 
        
        $timesheet = $DB->get_record('block_timetracker_timesheet', 
            array('id'=>$this->timesheetid));
        $courseid = $timesheet->courseid;
        $userid = $timesheet->userid;

        $userinfo = $DB->get_record('block_timetracker_workerinfo',
            array('id'=>$userid));
        
        if(!$userinfo){
            print_error('Worker info does not exist for workerinfo id of '.$this->userid);
            return;
        }

        $index  = new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php',
            array('id'=>$courseid,'userid'=>$userid));

        if(get_referer(false)){
            $nextpage = get_referer(false);
        } else {
            $nextpage = $index;
        }

        $mform->addElement('hidden', 'timesheetid', $this->timesheetid);
        $mform->setType('timesheetid', PARAM_INT);
        $mform->addElement('html',get_string('headername','block_timetracker', 
            $userinfo->firstname.' '.$userinfo->lastname));
        $mform->addElement('html',get_string('headertimestamp','block_timetracker', 
            date("n/j/Y g:i:sa", $timesheet->workersignature)));
        $mform->addElement('html','<br /><br />');
        $mform->addElement('textarea', 'message', 
            get_string('rejectreason','block_timetracker'), 
            'wrap="virtual" rows="3" cols="75"');
        $mform->addRule('message', null, 'required', null, 'client', 'false');
        $mform->addElement('html', '</b>'); 
        $this->add_action_buttons(true,get_string('sendbutton','block_timetracker'));
    }   
    
    function validation ($data, $files){
        global $OUTPUT;
        $errors = array();

        return $errors;
    }
}
