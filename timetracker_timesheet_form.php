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
 * This form will call for the timesheet to be generated. 
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once ($CFG->libdir.'/formslib.php');
require_once ('lib.php');

class timetracker_timesheet_form  extends moodleform {

    function timetracker_timesheet_form($context, $userid=-1){
        $this->context = $context;
        $this->userid = $userid;
        parent::__construct();
    }

    function definition() {
        global $CFG, $USER, $DB, $COURSE;
        $mform =& $this->_form; // Don't forget the underscore! 

        $canmanage = false;
        if (has_capability('block/timetracker:manageworkers', $this->context)) {
            $canmanage = true;
        }

        $canview = false;
        if (has_capability('block/timetracker:viewonly', $this->context)) {
            $canview = true;
        }
        
        $mform->addElement('header','general','Generate Timesheet');

        // Collect all of the workers under the supervisor

        $mform->addElement('hidden','id',$COURSE->id);    
        $mform->setType('id', PARAM_INT);

        if($canmanage || $canview) {
            $workerlist = array();
            $workers =
                $DB->get_records('block_timetracker_workerinfo',
                array('courseid'=>$COURSE->id,'deleted'=>0),
                'lastname ASC');
            foreach($workers as $worker){
                $workerlist[$worker->id] = $worker->firstname.' '.$worker->lastname;
            }
            $select = &$mform->addElement('select','workerid',
                get_string('workerid','block_timetracker'), $workerlist, 'size="5"');
            $select->setMultiple(true);
            $mform->addHelpButton('workerid','workerid','block_timetracker');
            $mform->addRule('workerid', null, 'required', null, 'client', 'false');
            if($this->userid > 0){
                $mform->setDefault('workerid', $this->userid);
            }
        } else {
            $worker =
                $DB->get_record('block_timetracker_workerinfo',array('mdluserid'=>$USER->id,
                'courseid'=>$COURSE->id));
            if(!$worker){
                print_error('Worker does not exist.');        
            }
            $mform->addElement('hidden','workerid',$worker->id);    
            $mform->setType('workerid', PARAM_INT);
        }

        $mform->addElement('checkbox', 'entiremonth', 'Entire month?');
        //A bit of a hack. If biweekly is in the shortname, default to the past two weeks
        if(preg_match_all('/biweekly/i', $COURSE->shortname)) {
            $mform->setDefault('entiremonth', false);
        } else {
            $mform->setDefault('entiremonth', true);
        }

        $months = array(
            1 =>'January',
            2=>'February',
            3=>'March',
            4=>'April',
            5=>'May',
            6=>'June',
            7=>'July',
            8=>'August',
            9=>'September',
            10=>'October',
            11=>'November',
            12=>'December');

        $mform->addElement('select', 'month', 
            get_string('month','block_timetracker'), $months);
        $mform->disabledIf('month', 'entiremonth');

        //the month 5 days ago
        $mform->setDefault('month', date("m", time() - (5 * 86400))); 
        $mform->addHelpButton('month','month','block_timetracker');

        $sql = 'SELECT timein FROM '.$CFG->prefix.
            'block_timetracker_workunit ORDER BY timein LIMIT 1';
        $earliestyear = $DB->get_record_sql($sql);


        if(!$earliestyear) $earliestyear = date("Y"); 
        else $earliestyear = date("Y", $earliestyear->timein);
        
        $years = array();
        foreach(range($earliestyear,date("Y")) as $year){
            $years[$year] = $year;
        }
        if(empty($years)) $years[date("Y")] = date("Y");

        $mform->addElement('select', 'year', get_string('year','block_timetracker'), $years);
        $mform->disabledIf('year', 'entiremonth');
        $mform->addHelpButton('year','year','block_timetracker');
        $mform->setDefault('year', date("Y"));


        $today = time();
        $currday = userdate($today, "%u");
        //error_log($currday);

        //1 is Monday - 7 is Sunday
        if($currday == 7) { //sunday
            $sunday = $today;
        } else if($currday > 1){//Tuesday - Saturday
            $sunday = strtotime('Sunday this week', $today);
        } else {//Monday - they turn in timesheets by noon.
            $sunday = strtotime('last Sunday', $today);
        }

        //2 mondays ago
        //$monday = strtotime('+1 day', strtotime('-2 weeks', $sunday));
        $monday = strtotime('-2 mondays', $sunday);

        $mform->addElement('date_selector', 'startday', 'Start day'); 
        $mform->setDefault('startday', $monday);
        $mform->disabledIf('startday', 'entiremonth', 'checked');

        $mform->addElement('date_selector', 'endday', 'End day'); 
        $mform->setDefault('endday', $sunday);
        $mform->disabledIf('endday', 'entiremonth', 'checked');

        /*
        if($canmanage){
            // Show File Format Dropdown
            $formats = array(
                'pdf' => 'PDF',
                'xls' => 'XLS');
            $mform->addElement('select', 'fileformat', 
                get_string('fileformat','block_timetracker'), $formats);
            $mform->addHelpButton('fileformat','fileformat','block_timetracker');
        } else {
            */
            $mform->addElement('hidden', 'fileformat', 'pdf');    
            $mform->setType('fileformat', PARAM_ALPHA);
            /*
        }
        */

        //$this->add_action_buttons(false, get_string('generatebutton','block_timetracker'));
        //normally you use add_action_buttons instead of this code
        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 
            'unofficial', 'Generate unofficial timesheet');

        //only let workers begin the official timesheet data submission process
        if(!$canmanage && !$canview){ 
            $buttonarray[] = &$mform->createElement('submit', 'official', 
                'Submit official timesheet');
        } 
        //$buttonarray[] = &$mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        //$mform->closeHeaderBefore('buttonar');
    }

    function validation($data, $files){
        $errors = array();
        
        if(!isset($data['entiremonth'])){
            if($data['endday'] < $data['startday']){
                $errors['startday'] = 'Start day cannot be after end day';
            }

        }


        return $errors;
    }
}
?>
