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
require_once('timetracker_managealerts_form.php');

require_login();

$courseid = required_param('id', PARAM_INT);


if($courseid){
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $PAGE->set_course($course);
    $context = $PAGE->context;
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
    $PAGE->set_context($context);
}

$urlparams['id'] = $courseid;
//$urlparams['userid'] = $

$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php', $urlparams);

/*
if(isset($_SERVER['HTTP_REFERER'])){
    $nextpage = $_SERVER['HTTP_REFERER'];
} else {
    $nextpage = $index;
}
*/

$alertsurl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/managealerts.php', $urlparams);
$nextpage = $alertsurl;


$strtitle = get_string('managealerts','block_timetracker');

$PAGE->set_url($alertsurl);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

$PAGE->navbar->add(get_string('pluginname', 'block_timetracker'), $index);
$PAGE->navbar->add($strtitle);

$mform = new timetracker_managealerts_form($PAGE->context);

if ($mform->is_cancelled()){ //user clicked 'cancel'

    // this seems to send a courseID of 0 to index.php, when, as best I can tell
    // $urlparams has the correct id. TODO
    redirect($nextpage); 
} else if($formdata = $mform->get_data()){

} else {
    $canview = false;
    if(has_capability('block/timetracker:viewonly', $context)){
        $canview = true;
    }
    echo $OUTPUT->header();
    $maintabs = get_tabs($urlparams, $canview, $courseid);
    $tabs = array($maintabs);
    print_tabs($tabs, 'alerts');
    //echo $OUTPUT->heading($strtitle, 2);
    #$PAGE->print_header('Manage worker info', 'Manage worker info');
    $mform->display();
    echo $OUTPUT->footer();
}

