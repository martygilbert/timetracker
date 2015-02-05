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

defined('MOODLE_INTERNAL') || die();
include_once('lib.php');

function get_workunit_popupinfo_fromid($unitid){
    $workunit = $DB->get_record('block_timetracker_workunig',
        array('id'=>$unitid));
    return get_workunit_popupinfo($workunit);
}

function get_workunit_popupinfo($unit){
    if(!$unit)
        return 'Unit does not exist';
    
    //get the status - certified or just submitted?
    if($unit->timesheetid && !$unit->submitted){ 
        $status = 'Awaiting Certification';
    } else if ($unit->timesheetid && $unit->submitted){
        $status = 'Certified';
    }
    $hours = get_hours($unit->timeout-$unit->timein, $unit->courseid);
    $output = '<h3>'.$status.'</h3>';
    $output .= '<ul style="text-align: left;">'.
        '<li><b>Hours:</b>&nbsp;'.$hours.'</li>'. 
        '<li><b>Pay rate:</b> $'.$unit->payrate.'</li>'. 
        '<li><b>Pay:</b> $'.round($unit->payrate * $hours, 2).'</li>';
        if($unit->lastedited){
            $output .= 
                '<li><b>Last edited: </b>'.
                    userdate($unit->lastedited,
                    get_string('datetimeformat', 'block_timetracker')).'</li>';
        } 

        $output .= '</ul>'; 
        
    return $output;
}

function get_popup_css(){
    $output = '
    <style type="text/css">
    span.dropt {
    }
    span.dropt:hover {
        text-decoration: none; 
        background: #ffffff; 
        z-index: 6; 
    }
    span.dropt span {
        position: absolute; 
        left: -9999px;
        margin: 20px 0 0 0px; 
        border-style: solid;
        border-color: black;
        border-width: 1px;
        padding: 3px 3px 3px 3px;
        z-index: 6;
    }
    span.dropt:hover span {
        left: 2%; 
        background: #ffffff;
    } 
    span.dropt:hover span {
        margin-left: 70%;
        background: #ffc125; 
        z-index:6;
    } 
    </style>'; 
    return $output;
}

