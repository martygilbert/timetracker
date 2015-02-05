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

/**
* Tell whether a unit can be editable, based on the following:
* A unit may be edited until the 5th day of the next month
*
* Question about this is whethere we should look at timein
* or timeout, now that we're not splitting up work units
* before adding them to the db TODO
* @deprecated as of v2011123100
*/
function expired($timein, $now = -1){
    if($now == -1) $now = time();

    $currdateinfo = usergetdate($now);

    $unitdateinfo = usergetdate($timein);
    if($now - $timein > (86400 * 35) || 
        (($currdateinfo['month'] != $unitdateinfo['month'] || 
        $currdateinfo['year'] != $unitdateinfo['year']) &&
        $currdateinfo['mday'] > 5)){
        return true;
    }
    return false;

}

/**
* Given a userid, courseid, start time and end time, find all units
* that meet within this time period, and split them up across the day boundary.
* For Example:
* DB work unit 10/01/11 09:00am to 10/02/11 02:00am
* this function would return:
*
* array[0]: 10/01/11 09:00am - 11:59:59
* array[1]: 10/02/11 12:00am - 02:00am
*
* Note: some $unit->id may be the same, since a single unit will be broken
* up across many days.
* 
* NOTE: $unsignedonly takes precedence over $timesheetid. If you ask for 
* $unsignedonly, it will give you only those units that are NOT part of
* an existing timesheet.
*
* @return an array of objects, each having all the properties of a work unit
*/
function get_split_units($start, $end, $userid=0, $courseid=0, $timesheetid=-1, $sort='ASC', 
    $unsignedonly=false){
    global $CFG, $DB;

    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_workunit WHERE ';

    if($timesheetid != -1){
        $sql .= ' timesheetid='.$timesheetid;
    } else {
        $sql.=
        '(timein BETWEEN '.
        $start. ' AND '.$end.' OR timeout BETWEEN '.
        $start. ' AND '.$end.') ';
    }

    if($userid > 0){
        $sql .= ' AND userid='.$userid;
    }
    
    if($courseid > 0){
        $sql .= ' AND courseid='.$courseid;
    }

    if($unsignedonly){
        $sql .= ' AND timesheetid=0';
    } 

    $sql .= ' ORDER BY timein '.$sort;

    $units = $DB->get_records_sql($sql);

    if(!$units) {
        return;
    }

    $splitunits = array(); 
    $nowtime = time();

    foreach($units as $unit){

        $splits = split_unit($unit);

        $splitunits = array_merge($splitunits, $splits);

    }
    return $splitunits;
}

//UGLY. Refactor
function get_unsplit_units($start, $end, $userid=0, $courseid=0, $timesheetid=-1, $sort='ASC', 
    $unsignedonly=false){
    global $CFG, $DB;

    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_workunit WHERE ';

    $sql.=
        '(timein BETWEEN '.
        $start. ' AND '.$end.' OR timeout BETWEEN '.
        $start. ' AND '.$end.') ';

    if($timesheetid != -1){
        $sql .= ' AND timesheetid='.$timesheetid;
    } 

    if($userid > 0){
        $sql .= ' AND userid='.$userid;
    }
    
    if($courseid > 0){
        $sql .= ' AND courseid='.$courseid;
    }

    if($unsignedonly){
        $sql .= ' AND timesheetid=0';
    } 

    $sql .= ' ORDER BY timein '.$sort;

    $units = $DB->get_records_sql($sql);

    if(!$units) {
        return;
    }
    return $units;
}


/**
* If any units straddle the $start or $end boundary, split them into multiple units
* Only consider units that have NOT been included in a timesheet already.
*/
function split_boundary_units($start, $end, $userid, $courseid){
    global $DB, $CFG;

    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_workunit WHERE '.
        'userid = '.$userid.' AND courseid = '.$courseid.' AND timesheetid=0 AND '.
        'timein < '.$start.' AND timeout > '.$start;
    
    $startunits = $DB->get_records_sql($sql);

    if($startunits){
        //if there are some (only should be 1, right?)
        //split them up to timein->$start and $start->timeout
        foreach($startunits as $unit){

            $origid = $unit->id;
            $timein = $unit->timein;
            $timeout = $unit->timeout; 

            unset($unit->id); 
            $unit->timeout = $start-1;

            $result = $DB->insert_record('block_timetracker_workunit', $unit);

            if(!$result) {
                print_error("Error splitting boundary work unit");
                return;
            }
            
            unset($unit->id);
            $unit->timein = $start;
            $unit->timeout = $timeout;
            
            $result = $DB->insert_record('block_timetracker_workunit', $unit);

            if(!$result) {
                print_error("Error splitting boundary work unit");
                return;
            }

            //delete the original
            $DB->delete_records('block_timetracker_workunit', array('id'=>$origid));
            //TODO update work unit history here?
        }
    }

    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_workunit WHERE '.
        'userid = '.$userid.' AND courseid = '.$courseid.' AND timesheetid=0 AND '.
        'timein BETWEEN '.$start.' AND '.$end.' AND timeout > '.$end;

    $endunits = $DB->get_records_sql($sql);

    if($endunits){
        //if there are some (only should be 1, right?)
        //split them up to timein->$end and $end->timeout
        foreach($endunits as $unit){
            $origid = $unit->id;
            $timein = $unit->timein;
            $timeout = $unit->timeout; 

            unset($unit->id); 
            $unit->timeout = $end;

            $result = $DB->insert_record('block_timetracker_workunit', $unit);

            if(!$result) {
                print_error("Error splitting boundary work unit");
                return;
            }
            
            unset($unit->id);
            $unit->timein = $end + 1;
            $unit->timeout = $timeout;

            $result = $DB->insert_record('block_timetracker_workunit', $unit);

            if(!$result) {
                print_error("Error splitting boundary work unit");
                return;
            }

            //delete the original
            $DB->delete_records('block_timetracker_workunit', array('id'=>$origid));
            //TODO update work unit history here?
        }
    }

}

/**
* Given a $unit (full of work unit table data), return an array of $unit objects
* That are split across the day boundary
* TO NOTE: Units that are split up have a 'partial' property that is set to True.
* @return an array of work units split up into days
*/
function split_unit($unit){

    //FIX ME! XXX I see lots of 86400 in here. Should use strtotime(), probably.

    $splitunits = array();

    if(!is_object($unit)) return $splitunits;

    $timein = usergetdate($unit->timein);
    $timeout = usergetdate($unit->timeout);

    //check to see if in and out are on the same day
    if($timein['year'] == $timeout['year'] && 
        $timein['month'] == $timeout['month'] &&
        $timein['mday'] == $timeout['mday']){

        $newunit = new stdClass();
        $newunit->timein = $unit->timein;
        $newunit->timeout = $unit->timeout;
        $newunit->payrate = $unit->payrate;
        $newunit->lastedited = $unit->lastedited;
        $newunit->lasteditedby = $unit->lasteditedby;
        $newunit->id = $unit->id;
        $newunit->userid = $unit->userid;
        $newunit->courseid = $unit->courseid;
        $newunit->partial = 0;
        $newunit->timesheetid = $unit->timesheetid;
        $newunit->canedit = $unit->canedit;
        $newunit->submitted = $unit->submitted;
    
        $splitunits[] = $newunit;
    } else { //spans multiple days
    
        $origtimein = $unit->timein;
        $checkout = $unit->timeout;
        $endofday = (86400+(usergetmidnight($unit->timein)-1)); //XXX
    
        $usersdate = usergetdate($endofday);
        if($usersdate['hours'] == 22){ 
            $endofday += 60 * 60;
        } else if ($usersdate['hours'] == 0){
            $endofday -= 60 * 60;
        }
    
        while ($unit->timein < $checkout){
        
            //add to array
            $unit->timeout = $endofday;

            $newunit = new stdClass();
            $newunit->timein = $unit->timein;
            $newunit->timeout = $unit->timeout;
            $newunit->payrate = $unit->payrate;
            $newunit->lastedited = $unit->lastedited;
            $newunit->lasteditedby = $unit->lasteditedby;
            $newunit->id = $unit->id;
            $newunit->userid = $unit->userid;
            $newunit->courseid = $unit->courseid;
            $newunit->partial = true;
            $newunit->timesheetid = $unit->timesheetid;
            $newunit->canedit = $unit->canedit;
            $newunit->submitted = $unit->submitted;
    
            $splitunits[] = $newunit;
    
            $unit->timein = $endofday + 1;

            //find next 23:59:59
            $endofday = 86400 + (usergetmidnight($unit->timein)-1);
        
            //because I can't get dst_offset_on to work!
            $usersdate = usergetdate($endofday);
            if($usersdate['hours'] == 22){ 
                $endofday += 60 * 60;
            } else if ($usersdate['hours'] == 0){
                $endofday -= 60 * 60;
            }
    
            //if not a full day, don't go to 23:59:59 
            //but rather checkout time
            if($endofday > $checkout){
                $endofday = $unit->timein + ($checkout - $unit->timein);
            } 
        }
    }
    return $splitunits;
}


/*
* uses get_split_units() to return an array of unit objects, none of
* which cross the day boundary. Note: id of these units may be shared.
* THIS SHOULD BE FOR DISPLAY ONLY, NO? Otherwise we have rounding issues for totals
* @return array of unit objects
*
*/
function get_split_month_work_units($userid, $courseid, $month, $year, $timesheetid=-1,
    $unsignedonly = false){

    $info = get_month_info($month, $year);

    return get_split_units($info['firstdaytimestamp'], $info['lastdaytimestamp'],
        $userid, $courseid, $timesheetid, 'ASC', $unsignedonly);
}

//UGLY. Refactor
function get_unsplit_month_work_units($userid, $courseid, $month, $year, $timesheetid=-1,
    $unsignedonly = false){

    $info = get_month_info($month, $year);

    return get_unsplit_units($info['firstdaytimestamp'], $info['lastdaytimestamp'],
        $userid, $courseid, $timesheetid, 'ASC', $unsignedonly);
}


/**
* Given an object that holds all of the values necessary from block_timetracker_workunit,
* Add it to the work unit table.
* @return true if worked, false if failed
*/
function add_unit($unit, $hourlog=false){
    global $DB, $CFG, $OUTPUT;

    if(!is_object($unit)) return false;
    if(isset($unit->id)) unset($unit->id);

    $unitid = $DB->insert_record('block_timetracker_workunit', $unit);

    $url = new moodle_url($CFG->wwwroot.'/blocks/timetracker/reports.php');
    $url->params(array('id'=>$unit->courseid, 
        'userid'=>$unit->userid,
        'reportstart'=>($unit->timein-1),
        'reportend'=>($unit->timeout+1)));

    if($unitid){
        if($hourlog)
            add_to_log($unit->courseid, '', 'add work unit', '', 'TimeTracker add work unit');
        else
            add_to_log($unit->courseid, '', 'add clock-out', '', 'TimeTracker clock-out');
    } else {
        if($hourlog){
            add_to_log($unit->courseid, '', 
                'error adding work unit', '', 'ERROR:  User hourlog failed.');
        } else {
            add_to_log($unit->courseid, '', 
                'error clocking-out', '', 'ERROR:  User clock-out failed.');
        }
        return false; 
    }
    return true;
}

/**
* Given an object that holds all of the values necessary from block_timetracker_workunit,
* add update it in the DB
* @return the true if updated successfully, false if not
*
*/
function update_unit($unit){
    global $DB;
    $id = $unit->id;
    $result = add_unit($unit);
    if($result){
        $deleteresult = $DB->delete_records('block_timetracker_workunit', 
            array('id'=>$id));
        if(!$deleteresult){
            //log error in deleting?
            return false;
        }
    } else {
        return false;    
    }
    return true;
}

/**
* Attempts to see if this work unit overlaps with any other work units already submitted
* for user $userid in $COURSE
* @return T if overlaps
*/
function overlaps($timein, $timeout, $userid, $unitid=-1, $courseid=-1){
    global $CFG, $COURSE, $DB;
    if($courseid == -1) $courseid = $COURSE->id;
    
    $sql = 'SELECT COUNT(*) FROM '.$CFG->prefix.'block_timetracker_workunit WHERE '.
        "$userid = userid AND $courseid = courseid AND (".
        "($timein >= timein AND $timein < timeout) OR ".
        "($timeout > timein AND $timeout <= timeout) OR ".
        "(timein >= $timein AND timein < $timeout))";
        
    if($unitid != -1){
      $sql.=" AND id != $unitid"; 
    }

    $numexistingunits = $DB->count_records_sql($sql);

    $sql = 'SELECT COUNT(*) FROM '.$CFG->prefix.'block_timetracker_pending WHERE '.
        "$userid = userid AND $courseid = courseid AND ".
        "timein BETWEEN $timein AND $timeout";

    $numpending = $DB->count_records_sql($sql);

    if($numexistingunits == 0 && $numpending == 0) return false;
    return true;
}

/**
* Returns an array of stdobjects that have the following:
* obj->display (how to display the work unit)
* obj->editlink (a url to edit the work unit)
* obj->deletelink (a url to delete the unit)
* obj->alertlink (a url to create an alert)
* obj->timein (a timestamp for this clock-in)
* obj->timeout (a timestamp for this clock-out, if applicable. if
* obj->id (the id of the offending unit)
* obj->timesheetid (the timesheetid of the offending unit)
* it is a pending clock-in, this value will be the same as the clock-in value)
* If the array is empty, there are no overlapping units
*/
function find_conflicts($timein, $timeout, $userid, $unitid=-1, $courseid=-1,
    $ispending=false, $onlyunsigned=false){

    global $CFG, $COURSE, $DB;
    if($courseid == -1) $courseid = $COURSE->id;
    
    //check work unit table first
    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_workunit WHERE '.
        "$userid = userid AND $courseid = courseid AND (".
        "($timein >= timein AND $timein < timeout) OR ".
        "($timeout > timein AND $timeout <= timeout) OR ".
        "(timein >= $timein AND timein < $timeout))";
        
    if($unitid != -1 && !$ispending){
        $sql.=" AND id != $unitid"; 
    }

    if($onlyunsigned){
        $sql .= " AND timesheetid=0";
    }

    $conflictingunits = $DB->get_records_sql($sql);

    $conflicts  = array();
    $baseurl = $CFG->wwwroot.'/blocks/timetracker';
    foreach ($conflictingunits as $unit){
        $entry = new stdClass();
        $disp = userdate($unit->timein,
            get_string('datetimeformat', 'block_timetracker')).
            ' to '.userdate($unit->timeout,
            get_string('datetimeformat', 'block_timetracker'),99,false);
        $entry->display = $disp;
        $entry->deletelink = $baseurl.'/deleteworkunit.php?id='.$unit->courseid.
            '&userid='.$unit->userid.'&unitid='.$unit->id.
            '&sesskey='.sesskey();
        $entry->editlink = $baseurl.'/editunit.php?id='.$unit->courseid.
            '&userid='.$unit->userid.'&unitid='.$unit->id;
        $entry->alertlink = $baseurl.'/alert.php?id='.$unit->courseid.
            '&userid='.$unit->userid.'&unitid='.$unit->id;
        $entry->timein = $unit->timein;
        $entry->timeout = $unit->timeout;
        $entry->id = $unit->id;
        $entry->timesheetid=$unit->timesheetid;

        $conflicts[] = $entry;
    }

    //pending units
    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_pending WHERE '.
        "$userid = userid AND $courseid = courseid AND ".
        "timein BETWEEN $timein AND $timeout";

    if($unitid != -1 && $ispending){
        $sql.=" AND id != $unitid"; 
    }

    $pendingconflicts = $DB->get_records_sql($sql);
    foreach ($pendingconflicts as $pending){
        $entry = new stdClass();
        $disp = 'Pending clock-in time: '.userdate($pending->timein,
            get_string('datetimeformat', 'block_timetracker'));
        $entry->display = $disp;
        $entry->deletelink = $baseurl.'/deleteworkunit.php?id='.$pending->courseid.
            '&userid='.$pending->userid.'&unitid='.$pending->id;
        $entry->editlink =  '#';
        $entry->timein = $entry->timeout = $pending->timein;
        $entry->alertlink = $baseurl.'/alert.php?id='.$pending->courseid.
            '&userid='.$pending->userid.'&unitid='.$pending->id.'&ispending=true';
        $entry->id = $pending->id;

        $conflicts[] = $entry;

    }
   
    return $conflicts;
}

/**
* Used in navigation
* @return array of tabobjects 
*/
function get_tabs($urlparams, $canmanage = false, $courseid = -1){
    global $CFG;
    $basicparams = $urlparams;
    unset($basicparams['userid']);

    $tabs = array();
    $tabs[] = new tabobject('home',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker/index.php', $basicparams),
        'Main');
    $tabs[] = new tabobject('reports',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker/reports.php',
        $urlparams),'Reports');
    $tabs[] = new tabobject('timesheets',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker/timesheet.php',
        $urlparams), 'Timesheets');

    
    $numalerts = '';
    if($canmanage){
        $manageurl = 
            new moodle_url($CFG->wwwroot.'/blocks/timetracker/manageworkers.php', 
            $basicparams);
        $manageurl->remove_params('userid');
        $tabs[] = new tabobject('manage', $manageurl, 'Manage Workers');
        $tabs[] = new tabobject('terms',
            new moodle_url($CFG->wwwroot.'/blocks/timetracker/terms.php', $basicparams),
            'Terms');
        if($courseid != -1){
            //getnumalerts from $courseid
            $n = has_course_alerts($courseid);
            if($n > 0){
                $numalerts = '('.$n.')';
            }
        }
    }
    $tabs[] = new tabobject('alerts',
        new moodle_url($CFG->wwwroot.'/blocks/timetracker/managealerts.php', $basicparams),
        'Alerts '.$numalerts);

    return $tabs;
}

function add_enrolled_users($context){
    global $COURSE, $DB;

    //Add any enrolled users NOT in the WORKERINFO table.
    $config = get_timetracker_config($COURSE->id);
    $students = get_enrolled_users($context, 'mod/assignment:submit');

    foreach ($students as $student){
        $worker = $DB->get_record('block_timetracker_workerinfo',
            array('mdluserid'=>$student->id, 'courseid'=>$COURSE->id));

        if(!$worker){
            //They've never been enrolled in this course before.
            $student->mdluserid = $student->id;
            unset($student->id);
            $student->courseid = $COURSE->id;
            $student->idnum = $student->username;
            $student->address = '0';
            $student->position = $config['position'];
            $student->currpayrate = $config['curr_pay_rate'];
            $student->timetrackermethod = $config['trackermethod'];
            $student->dept = $config['department'];
            $student->budget = $config['budget'];
            $student->supervisor = $config['supname'];
            $student->institution = $config['institution'];
            $student->maxtermearnings = $config['default_max_earnings'];
            $res = $DB->insert_record('block_timetracker_workerinfo', $student);
            if(!$res){
                print_error("Error adding $student->firstname $student->lastname ".
                    "to TimeTracker");
            }
        } else if ($worker->deleted == 1){
            //check to see if the user is enrolled now (we un-enroll on deletion),
            //and if the deleted flag is set. That would mean that they should
            //be un-deleted.

            $worker->deleted    = 0;
            $worker->active     = 0;
            $DB->update_record('block_timetracker_workerinfo', $worker);

        }
    }

}

/*
* rounds to nearest 15 minutes (900 secs) by default. can be set
* using config_block_timetracker_round
*/
function round_time($totalsecs=0, $round=900){
    if($totalsecs <= 0) return 0;
    
    if($round > 0){
        $temp = $totalsecs % 3600;
        $disttoround = $temp % $round;
    
        if($disttoround >= ($round/2)) 
            $totalsecs = $totalsecs + ($round - $disttoround); //round up
        else 
            $totalsecs = $totalsecs - $disttoround; //round down
    }

    return $totalsecs;
}

/*
* @return number of hours in decimal format, rounded to the nearest config->round
*/
function get_hours($totalsecs=0, $courseid=-1){

    $round = get_rounding_config($courseid);
    $totalsecs = round_time($totalsecs, $round);
    $hrs = round($totalsecs/3600, 3);
    return ($hrs);
}


/**
* returns the $totalsecs as 'xx hour(s) xx minute(s)', rounded to the nearest 15 min
*/
function format_elapsed_time($totalsecs=0, $courseid=-1){
    if($totalsecs <= 0){
        return '0 hours 0 minutes';
    }

    $round = get_rounding_config($courseid);
    $totalsecs = round_time($totalsecs, $round);
    $hours = floor($totalsecs/3600);
    $minutes = ($totalsecs % 3600)/60;
    
    return $hours.' hour(s) and '.$minutes. ' minute(s)'; 
}

function get_rounding_config($courseid = -1){
    if($courseid == -1){
        $round = 900;
    } else {
        $config = get_timetracker_config($courseid);
        if(array_key_exists('round', $config)){
            $round = $config['round'];
        } else {
            $round = 900;
        }
    }
    return $round;
}

/**
* Calculate Total Earnings 
* @param $userid, $courseid
* @return total money earned
*/
function get_total_earnings($userid, $courseid, $officialonly=false){

    //$workerunits = get_split_units(0, time(), $userid, $courseid);
    $workerunits = get_unsplit_units(0, time(), $userid, $courseid);

    if(!$workerunits) return 0;
    $round = get_rounding_config($courseid);

    $earnings = 0;
    foreach($workerunits as $subunit){
        if($officialonly){
            if($subunit->submitted){
                $hours = round_time($subunit->timeout - $subunit->timein, $round);
                $hours = round($hours/3600, 3);
                $earnings += $hours * $subunit->payrate;
            }
        } else {
            $hours = round_time($subunit->timeout - $subunit->timein, $round);
            $hours = round($hours/3600, 3);
            $earnings += $hours * $subunit->payrate;
        }
    }

    return round($earnings, 2);

}

/**
* This function will determine if there are any unsigned units
* prior to $start for this user in this course.
* @param $courseid - the courseid in quesiton
* @param $userid - the TimeTracker id of the worker
* @param $start - the epoch start time - we want to see if there are any units prior
* @return 0 if no older, unsigned units exist, otherwise the epoch timein of the oldest
*/
function has_older_unsigned_units($courseid, $userid, $start){
    global $DB, $CFG;

    $sql = 'SELECT * FROM '.$CFG->prefix.'block_timetracker_workunit WHERE '.
        'courseid='.$courseid.' AND userid='.$userid.' AND timein < '.$start.
        ' AND timesheetid=0 ORDER BY timein ASC LIMIT 1';

    $result = $DB->get_records_sql($sql);

    if(!$result) return 0;

    $u = array_shift($result);
    return $u->timein;
}

/**
* Determine if the course has alerts waiting
* @param $courseid id of the course
* @return 0 if no alerts are pending, # of alerts if they exist.
*/
function has_course_alerts($courseid, $userid = -1){
    global $CFG, $DB;
    //check the alert* tables to see if there are any outstanding alerts:
    $sql = 'SELECT COUNT(*) FROM '.
        $CFG->prefix.'block_timetracker_alertunits'.
        ' WHERE courseid='.$courseid;

    if($userid != -1){
        $sql .= ' AND userid='.$userid;
    }
    $sql .= ' ORDER BY alerttime';

    $numalerts = $DB->count_records_sql($sql);
    return $numalerts;

}


/**
* Generate the alert links for a course
* @param $courseid id of the course
* @return three-dimensional array of links. First index is the TT worker id, the second is 
* the alertid, while the third index is either 'approve', 'deny', or 'change' for each of
* the corresponding links
*/
function get_course_alert_links($courseid){
    global $CFG, $DB;
    //check the alert* tables to see if there are any outstanding alerts:
    $sql = 'SELECT '.$CFG->prefix.'block_timetracker_alertunits.* FROM '.
        $CFG->prefix.'block_timetracker_alertunits '.
        'WHERE courseid='.$courseid.
            ' ORDER BY alerttime';

    $alerts = $DB->get_recordset_sql($sql);
    $alertlinks = array();
    foreach ($alerts as $alert){
        $url = $CFG->wwwroot.'/blocks/timetracker/alertaction.php';
        
        $params = "?alertid=$alert->id";
        if($alert->todelete) $params.="&delete=1";

        $alertlinks[$alert->userid][$alert->id]['approve'] = $url.$params."&action=approve";
        $alertlinks[$alert->userid][$alert->id]['deny'] = $url.$params."&action=deny";
        $alertlinks[$alert->userid][$alert->id]['change'] = $url.$params."&action=change";
        $alertlinks[$alert->userid][$alert->id]['delete'] = $url.$params."&action=delete";

    }

    return $alertlinks;
}




/**
* Generate the alert links for a course
* @param $courseid id of the course
* @param $alerticon create the alert icon (using new pix_icon)
* @param $alertaction create the action of the alert (usually $OUTPUT->action_icon($alertsurl,$alertaction) 
* @return hyperlink with number of existing alerts
* Example: Manage Alerts (3)
*/
function get_alerts_link($courseid, $alerticon, $alertaction){
    global $CFG, $DB;
    //getnumalerts from $courseid
    $numalerts = '';
    $n = has_course_alerts($courseid);
    if($n > 0){
        $numalerts = '('.$n.')';
    }
    
    $urlparams['id'] = $courseid;
    $baseurl = $CFG->wwwroot.'/blocks/timetracker';
    $url = new moodle_url($baseurl.'/managealerts.php', $urlparams);
    $text = $alertaction.' <a href="'.$url. 'style="color: red">Manage Alerts '.$numalerts.'</a><br />';

    return $text;
}

/**
* Determine if the course has alerts waiting
* @param $courseid id of the course
* @return 0 if no alerts are pending, # of alerts if they exist.
*/
function has_unsigned_timesheets($courseid){
    global $CFG, $DB;
    //check the timesheet table to see if there are any unsigned timesheets:
    $numtimesheets = $DB->count_records('block_timetracker_timesheet',
        array('courseid'=>$courseid,
        'supervisorsignature'=>0));
    return $numtimesheets;

}

function get_timesheet_link($courseid, $timesheetsicon, $timesheetsaction){
    global $CFG, $DB;
    //getnumalerts from $courseid
    $numts = '';
    $n = has_unsigned_timesheets($courseid);
    if($n > 0){
        $numts = '('.$n.')';
    }

    $urlparams['id'] = $courseid;
    $baseurl = $CFG->wwwroot.'/blocks/timetracker';
    $url = new moodle_url($baseurl.'/supervisorsig.php', $urlparams);
    $text = $timesheetsaction.' <a href="'.$url.'" style="color: red">
        Sign Timesheets '.$numts.'</a><br /><br />';

    return $text;
}



/**
* Calculate Total Hours
* @param $workerunits is an array, each $subunit has $subunit->timein and $subunit->timeout
* @return total hours worked in decimal format rounded to the nearest interval.
*/
function get_total_hours($userid, $courseid, $officialonly=false){

    $workerunits = get_unsplit_units(0, time(), $userid, $courseid);

    if(!$workerunits) return 0;

    $round = get_rounding_config($courseid);
    $total = 0;
    foreach($workerunits as $subunit){
        if($officialonly){
            if($subunit->submitted){
                $total += round_time($subunit->timeout - $subunit->timein, $round);
            }
        } else {
            $total += round_time($subunit->timeout - $subunit->timein, $round);
        }
    }

    return get_hours($total, $courseid);

}

/**
* Also calculates overtime (Weeks > 40 hours Mon-Sun)
* @return earnings (in dollars) for this time period
*
*/
function get_earnings($userid, $courseid, $start, $end, $processovt=1, $timesheetid=-1){

    global $DB;

    //this is bad, no? get_split_units should only be for 
    //$units = get_split_units($start, $end, $userid, $courseid);
    $units = get_unsplit_units($start, $end, $userid, $timesheetid, $courseid);
    if(!$units) return 0;

    $info = break_down_earnings($units, $processovt);
    return $info['earnings'];
}

/**
* Get all of the info associated with this start/end tiem
* @return array of info regarding earnings. See break_down_earnings
*/
function get_earnings_info($userid, $courseid, $start, $end, $processovt=1, $timesheetid=-1,
    $unsignedonly = false){

    global $DB;

    //split boundary units first?
    split_boundary_units($start, $end, $userid, $courseid);

    //this is bad, no? get_split_units should only be for  display?
    //$units = get_split_units($start, $end, $userid, $courseid);

    $units = get_unsplit_units($start, $end, $userid, $courseid, $timesheetid,
        'ASC', $unsignedonly);

    if(!$units) return 0;

    return break_down_earnings($units, $processovt);
}

/**
* Break down earnings into reghours, regearnings, ovthours, ovtearnings,hours,earnings
* All $unit in $units should be from the same userid/courseid and sorted by timein
* @return array
*/
function break_down_earnings($units, $processovt = 1){
    
    global $DB;
    $info = array();
    $info['reghours'] = 0;
    $info['regearnings'] = 0;
    $info['ovthours'] = 0;
    $info['ovtearnings'] = 0;
    $info['hours'] = 0;
    $info['earnings'] = 0;

    if(!$units) return $info;

    $lastunit = end($units);
    $firstunit = reset($units);

    $round = get_rounding_config($firstunit->courseid);

    $worker = $DB->get_record('block_timetracker_workerinfo',
        array('id'=>$firstunit->userid));

    if(!$worker) return $info;
    if(!$processovt){

        $earnings = 0;
        foreach($units as $unit){
            $hours = round_time($unit->timeout - $unit->timein, $round);
            $hours = round($hours/3600, 3);
            $info['hours'] += $hours;
            $info['reghours'] += $hours;
            $earnings += $hours * $unit->payrate;
        }
        $info['earnings'] = $info['regearnings'] = round($earnings, 3);

    } else {

        //we know we want monday - sunday to find overtime.
        //why not split each unit in this group that crosses the sun->monday boundary
        //THEN, look at each unit on a week by week basis

        //array to hold week hours
        $weekhours = array(); 
        $newunits = array();
        
        //$monthinfo = get_month_info(userdate($firstunit->timein, "%m"),
            //userdate($firstunit->timein, "%Y"));

        //week0 is day 1 - midnight the following Sunday.
        //$monday = $monthinfo['firstdaytimestamp']; //not really monday, I know
        $monday = strtotime("last Monday", strtotime('tomorrow', $firstunit->timein));
        $sunday = strtotime("next Monday", $monday) - 1; //23:59:59
        //error_log(userdate($monday, "%D %H:%M:%S"));
        //error_log(userdate($sunday, "%D %H:%M:%S"));

        while(sizeof($units) > 0){
            
            //look at first item in array
            $unit = array_shift($units);

            if($unit->timeout < $monday){
                //fast-forward
                //before this 'week'
                //error_log("Should never happen - before this week");
                //just in case.
                continue;
            }

            if($unit->timein >= $monday && $unit->timeout <= $sunday){ 
                //belongs to this week.

                $newunits[] = $unit;
    
            } else if (($unit->timein >= $monday && $unit->timein <= $sunday) 
                && $unit->timeout > $sunday){ 

                //IMPORTANT REMINDER!!
                //don't touch the original unit - make a copy!
                //Arrays are passed by copy, but they copy the references
                //to the object, and NOT the objects themselves


                //crosses the boundary; split them into 2 + divider

                //unit A: timein->sunday
                $preunit = new stdClass();
                $preunit->timein = $unit->timein;
                $preunit->timeout = $sunday;
                $preunit->partial = true;
                $preunit->payrate = $unit->payrate;
                $preunit->lastedited = $unit->lastedited;
                $preunit->lasteditedby = $unit->lasteditedby;
                $preunit->id = $unit->id;
                $preunit->userid = $unit->userid;
                $preunit->courseid = $unit->courseid;
                $preunit->timesheetid = $unit->timesheetid;
                $preunit->canedit = $unit->canedit;
                $preunit->submitted = $unit->submitted;
    
                $newunits[] = $preunit;
    
                /**************************/
                //Divider unit
                $dividerUnit = new stdClass();
                $dividerUnit->timein = -1; //signify divider unit
    
                $newunits[] = $dividerUnit;
                /**************************/
    
                //unit B monday->timeout
                $postunit = new stdClass();
                $postunit->timein = $sunday + 1;
                $postunit->timeout = $unit->timeout;
                $postunit->payrate = $unit->payrate;
                $postunit->lastedited = $unit->lastedited;
                $postunit->lasteditedby = $unit->lasteditedby;
                $postunit->id = $unit->id;
                $postunit->userid = $unit->userid;
                $postunit->courseid = $unit->courseid;
                $postunit->partial = true;
                $postunit->timesheetid = $unit->timesheetid;
                $postunit->canedit = $unit->canedit;
                $postunit->submitted = $unit->submitted;
    
                $newunits[] = $postunit;
                //error_log("Added: $newunit->timein $newunit->timeout");
    
                $monday = strtotime("next Monday", $monday);
                $sunday = strtotime("next Monday", $monday) - 1;
    
            } else { 
                
                //not on this week. Go to next one
                //and put this guy back in the front of the array
                array_unshift($units, $unit);

                //Divider unit
                $dividerUnit = new stdClass();
                $dividerUnit->timein = -1; //signify divider unit
                $newunits[] = $dividerUnit;
    
        
                //look at next week's times in/out
                $monday = strtotime("next Monday", $monday);
                $sunday = strtotime("next Monday", $monday) - 1;
                //break;
            }
        }


        $weeknum = 0;
        $weekhours[$weeknum] = 0;
        foreach($newunits as $unit){
            //weekday will be from 1 (Monday) to 7 (Sunday)

            //week divider
            if($unit->timein == -1){
                $weeknum++;
                $weekhours[$weeknum] = 0;
                continue;
            }

            $hours = round_time($unit->timeout - $unit->timein, $round);
            $hours = round($hours/3600, 3);

    
            if( ($hours + $weekhours[$weeknum]) > 40){
                $ovthours = $reghours = 0; 

                if($weekhours[$weeknum] > 40){
                    $ovthours = $hours;
                } else {
                    $reghours = 40 - $weekhours[$weeknum]; 
                    $ovthours = $hours - $reghours;
                }
    
                $info['reghours'] += $reghours;
                $info['ovthours'] += $ovthours;
    
                $amt = $reghours * $unit->payrate;
                $info['regearnings'] += $amt;
                
                $ovtamt = $ovthours * ($unit->payrate * 1.5);
                $info['ovtearnings'] += $ovtamt;
    
            } else {
                $amt = $hours * $unit->payrate;
                $info['reghours'] += $hours;
                $info['regearnings'] += $amt;
            }
            $weekhours[$weeknum] += $hours;
        }
    
        $info['regearnings'] = round($info['regearnings'], 3);
        $info['ovtearnings'] = round($info['ovtearnings'], 3);
        $info['earnings'] = round($info['regearnings']+$info['ovtearnings'], 3);
        $info['hours'] = $info['reghours'] + $info['ovthours'];
        $info['weekhours'] = $weekhours;

    }

    /*
    foreach($weekhours as $h){
        error_log($h);
    }
    */

    return $info;
}

/**
* @return earnings (in dollars) for this month
*
*/
function get_earnings_this_month($userid, $courseid, $month = -1, $year = -1,
    $officialonly=false){
    $currtime = usergetdate(time());

    if($month == -1 || $year == -1){
        $units = get_split_month_work_units($userid, $courseid, 
            $currtime['mon'], $currtime['year']);
    } else {
        $units = get_split_month_work_units($userid, $courseid, 
            $month, $year);
    }

    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $earnings = 0;
    foreach($units as $unit){
        if($officialonly){
            if($unit->submitted){
                $hours = round_time($unit->timeout - $unit->timein, $round);
                $hours = round($hours/3600, 3);
                $earnings += $hours * $unit->payrate;
            }
        } else {
            $hours = round_time($unit->timeout - $unit->timein, $round);
            $hours = round($hours/3600, 3);
            $earnings += $hours * $unit->payrate;
        }
    }
    return round($earnings, 2);
}

/**
* @param $month The month (1-12) that you're inspecting
* @param $year The year (yyyy) that you're inspecting
* @return array of values
    $monthinfo['firstdaytimestamp'] <= unix time of midnight of first day
    $monthinfo['lastdaytimestamp'] <= unix time of 23:59:59 of last day
    $monthinfo['dayofweek'] <= the index of day of week of first day (0Sun-6Sat)
    $monthinfo['lastday'] <= the last day of this month
    $monthinfo['monthname'] <= the name of this month
*/
function get_month_info($month, $year){
    $monthinfo = array();
    
    $timestamp = make_timestamp($year, $month); //timestamp of midnight, first day of $month
    $monthinfo['firstdaytimestamp'] = $timestamp;
    $monthinfo['lastday'] = date('t', $timestamp);

    $thistime = usergetdate($timestamp);
    $monthinfo['dayofweek'] = $thistime['wday'];
    $monthinfo['monthname'] = $thistime['month'];

    $timestamp = make_timestamp($year, $month, $monthinfo['lastday'],23,59,59);
    $monthinfo['lastdaytimestamp'] = $timestamp; //23:59:59pm

    return $monthinfo;
}

/**
* @return hours (in decimal) for this defined period
*
*/
function get_hours_this_period($userid, $courseid, $start, $end){

    $units = get_split_units($start, $end, $userid, $courseid);

    $config = get_timetracker_config($courseid);


    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $total = 0;
    foreach($units as $unit){
        $total += round_time($unit->timeout - $unit->timein, $round);
    }

    return get_hours($total, $courseid);
}


/**
* @return hours (in decimal) for this month
*
*/
function get_hours_this_month($userid, $courseid, $month = -1, $year = -1,
    $officialonly = false){
    $currtime = usergetdate(time());
    if($month == -1 || $year == -1){
        $units = get_split_month_work_units($userid, $courseid,
            $currtime['mon'], $currtime['year']);
    } else {
        $units = get_split_month_work_units($userid, $courseid,
            $month, $year);
    }

    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $total = 0;
    foreach($units as $unit){
        if($officialonly){
            if($unit->submitted)
                $total += round_time($unit->timeout - $unit->timein, $round);
        } else {
            $total += round_time($unit->timeout - $unit->timein, $round);
        }
    }
    return get_hours($total, $courseid);
}

/**
* @return earnings (in dollars) for this calendar year
*
*/
function get_earnings_this_year($userid, $courseid, $time=0, $officialonly=false){

    
    if($time == 0){
        $time = time();
    }

    $currtime = usergetdate($time);

    $firstofyear = make_timestamp($currtime['year']); //defaults to jan 1 midnight
    //$endofyear = make_timestamp($currtime['year'], 12, 31, 23, 59, 59);

    $units = get_split_units($firstofyear, $time, $userid, $courseid);

    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $earnings = 0;
    foreach($units as $unit){
        if($officialonly){
            if($unit->submitted){
                $hours = round_time($unit->timeout - $unit->timein, $round);
                $hours = round($hours/3600, 3);
                $earnings += $hours * $unit->payrate;
            }
        } else {
            $hours = round_time($unit->timeout - $unit->timein, $round);
            $hours = round($hours/3600, 3);
            $earnings += $hours * $unit->payrate;
        }
    }

    return round($earnings, 2);
}

/**
* @return hours (in decimal) for this calendar year
*
*/
function get_hours_this_year($userid, $courseid, $time=0, $officialonly = false){

    if($time == 0){
        $time = time();
    }

    $currtime = usergetdate($time);

    $firstofyear = make_timestamp($currtime['year']); //defaults to jan 1 midnight
    //$endofyear = make_timestamp($currtime['year'], 12, 31, 23, 59, 59);

    $units = get_split_units($firstofyear, $time, $userid, $courseid);

     
    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $total = 0;
    foreach($units as $unit){
        if($officialonly){ 
            if($unit->submitted)
                $total += round_time($unit->timeout - $unit->timein, $round);
        } else {
            $total += round_time($unit->timeout - $unit->timein, $round);
        }
    }
    return get_hours($total, $courseid);
}

/**
*
* @return array 'termstart' and 'termend'
*/
function get_term_boundaries($courseid, $time = 0){
    global $DB;
    if($time == 0) {
        $currtime = time();
    } else {
        $currtime = $time;
    }
    $year = date("Y");

    $terms = $DB->get_records('block_timetracker_term',
        array('courseid'=>$courseid), 'month, day');

    $termstart = 0;
    $termend = 0;
    if($terms){

        $term_times = array();
        $counter = 0;
        foreach($terms as $term){
            //XXX TODO Replace with user time-zone specific Moodle function
            $tstart = mktime(0,0,0, $term->month, $term->day, $year);
            $term_times[] = $tstart; 
            if($counter == 0){
                //XXX TODO Replace with user time-zone specific Moodle function
                $term_times[] = mktime(0,0,0, $term->month, $term->day, $year+1);
            }
            $counter++;
        }
    
        sort($term_times);
    
        foreach($term_times as $termtime){
            if($currtime < $termtime){
                $termend = $termtime - 1;
                break;
            }
            $termstart = $termtime;
        }
    }

    $boundaries = array('termstart'=>$termstart,'termend'=>$termend);
    return $boundaries;
}


/**
* @return hours (in decimal) for the current term
*
*/
function get_hours_this_term($userid, $courseid=-1, $officialonly=false){

    $boundaries = get_term_boundaries($courseid);
    
    $units = get_split_units($boundaries['termstart'], $boundaries['termend'],
        $userid, $courseid);

    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $total = 0;
    foreach($units as $unit){
        if($officialonly){
            if($unit->submitted)
                $total += round_time($unit->timeout - $unit->timein, $round);
        } else {
            $total += round_time($unit->timeout - $unit->timein, $round);
        }
    }
    return get_hours($total, $courseid);
}

function get_earnings_this_term($userid, $courseid, $time=0, $officialonly=false){

    $boundaries = get_term_boundaries($courseid, $time);
    
    /** ISSUE HERE **/
    /*
        If the units are split, doesn't that mess up the earnings?
        TODO XXX
    */
    $units = get_split_units($boundaries['termstart'], $boundaries['termend'],
        $userid, $courseid);

    if(!$units) return 0;
    $round = get_rounding_config($courseid);
    $earnings = 0;
    foreach($units as $unit){
        if($officialonly){
            if($unit->submitted){
                $hours = round_time($unit->timeout - $unit->timein);
                $hours = round($hours/3600, 3);
                $earnings += $hours * $unit->payrate;
            }
        } else {
            $hours = round_time($unit->timeout - $unit->timein);
            $hours = round($hours/3600, 3);
            $earnings += $hours * $unit->payrate;
        }
    }
    return round($earnings, 2);
}

/**
* We need
* this month
* this term
* year to date
* total
*/

/**
* Yuck. Highly ineffecient.
* Gives an array with worker stats:
* $stats['totalhours']
* $stats['monthhours'
* $stats['yearhours']
* $stats['termhours']
* $stats['totalearnings'] 
* $stats['monthearnings']
* $stats['yearearnings']
* $stats['termearnings']
*
* $stats['officialtotalhours']
* $stats['officialmonthhours'
* $stats['officialyearhours']
* $stats['officialtermhours']
* $stats['officialtotalearnings'] 
* $stats['officialmonthearnings']
* $stats['officialyearearnings']
* $stats['officialtermearnings']
* @return an array with useful values
*/
function get_worker_stats($userid, $courseid){
    global $DB;

    $stats['totalhours'] = get_total_hours($userid, $courseid);
    $stats['monthhours'] = get_hours_this_month($userid, $courseid);
    $stats['yearhours'] = get_hours_this_year($userid, $courseid);
    $stats['termhours'] = get_hours_this_term($userid, $courseid);

    $stats['officialtotalhours'] = 
        get_total_hours($userid, $courseid, true);
    $stats['officialmonthhours'] = 
        get_hours_this_month($userid, $courseid, -1, -1, true);
    $stats['officialyearhours'] = 
        get_hours_this_year($userid, $courseid, 0, true);
    $stats['officialtermhours'] = 
        get_hours_this_term($userid, $courseid, true);

    $stats['totalearnings'] = get_total_earnings($userid, $courseid);
    $stats['monthearnings'] =get_earnings_this_month($userid, $courseid);
    $stats['yearearnings'] = get_earnings_this_year($userid, $courseid);
    $stats['termearnings'] = get_earnings_this_term($userid, $courseid);

    $stats['officialtotalearnings'] = 
        get_total_earnings($userid, $courseid, true);
    $stats['officialmonthearnings'] =
        get_earnings_this_month($userid, $courseid, -1, -1, true);
    $stats['officialyearearnings'] = 
        get_earnings_this_year($userid, $courseid, 0, true);
    $stats['officialtermearnings'] = 
        get_earnings_this_term($userid, $courseid, 0, true);



    return $stats; 
}

/**
* @see get_worker_stats
* @return an object array like this:
Array{
    [userid as index] => stdClassObject
        {
            [id] = TT user id
            [mdluserid] = moodle user id
            .
            .  (all other workerinfo fields
            .
            [totalhours] = 
            [monthhours] = 
            .
            . (all other from get_worker_stats())
            .
        }
    [next userid as index] => stdClassObject
        {
            etc
}
*/
/**
    Returns all of the worker stats for each worker in an array for a given course
    @param $courseid the Moodle courseid 
    @param $showdeleted doesn't show deleted workers by default
    @param $hasmaxtermearnings value of 0 shows workers that have a maxtermearnings of 0.
    value of 1 means they have a maxterm of > 0. -1 and this option is ignored.

    @return an array of workers and their stats
*/
function get_workers_stats($courseid, $showdeleted=false, $hasmaxtermearnings=-1){
    global $DB; 

    $opts = 'courseid='.$courseid;
    if(!$showdeleted){
        $opts .= ' AND deleted = 0';
    }

    if($hasmaxtermearnings == 0){
        $opts .= ' AND maxtermearnings = 0';
    } else if ($hasmaxtermearnings == 1){
        $opts .= ' AND maxtermearnings > 0';
    }

    $workers = $DB->get_records_select('block_timetracker_workerinfo',
        $opts, null, 'lastname ASC, firstname ASC');
    
    if(!$workers) return null;
    $round = get_rounding_config($courseid);
    $workerstats = array();
    foreach($workers as $worker){
        
        //XXX this is bad; multiple DB calls. Should do all in one call for efficiency
        $stats = get_worker_stats($worker->id, $courseid);
        foreach($stats as $stat=>$val){
            $worker->$stat = $val;
        }

        $lastunit = $DB->get_records('block_timetracker_workunit',
            array('userid'=>$worker->id, 'courseid'=>$courseid), 'timeout DESC LIMIT 1');
        $lu = '';
        if(!$lastunit){
            $lu = 'N/A';
        }
        foreach($lastunit as $u){
            $hours = round_time($u->timeout - $u->timein, $round);
            $hours = round($hours/3600, 3);
            $lu = 
                userdate($u->timein,get_string('simpledate','block_timetracker'));
                //' for '.
                //number_format($hours, 2).' hours';
        }

        $worker->lastunit = $lu;
        $workerstats[$worker->id] = $worker;
    }

    return $workerstats;
}



/**
* XX TODO document this function
* @return an array of config items for this course;
*/
function get_timetracker_config($courseid){
    global $DB;
    $config = array();
    $confs = $DB->get_records('block_timetracker_config',array('courseid'=>$courseid));
    foreach ($confs as $conf){
        $key = preg_replace('/block\_timetracker\_/','', $conf->name);
        $config[$key] = $conf->value;
    }

    return $config;
}

function unenroll_worker($courseid, $mdluserid){

    //get the FROM course context
    $context = get_context_instance(CONTEXT_COURSE, $courseid);

    //un-enroll from first course, if enrolled.
    if(is_enrolled($context, $mdluserid)){
        $manual = enrol_get_plugin('manual');
        $instances = enrol_get_instances($courseid, false);
        foreach($instances as $instance){
                if($instance->enrol == 'manual'){
                    $winner = $instance;
                    break;
                }
        }

        if(isset($winner)){
            $manual->unenrol_user($winner, $mdluserid);
        } else {
            error_log("Cannot unenroll $worker->firstname $worker->lastname\n");
            return false;
        }
    } else {
        return true; //not enrolled
        //echo "$worker->firstname $worker->lastname is NOT enrolled in FROM course\n";
        //user is not enrolled, so do nothing.
    }
    return true;
}

function enroll_worker($courseid, $mdluserid){
    //enroll in course, if NOT enrolled
    if(!is_enrolled($context, $mdluserid)){
        $manual = enrol_get_plugin('manual');

        $instances = enrol_get_instances($courseid, false);
        foreach($instances as $instance){
                if($instance->enrol == 'manual'){
                    $winner = $instance;
                    break;
                }
        }

        if(isset($winner)){
            $roleid = $DB->get_field('role', 'id', array(
                'archetype'=>'student'));
            if($roleid)
                $manual->enrol_user($winner, $mdluserid, $roleid, time());
            else
                $manual->enrol_user($winner, $mdluserid, 5, time());
        }else{
            echo "Cannot enroll $worker->firstname $worker->lastname\n";
        }
    } else {
        //do nothing; already enrolled
    }
}


/**
 * Copied from moodlelib.php to addCCs
 * Added by martygilbertgmailcom
 * Send an email to a specified user
 *
 * @global object
 * @global string
 * @global string IdentityProvider(IDP) URL user hits to jump to mnet peer.
 * @uses SITEID
 * @param stdClass $user  An array of  {@link $USER} objects
 * @param stdClass $from A {@link $USER} object
 * @param and array of {@link $USER} objects to add to CC line
 * @param string $subject plain text subject line of the email
 * @param string $messagetext plain text version of the message
 * @param string $messagehtml complete html version of the message (optional)
 * @param string $attachment a file on the filesystem, relative to $CFG->dataroot
 * @param string $attachname the name of the file (extension indicates MIME)
 * @param bool $usetrueaddress determines whether $from email address should
 *          be sent out. Will be overruled by user profile setting for maildisplay
 * @param string $replyto Email address to reply to
 * @param string $replytoname Name of reply to recipient
 * @param int $wordwrapwidth custom word wrap width, default 79
 * @return bool Returns true if mail was sent OK and false if there was an error.
 */
function email_to_user_wcc($users, $from, $cc=null, $subject, $messagetext, $messagehtml='', $attachment='', $attachname='', $usetrueaddress=true, $replyto='', $replytoname='', $wordwrapwidth=79) {

    global $CFG;

    $temprecipients = array();
    $mail = get_mailer();

    //add all of the valid users to send to multiple on the To: line
    foreach($users as $user){
        if (empty($user) || empty($user->email)) {
            $nulluser = 'User is null or has no email';
            error_log($nulluser);
            if (CLI_SCRIPT) {
                mtrace('Error: blocks/timetracker_admin/lib.php email_to_user_wcc(): '.
                    $nulluser);
            }
            return false;
        }
    
        if (!empty($user->deleted)) {
            // do not mail deleted users
            $userdeleted = 'User is deleted';
            error_log($userdeleted);
            if (CLI_SCRIPT) {
                mtrace('Error: blocks/timetracker_admin/lib.php email_to_user_wcc(): '.
                    $userdeleted);
            }
            return false;
        }
    
        if (!empty($CFG->noemailever)) {
            // hidden setting for development sites, set in config.php if needed
            $noemail = 'Not sending email due to noemailever config setting';
            error_log($noemail);
            if (CLI_SCRIPT) {
                mtrace('Error: blocks/timetracker_admin/lib.php email_to_user_wcc(): '.$noemail);
            }
            return true;
        }
    
        if (!empty($CFG->divertallemailsto)) {
            $subject = "[DIVERTED {$user->email}] $subject";
            $user = clone($user);
            $user->email = $CFG->divertallemailsto;
        }
    
        // skip mail to suspended users
        if ((isset($user->auth) && $user->auth=='nologin') or 
            (isset($user->suspended) && $user->suspended)) {
            return true;
        }
    
        if (!validate_email($user->email)) {
            // we can not send emails to invalid addresses - it might 
            //create security issue or confuse the mailer
            $invalidemail = "User $user->id (".fullname($user).
                ") email ($user->email) is invalid! Not sending.";
            error_log($invalidemail);
            if (CLI_SCRIPT) {
                mtrace('Error: blocks/timetracker_admin/lib.php email_to_user_wcc(): '.
                    $invalidemail);
            }
            return false;
        }
    
        if (over_bounce_threshold($user)) {
            $bouncemsg = "User $user->id (".fullname($user).
                ") is over bounce threshold! Not sending.";
            error_log($bouncemsg);
            if (CLI_SCRIPT) {
                mtrace('Error: blocks/timetracker_admin/lib.php email_to_user_wcc(): '.
                    $bouncemsg);
            }
            return false;
        }
    
        // If the user is a remote mnet user, parse the email text for URL to the
        // wwwroot and modify the url to direct the user's browser to login at their
        // home site (identity provider - idp) before hitting the link itself
        if (is_mnet_remote_user($user)) {
            require_once($CFG->dirroot.'/mnet/lib.php');
    
            $jumpurl = mnet_get_idp_jump_url($user);
            $callback = partial('mnet_sso_apply_indirection', $jumpurl);
    
            $messagetext = preg_replace_callback("%($CFG->wwwroot[^[:space:]]*)%",
                    $callback,
                    $messagetext);
            $messagehtml = 
                preg_replace_callback("%href=[\"'`]($CFG->wwwroot[\w_:\?=#&@/;.~-]*)[\"'`]%",
                $callback,
                $messagehtml);
        }

        $temprecipients[] = array($user->email, fullname($user));
    
    } //end of foreach $users

    if (!empty($mail->SMTPDebug)) {
        echo '<pre>' . "\n";
    }

    $tempreplyto = array();

    $supportuser = generate_email_supportuser();

    // make up an email address for handling bounces
    if (!empty($CFG->handlebounces)) {
        $modargs = 'B'.base64_encode(pack('V',$user->id)).substr(md5($user->email),0,16);
        $mail->Sender = generate_email_processing_address(0,$modargs);
    } else {
        $mail->Sender = $supportuser->email;
    }

    if (is_string($from)) { // So we can pass whatever we want if there is need
        $mail->From     = $CFG->noreplyaddress;
        $mail->FromName = $from;
    } else if ($usetrueaddress and $from->maildisplay) {
        $mail->From     = $from->email;
        $mail->FromName = fullname($from);
    } else {
        $mail->From     = $CFG->noreplyaddress;
        $mail->FromName = fullname($from);
        if (empty($replyto)) {
            $tempreplyto[] = array($CFG->noreplyaddress, get_string('noreplyname'));
        }
    }

    if($cc && is_array($cc) && count($cc) > 0){
        //add them to the cc line
        foreach($cc as $copyto){
            // skip mail to suspended users
            if ((isset($copyto->auth) && $copyto->auth=='nologin') or 
                (isset($copyto->suspended) && $copyto->suspended)) {
                continue;
            }
    
            //double-check the email address
            if (!validate_email($copyto->email)) {
                continue;
            }

            //add to the CC line
            $mail->AddCC($copyto->email, fullname($copyto));
        }
    }

    if (!empty($replyto)) {
        $tempreplyto[] = array($replyto, $replytoname);
    }

    $mail->Subject = substr($subject, 0, 900);


    $mail->WordWrap = $wordwrapwidth;                   // set word wrap

    if (!empty($from->customheaders)) {                 // Add custom headers
        if (is_array($from->customheaders)) {
            foreach ($from->customheaders as $customheader) {
                $mail->AddCustomHeader($customheader);
            }
        } else {
            $mail->AddCustomHeader($from->customheaders);
        }
    }

    if (!empty($from->priority)) {
        $mail->Priority = $from->priority;
    }

    // Don't ever send HTML to users who don't want it
    if ($messagehtml && !empty($user->mailformat) && $user->mailformat == 1) { 
        $mail->IsHTML(true);
        $mail->Encoding = 'quoted-printable';           // Encoding to use
        $mail->Body    =  $messagehtml;
        $mail->AltBody =  "\n$messagetext\n";
    } else {
        $mail->IsHTML(false);
        $mail->Body =  "\n$messagetext\n";
    }

    if ($attachment && $attachname) {
        if (preg_match( "~\\.\\.~" ,$attachment )) {    // Security check for ".." in dir path
            $temprecipients[] = array($supportuser->email, fullname($supportuser, true));
            $mail->AddStringAttachment(
                'Error in attachment.  User attempted to attach a filename with a unsafe name.', 
                'error.txt', '8bit', 'text/plain');
        } else {
            require_once($CFG->libdir.'/filelib.php');
            $mimetype = mimeinfo('type', $attachname);
            $mail->AddAttachment($CFG->dataroot .'/'. $attachment, $attachname, 
                'base64', $mimetype);
        }
    }

    // Check if the email should be sent in an other charset then the default UTF-8
    if ((!empty($CFG->sitemailcharset) || !empty($CFG->allowusermailcharset))) {

        // use the defined site mail charset or eventually the one preferred by the recipient
        $charset = $CFG->sitemailcharset;
        if (!empty($CFG->allowusermailcharset)) {
            if ($useremailcharset = get_user_preferences('mailcharset', '0', $user->id)) {
                $charset = $useremailcharset;
            }
        }

        // convert all the necessary strings if the charset is supported
        $charsets = get_list_of_charsets();
        unset($charsets['UTF-8']);
        if (in_array($charset, $charsets)) {
            $mail->CharSet  = $charset;
            $mail->FromName = textlib::convert($mail->FromName, 'utf-8', strtolower($charset));
            $mail->Subject  = textlib::convert($mail->Subject, 'utf-8', strtolower($charset));
            $mail->Body     = textlib::convert($mail->Body, 'utf-8', strtolower($charset));
            $mail->AltBody  = textlib::convert($mail->AltBody, 'utf-8', strtolower($charset));

            foreach ($temprecipients as $key => $values) {
                $temprecipients[$key][1] = textlib::convert($values[1], 
                    'utf-8', strtolower($charset));
            }
            foreach ($tempreplyto as $key => $values) {
                $tempreplyto[$key][1] = textlib::convert($values[1], 
                'utf-8', strtolower($charset));
            }
        }
    }

    foreach ($temprecipients as $values) {
        $mail->AddAddress($values[0], $values[1]);
    }
    foreach ($tempreplyto as $values) {
        $mail->AddReplyTo($values[0], $values[1]);
    }

    if ($mail->Send()) {
        set_send_count($user);
        if (!empty($mail->SMTPDebug)) {
            echo '</pre>';
        }
        return true;
    } else {
        add_to_log(SITEID, 'library', 'mailer', qualified_me(), 'ERROR: '. $mail->ErrorInfo);
        if (CLI_SCRIPT) {
            mtrace('Error: blocks/timetracker_admin/lib.php email_to_user_wcc(): '.
                $mail->ErrorInfo);
        }
        if (!empty($mail->SMTPDebug)) {
            echo '</pre>';
        }
        return false;
    }
}
