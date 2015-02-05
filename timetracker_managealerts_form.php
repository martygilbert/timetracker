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

require_once ($CFG->libdir.'/formslib.php');
require_once ('lib.php');

class timetracker_managealerts_form  extends moodleform {

    function timetracker_managealerts_form($context){
        $this->context = $context;
        parent::__construct();
    }


    function definition() {
        global $CFG, $USER, $DB, $COURSE, $OUTPUT;


        $mform =& $this->_form; // Don't forget the underscore! 
        $canmanage = false;
        if (has_capability('block/timetracker:manageworkers', $this->context)) {
            $canmanage = true;
        }

        $canview = false;
        if (has_capability('block/timetracker:viewonly', $this->context)) {
            $canview = true;
        }

        if($canview && !$canmanage){
            $urlparams['id'] = $COURSE->id;
            $nextpage = new moodle_url(
                $CFG->wwwroot.'/blocks/timetracker/index.php', $urlparams);
            redirect($nextpage, 
                'You do not have permission to view alerts for this course. <br />
                Redirecting you now.', 2);
        }

        $mform->addElement('header', 'general', 
            get_string('managealerts','block_timetracker')); 
		$mform->addHelpButton('general','managealerts','block_timetracker');


        $strname = get_string('workername', 'block_timetracker');
        $strprev = get_string('previous', 'block_timetracker');
        $strproposed = get_string('proposed', 'block_timetracker');
        $strmsg = get_string('message', 'block_timetracker');

        if(!has_course_alerts($COURSE->id)){
            $mform->addElement('html','<div style="text-align:center">');
            $mform->addElement('html',get_string('noalerts','block_timetracker'));
            $mform->addElement('html','</div>'); 
            return;
        } else {

            $mform->addElement('html', 
            '<br /><table align="center" border="1" cellspacing="10px" '.
            'cellpadding="5px" width="95%">');
        
            $tblheaders=
                '<tr>
                    <td><div style="font-weight: bold; width: 120px;">'.$strname.'</div></td>
                    <td><div style="font-weight: bold; width: 140px;">'.$strprev.'</div></td>
                    <td><div style="font-weight: bold; width: 140px;">'.$strproposed.'</div></td>
                    <td><div style="font-weight: bold; ">'.
                    $strmsg.'</span></td>';
            if($canmanage)
                $tblheaders .= '<td style="text-align: center">'.
                    '<div style="font-weight: bold; width: 100px">'.
                    get_string('action').'</div></td>';
            $tblheaders .= '</tr>';

            $mform->addElement('html',$tblheaders);


            $alertlinks=get_course_alert_links($COURSE->id);
            //print_object($alertlinks);
    
            if($canmanage){
                $alerts = $DB->get_records('block_timetracker_alertunits', 
                    array('courseid'=>$COURSE->id), 'alerttime');
            } else {
                $ttuserid = $DB->get_field('block_timetracker_workerinfo',
                    'id', array('mdluserid'=>$USER->id,'courseid'=>$COURSE->id));
                if(!$ttuserid) print_error('Error obtaining mdluserid from workerinfo for '.
                    $USER->id);
                $alerts = $DB->get_records('block_timetracker_alertunits',
                    array('courseid'=>$COURSE->id,'userid'=>$ttuserid));
            }
        }    

        foreach ($alerts as $alert){ 
            $worker = $DB->get_record('block_timetracker_workerinfo',
                array('id'=>$alert->userid));

            $mform->addElement('html','<tr>'); 
            $row ='<td>'.$worker->lastname.', '.$worker->firstname .
                '<br />Submitted:<br />'.
                userdate($alert->alerttime, get_string('datetimeformat',
                'block_timetracker')).
                '</td>';
            $row.='<td>'.userdate($alert->origtimein, 
                get_string('datetimeformat','block_timetracker'));

            if($alert->origtimeout > 0){
                $row.='<br />'.userdate($alert->origtimeout, 
                    get_string('datetimeformat','block_timetracker'));
                $row.='<br />Elapsed: '.get_hours(
                    $alert->origtimeout - $alert->origtimein, $alert->courseid).
                    ' hour(s)';
            } else {
                $row.= '';
            }
            $row .='</td>';

            if($alert->todelete == 0){
                $row.='<td>'.userdate($alert->timein, 
                    get_string('datetimeformat','block_timetracker'));

                $row.='<br />'.userdate($alert->timeout, 
                    get_string('datetimeformat','block_timetracker'));

                $row.='<br />Elapsed: '.get_hours(
                    $alert->timeout - $alert->timein, $alert->courseid).
                    ' hour(s)';

                $row.='</td>';
            } else {
                $row.='<td><span style="color: red">User requests removal</span></td>';
            }

            $row.='<td>'.nl2br($alert->message).'</td>';
    
            if($canmanage){

                $editurl = new moodle_url($alertlinks[$worker->id][$alert->id]['change']);
                $editaction = $OUTPUT->action_icon($editurl, new pix_icon('clock_edit', 
                    'Edit proposed work unit','block_timetracker'));
    
                $approveurl = new moodle_url($alertlinks[$worker->id][$alert->id]['approve']);
                $checkicon = new pix_icon('approve',
                    'Approve the proposed work unit','block_timetracker');
                if($alert->todelete){
                    $approveaction=$OUTPUT->action_icon($approveurl, $checkicon,
                    new confirm_action('Are you sure you want to delete this work unit
                    as requested by the worker?'));
                } else {
                    $approveaction=$OUTPUT->action_icon($approveurl, $checkicon);
                }

                $deleteurl = new moodle_url($alertlinks[$worker->id][$alert->id]['delete']);
                $deleteicon = new pix_icon('delete',
                    'Delete this alert', 'block_timetracker');
                $deleteaction = $OUTPUT->action_icon(
                    $deleteurl, $deleteicon, 
                    new confirm_action(
                    'Are you sure you want to delete this alert?'));
        
                $denyurl = new moodle_url($alertlinks[$worker->id][$alert->id]['deny']);
                $denyicon = new pix_icon('clock_delete',
                    'Deny and restore original work unit','block_timetracker');

                $denyaction = $OUTPUT->action_icon(
                    $denyurl, $denyicon, 
                    new confirm_action(
                    'Are you sure you want to deny this alert unit?<br />The work unit 
                    will be re-inserted into the worker\'s record as it originally
                    appeared.'));

                if($alert->origtimeout > 0){
                    $row .= '<td style="text-align: left">'.
                        $approveaction . ' ' . $deleteaction. ' '.
                        $editaction. ' '.$denyaction.'</td>';
                } else {
                    $row .= '<td style="text-align: left">'.
                        $approveaction . ' ' . $deleteaction. ' '.
                        $editaction.'</td>';

                }
            }
    
            $row.='</tr>';
            $mform->addElement('html',$row);
    
        }
    
        $mform->addElement('html','</table>');
    
        //$this->add_action_buttons(true, 'Save Changes');

        if($canmanage){
            $mform->addElement('header','general',
                'Alert action legend');
            $legend ='<br />
                <img src="'.$CFG->wwwroot.'/blocks/timetracker/pix/approve.png" />
                Approve the proposed work unit <br />
                <img src="'.$CFG->wwwroot.'/blocks/timetracker/pix/delete.png" />
                Delete the alert and the original/proposed work units <br />
                <img src="'.$CFG->wwwroot.'/blocks/timetracker/pix/clock_edit.png" />
                Edit the proposed work unit before approval<br />
                <img src="'.$CFG->wwwroot.'/blocks/timetracker/pix/clock_delete.png" />
                Deny the proposed work unit and re-add the original work unit <br /><br />
                <img src="'.$CFG->wwwroot.'/blocks/timetracker/pix/alert.gif" />
                <strong>ADMINISTRATIVE ALERT</strong><br /> 
                This is a work unit/clock-in that has been deemed questionable <br />
                due to its excessive duration and has been set to zero (0) hours.<br />
                Please confirm that this work unit is correct, or modify it appropriately';
            $mform->addElement('html',$legend);
        }
    
    }

}
