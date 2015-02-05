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
// You should have received a copy of the GNU General Public License // along with Moodle.  If not, see <http://www.gnu.org/licenses/>.  
/**
 * This block will display a summary of hours and earnings for the worker.
 *
 * @package    TimeTracker
 * @copyright  Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');

$courseid = 73;
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

global $DB, $CFG, $COURSE;

$strtitle = 'Test Page';

$index = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$courseid));

$PAGE->set_url($index);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

echo $OUTPUT->header();
//$html = $OUTPUT->action_link($index, 'Back to course page');
//$startday = 1359262800; //sunday 00:00:00
$startday = 1361835369; //Monday 02/25/13 @ 6:36pm
//$startday = 1361748969; //Sunday 02/24/13 @ 6:36pm
//$startday = 1359349200; //monday 00:00:00
//$startday = 1357448400; //01/06 00:00:00

//$sundayend = strtotime("last Monday", strtotime('tomorrow', $startday)) ;
//$sundayend = strtotime("Sunday this week", $startday);
$sundayend = strtotime("last Sunday", $startday);



$html = userdate($startday, "%D %H:%M:%S");
$html .= $sundayend . "<br />";
$html .= userdate($sundayend, "%D %H:%M:%S");

$monthinfo = get_month_info(7,2012);
$html .= '<br /><br />'.$monthinfo['dayofweek'];
    
echo $html;

echo $OUTPUT->footer();
