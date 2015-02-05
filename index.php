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
require_once($CFG->libdir . '/tablelib.php');
require_once('lib.php');

require_login();

$courseid = required_param('id', PARAM_INT);
//$userid = optional_param('userid',0, PARAM_INT);

$urlparams['id'] = $courseid;

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

$canmanage = false;
if (has_capability('block/timetracker:manageworkers', $context)) { //supervisor
    $canmanage = true;
    $urlparams['userid']=0;
}

$canview = false;
if (has_capability('block/timetracker:viewonly', $context)) {
    $canview = true;
    $urlparams['userid']=0;
}

$worker = $DB->get_record('block_timetracker_workerinfo',array('mdluserid'=>$USER->id, 
    'courseid'=>$course->id));


if(!$canmanage && !$canview && !$worker){
    print_error('usernotexist', 'block_timetracker',
        $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$course->id);
}

if(!($canmanage || $canview) && $USER->id != $worker->mdluserid){
    print_error('notpermissible', 'block_timetracker',
        $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$course->id);
}
if($worker){
    $urlparams['userid'] = $worker->id;
    $userid = $worker->id;
}

$now = time();
$currdateinfo = usergetdate($now);

$index = new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php', $urlparams);

$strtitle = get_string('pluginname','block_timetracker');

$PAGE->set_url($index);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->set_pagelayout('base');

echo $OUTPUT->header();


$tabs = get_tabs($urlparams, $canview, $courseid);
$tabs = array($tabs);
print_tabs($tabs, 'home');


if ($canmanage || $canview) { //supervisor

    //workers that DO have max term earning
    $workers = get_workers_stats($courseid, false, 1);

    if($workers){

        //now print out roster
        $reporturl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/reports.php');

        $content = '';
        //$content .= $OUTPUT->box_start('generalbox boxaligncenter');
    
        $content .= '<br /><table align="center" 
                    width="95%" style="border: 1px solid #000;">';
        $content .= '<tr><th colspan="7">Worker list</th></tr>'."\n";
        $content .= '<tr>
                <td style="font-weight: bold"><br />Name</td>
                <td style="font-weight: bold; text-align: right"><br />Last unit</td>
                <td style="font-weight: bold; text-align: center"><br />Rate</td>
                <td style="font-weight: bold; text-align: center">Max term<br />'.
                'earnings</td>
                <td style="font-weight: bold; text-align: right">Term earnings<br />'.
                'processed / total</td>
                <td style="font-weight: bold; text-align: right">Hours remaining<br />'.
                'to process / to earn</td>
                <td style="font-weight: bold; text-align: right">Lifetime earnings<br />'.
                'processed / total</td>
                </tr>
            ';
        
        foreach($workers as $worker){

            $reporturl->params(array('id'=>$worker->courseid, 
                'userid'=>$worker->id));

            $html = '<tr>';
            $html .= '<td>'.
                $OUTPUT->action_link($reporturl, $worker->lastname.', '.$worker->firstname)
                .'</td>';

            $html .= '<td style="text-align: right">'.$worker->lastunit.'</td>';

            $html .= '<td style="text-align: center">$'.
                number_format($worker->currpayrate, 2).'</td>';

            $html .= '<td style="text-align: center">$'.
                $worker->maxtermearnings.'</td>';

            //calculate hours/earnings needed
            $officialearningsleft = $worker->maxtermearnings - $worker->officialtermearnings;
            $earningsleft = $worker->maxtermearnings - $worker->termearnings;
            $hoursleft = round($earningsleft / $worker->currpayrate, 2);
            $officialhoursleft = round($officialearningsleft / $worker->currpayrate, 2);


            if($worker->termearnings > $worker->maxtermearnings){

                //over the max
                $html .= 
                    '<td style="text-align:right"><span style="color: white; background: red">'.
                    '$'.
                    number_format($worker->officialtermearnings, 2).
                    ' / $'.
                    number_format($worker->termearnings, 2).
                    '</span></td>';

                $html .= 
                    '<td style="text-align:right"><span style="color: white; background: red">'.
                    '0 / 0'.
                    '</span></td>';

            } else if(($worker->maxtermearnings - $worker->termearnings) <= 50 && 
                $worker->termhours != 0){

                //close to the max

                $html .= 
                    '<td style="text-align:right">'.
                    '<span style="background: yellow">'.
                    '$'.
                    number_format($worker->officialtermearnings, 2).
                    ' / $'.
                    number_format($worker->termearnings, 2).
                    '</span></td>';

                $html .= 
                    '<td style="text-align:right">'.
                    '<span style="background: yellow">'.
                    round($officialhoursleft, 2).
                    ' / '.
                    round($hoursleft, 2).
                    '</span></td>';

            } else { //not close to or over maxtermearnings
                $html .= '<td style="text-align: right">'.
                    '$'.
                    number_format($worker->officialtermearnings, 2).
                    ' / $'.
                    number_format($worker->termearnings, 2).
                    '</td>';

                $html .= '<td style="text-align: right">'.
                    round($officialhoursleft, 2).
                    ' / '.
                    round($hoursleft, 2).
                    '</td>';
            }

            $html .= '<td style="text-align: right">$'.
                number_format($worker->officialtotalearnings, 2).
                ' / $'. 
                number_format($worker->totalearnings, 2).
                '</td>';

            $html .= '</tr>';
            $content .= $html;
        }

        $content .= '</table>';
        //$content .= $OUTPUT->box_end();
        print_collapsible_region($content, '', 
            'hasmaxterm', 'Workers with term earnings limit', '', '');
    }


    /*************************************/
    //workers that do NOT have max term earning

    $workersnomax = get_workers_stats($courseid, false, 0);

    $content = '';
    if($workersnomax){

        //now print out roster
        $reporturl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/reports.php');

        $content = '';
        //$content .= $OUTPUT->box_start('generalbox boxaligncenter');
    
        $content .= '<br /><table align="center" 
                    width="95%" style="border: 1px solid #000;">';
        $content .= '<tr><th colspan="7" >Worker list</th></tr>'."\n";
        $content .= '<tr>
                <td style="font-weight: bold"><br />Name</td>
                <td style="font-weight: bold"><br />Last unit</td>
                <td style="font-weight: bold; text-align: center"><br />Rate</td>
                <td style="font-weight: bold; text-align: right">Month earnings<br />'.
                'processed / total</td>
                <td style="font-weight: bold; text-align: right">Year earnings<br />'.
                'processed / total</td>
                <td style="font-weight: bold; text-align: right">Lifetime earnings<br />'.
                'processed / total</td>
                </tr>
            ';
        
        foreach($workersnomax as $worker){

            $reporturl->params(array('id'=>$worker->courseid, 
                'userid'=>$worker->id));

            $html = '<tr>';
            $html .= '<td>'.
                $OUTPUT->action_link($reporturl, $worker->lastname.', '.$worker->firstname)
                .'</td>';

            $html .= '<td>'.$worker->lastunit.'</td>';

            $html .= '<td style="text-align: center">$'.
                number_format($worker->currpayrate, 2).'</td>';

            $html .= '<td style="text-align: right">'.
                '$'.
                number_format($worker->officialmonthearnings, 2).
                ' / $'.
                number_format($worker->monthearnings, 2).
                '</td>';

            $html .= '<td style="text-align: right">'.
                '$'.
                number_format($worker->officialyearearnings, 2).
                ' / $'.
                number_format($worker->yearearnings, 2).
                '</td>';


            $html .= '<td style="text-align: right">$'.
                number_format($worker->officialtotalearnings, 2).
                ' / $'. 
                number_format($worker->totalearnings, 2).
                '</td>';

            $html .= '</tr>';
            $content .= $html;
        }
        $content .= '</table>';
        //$content .= $OUTPUT->box_end();
        print_collapsible_region($content, '', 
            'nomaxterm', 'Workers with no term earnings limit', '', '');
    } 

    if(!$workers && !$workersnomax){
        echo '<h2 style="text-align:center">No workers at this time</h2>';
    }

} else { //worker
    if(!$worker){
        print_error('User is not known to TimeTracker. '.
            'Please register on the course main page');
    }

    $userUnits = $DB->get_records('block_timetracker_workunit',
        array('userid'=>$worker->id),'timeout DESC','*',0,10);
    $userPending = $DB->get_records('block_timetracker_pending', 
        array('userid'=>$worker->id));


    //add clockin/clockout box
    if(!$userPending && $worker->timetrackermethod==0){ //timeclock
        $clockinicon = new pix_icon('clock_play','Clock-in', 'block_timetracker');
        $clockinurl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/timeclock.php',
            $urlparams);
        $clockinurl->params(array('clockin'=>1));
        $clockinaction = $OUTPUT->action_icon($clockinurl, $clockinicon);
        echo $OUTPUT->box_start('generalbox boxaligncenter');
        echo '<h2 style="text-align: center">Clock-in</h2>';
        echo '<p style="text-align: center">You are not currently clocked-in.'.
            '<br />Click the icon to clock-in now. ';
        echo $clockinaction.'</p>';
        echo $OUTPUT->box_end();
    } else if(!$userPending && $worker->timetrackermethod==1){ //hourlog
        $clockinicon = new pix_icon('clock_add','Add work unit', 'block_timetracker');
        $clockinurl = new moodle_url($CFG->wwwroot.'/blocks/timetracker/hourlog.php',
            $urlparams);
        $clockinaction = $OUTPUT->action_icon($clockinurl, $clockinicon);
        echo $OUTPUT->box_start('generalbox boxaligncenter');
        echo '<h2 style="text-align: center">Add hours</h2>';
        echo '<p style="text-align: center">Would you like to add some hours now?'.
            '<br />Click the icon to add work units. ';
        echo $clockinaction.'</p>';
        echo $OUTPUT->box_end();
    } else if ($userPending && $worker->timetrackermethod==0){
        echo $OUTPUT->box_start('generalbox boxaligncenter');
        echo '<h2>Pending Clock-in</h2>';

        $table = new flexible_table('timetracker-display-worker-index');
    
        $table->define_columns(array('timein', 'action'));
        $table->define_headers(array('Time in', 'Action'));

        $table->column_style('timein', 'text-align', 'left');
        $table->column_style('action', 'text-align', 'center');
        
        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('class', 
            'generaltable generalbox boxaligncenter');
        $table->define_baseurl($index);

        $table->setup();
        
        $clockouturl = new moodle_url($CFG->wwwroot.
            '/blocks/timetracker/timeclock.php', $urlparams);
        $clockouturl->params(array('clockout'=>1));
        foreach ($userPending as $pending){
            $clockouticon = 
                new pix_icon('clock_stop','Clock out','block_timetracker');
            $clockoutaction = 
                $OUTPUT->action_icon($clockouturl, $clockouticon);

            $urlparams['ispending']=true;
            $urlparams['unitid'] = $pending->id;

            $alertlink= new moodle_url($CFG->wwwroot.
                '/blocks/timetracker/alert.php', $urlparams);
            
            $baseurl = $CFG->wwwroot.'/blocks/timetracker'; 

            $urlparams['id'] = $pending->courseid;
            $urlparams['userid'] = $pending->userid;
            $urlparams['sesskey'] = sesskey();
            $urlparams['unitid'] = $pending->id;

            $deleteurl = new moodle_url($baseurl.
                '/deletepending.php', $urlparams);
            $deleteicon = new pix_icon('clock_delete', get_string('delete'),
                'block_timetracker');
            $deleteaction = $OUTPUT->action_icon(
                $deleteurl, $deleteicon, 
                new confirm_action(
                'Are you sure you want to delete this pending work unit?'));
            $alerticon= new pix_icon('alert',
                'Alert Supervisor of Error','block_timetracker');
            $alertaction= $OUTPUT->action_icon($alertlink, $alerticon);
            $table->add_data(
                array(userdate($pending->timein,get_string('datetimeformat',
                'block_timetracker')), 
                $clockoutaction.' '.$deleteaction.' '.$alertaction));
        }
        
        unset($urlparams['ispending']);
        unset($urlparams['unitid']);
        $table->print_html();

        echo $OUTPUT->box_end();


    }

    $stats = get_worker_stats($userid, $courseid);
    //error_log($stats['officialtermearnings']);

    $termstyle = '';
    if($worker->maxtermearnings > 0){  
        if($stats['termearnings'] > $worker->maxtermearnings) {
            $termstyle = 'red_warning';
        } else if ($worker->maxtermearnings - $stats['termearnings'] <= 50 && 
            $stats['termhours'] != 0){
            $termstyle = 'yellow_warning';
        }
    }

    //Term Data
    if($worker->maxtermearnings > 0){
        echo $OUTPUT->box_start('generalbox boxaligncenter');
        echo '<h2 style="text-align:center">Term earnings</h2>';

        //put term data here
        $termtable = new flexible_table('timetracker-display-worker-summary');
        $termtable->define_columns(array('label', 'value'));
        $termtable->define_headers(array('Name', 'Value'));

        $termtable->set_attribute('class', 'generaltable boxaligncenter');
    
        //$termtable->column_style('officialval', 'text-align', 'right');
        $termtable->column_style('label', 'text-align', 'left');
        $termtable->column_style('value', 'text-align', 'right');
        
        $termtable->define_baseurl($index);

        $termtable->setup();

        $termtable->add_data(array(
            'Maximum term earnings allowed', 
            '$'.number_format($worker->maxtermearnings, 2)
            ), 'bold');

        $termtable->add_data(array(
            'Processed / total  term earnings', 
            '$'.number_format($stats['officialtermearnings'], 2). ' / '.
            '$'.number_format($stats['termearnings'], 2)
            
            ), $termstyle);

        $officialearningsleft = $worker->maxtermearnings - $stats['officialtermearnings'];
        $earningsleft = $worker->maxtermearnings - $stats['termearnings'];

        $termtable->add_data(array(
            'Amount remaining (to process / to earn)', 
            '$'.number_format($officialearningsleft, 2). 
            ' / '.
            '$'.number_format($earningsleft, 2)
            ), $termstyle);

        $termtable->add_data(array('',''));


        $termtable->add_data(array(
            'Maximum hours allowed (approx)',
            round($worker->maxtermearnings/$worker->currpayrate, 2)),
            'bold');
            
        $termtable->add_data(array(
            'Processed / total hours this term', 
            $stats['officialtermhours']. ' / '.
            $stats['termhours']
            ), $termstyle);

        $termtable->add_data(array(
            'Hours remaining (to process / to earn)',
            round($officialearningsleft/$worker->currpayrate, 2). ' / '.
            round($earningsleft/$worker->currpayrate, 2)),
            $termstyle);

        $termtable->print_html();


        echo $OUTPUT->box_end();
    }

    //summary data
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    echo '<h2 style="text-align: center">Earnings summary</h2>';

    $statstable = new flexible_table('timetracker-display-worker-summary');
    $statstable->define_columns(array('period', 'officialval', 'totalvalue'));
    $statstable->define_headers(array('Period', 'Processed hours / earnings',
        'Total hours / earnings'));
    $statstable->define_baseurl($index);
    $statstable->set_attribute('class', 'generaltable boxaligncenter');
    //$statstable->set_attribute('class', 'boxaligncenter');
    
    $statstable->column_style('officialval', 'text-align', 'right');
    $statstable->column_style('totalvalue', 'text-align', 'right');
    $statstable->column_style('period', 'text-align', 'left');
    $statstable->column_style('period', 'font-weight', 'bold');

    $statstable->setup();


    $statstable->add_data(array(
        'This month',
        $stats['officialmonthhours'].' / '.'$'.
        $stats['officialmonthearnings'],
        $stats['monthhours'].' / '.'$'.$stats['monthearnings']
        ));
    $statstable->add_data(array(
        'This term',
        $stats['officialtermhours'].' / '.'$'.$stats['officialtermearnings'],
        $stats['termhours'].' / '.'$'.$stats['termearnings']), $termstyle
        );
    $statstable->add_data(array(
        'This year',
        $stats['officialyearhours'].' / '.'$'.$stats['officialyearearnings'],
        $stats['yearhours'].' / '.'$'.$stats['yearearnings']
        ));
    $statstable->add_data(array(
        'Total',
        $stats['officialtotalhours'].' / '.'$'.
        $stats['officialtotalearnings'],
        $stats['totalhours'].' / '.'$'.$stats['totalearnings']
        ));

    $statstable->print_html();

    echo $OUTPUT->box_end();

}

echo $OUTPUT->footer();
