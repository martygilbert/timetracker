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
 * This page will allow a supevisor to input the date, time, and duration of a work unit for a
 * student.
 *
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once('../../config.php');
require('timetracker_addunit_form.php');

global $CFG, $COURSE, $USER;

require_login();

$courseid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$start = optional_param('start', 0, PARAM_INT);
$end = optional_param('end', 0, PARAM_INT);

$urlparams['id'] = $courseid;
$urlparams['userid'] = $userid;

//set up page URLs
$url = new moodle_url($CFG->wwwroot.'/blocks/timetracker/addunit.php', $urlparams);
$url->params(array('start'=>$end));
$url->params(array('end'=>$start));

$manage = new moodle_url($CFG->wwwroot.'/blocks/timetracker/manageworkers.php', $urlparams);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

$PAGE->set_url($url);
$PAGE->set_pagelayout('base');

$workerrecord = $DB->get_record('block_timetracker_workerinfo', 
    array('id'=>$userid,'courseid'=>$courseid));

if(!$workerrecord){
    print_error("No worker found.");
    die;
}

$canmanage = false;
if (has_capability('block/timetracker:manageworkers', $context)) { //supervisor
    $canmanage = true;
}

$strtitle = get_string('addunittitle','block_timetracker',
    $workerrecord->firstname.' '.$workerrecord->lastname); 

$nextpage = $manage;

if(get_referer(false)){
    $nextpage = new moodle_url(get_referer(false));
} else {
    $nextpage = $manage;
}

//if we posted to ourself from ourself
if(strpos($nextpage, qualified_me()) !== false){
    $nextpage = new moodle_url($SESSION->lastpage);
} else {
    $SESSION->lastpage = $nextpage;
}

if (isset($SESSION->fromurl) &&
    !empty($SESSION->fromurl)){
    $nextpage = new moodle_url($SESSION->fromurl);
    unset($SESSION->fromurl);
}

$nextpage->params(array('id'=>$courseid, 'userid'=>$userid));

$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->navbar->add(get_string('blocks'));
$PAGE->navbar->add(get_string('pluginname','block_timetracker'), $manage);
$PAGE->navbar->add($strtitle);

$mform = new timetracker_addunit_form($context, $userid, $courseid,
    $start, $end);

if($workerrecord->active == 0){
    echo $OUTPUT->header();
    print_string('notactiveerror','block_timetracker');
    echo '<br />';
    echo $OUTPUT->footer();
    die;
}

if ($mform->is_cancelled()){ //user clicked cancel
	 redirect($manage);

} else if ($formdata=$mform->get_data()){
    $formdata->courseid = $formdata->id;
    unset($formdata->id);
    //$formdata->payrate = $workerrecord->currpayrate;
    $formdata->lastedited = time();
    $formdata->lasteditedby = $formdata->editedby;
    $result = add_unit($formdata, true);

    if($result) {
        $status = 'Work unit(s) added successfully.'; 
    } else {
        $status = 'Error adding work unit(s)';
    }

    redirect($nextpage, $status, 1);

} else {
    //form is shown for the first time
    echo $OUTPUT->header();
    $tabs = get_tabs($urlparams, $canmanage, $courseid);
    $tabs[] = new tabobject('addunit',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php#', $urlparams),
        'Add Work Unit');
    
    $tabs = array($tabs);
    print_tabs($tabs, 'addunit');

    $mform->display();
    echo $OUTPUT->footer();
}
