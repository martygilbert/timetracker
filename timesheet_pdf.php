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
 * @package    Block
 * @subpackage TimeTracker
 * @copyright  2011 Marty Gilbert & Brad Hughes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/blocks/timetracker/lib.php');
require_once($CFG->dirroot.'/lib/tcpdf/tcpdf.php');


function generate_pdf_from_timesheetid($timesheetid, $userid, 
    $courseid, $method = 'I', $base=''){

    global $DB, $CFG;

    $units = $DB->get_records('block_timetracker_workunit', array('userid'=>$userid,
        'timesheetid'=>$timesheetid), 'timein ASC');

    if($units){

        $start = reset($units);
        $mondaybefore = strtotime("last Monday", strtotime('tomorrow',$start->timein));
        //error_log('From: '.userdate($mondaybefore, "%D %H:%M:%S"));
        $end = end($units);
        $sundayafter = strtotime("next Monday", 
            strtotime('tomorrow', $end->timeout)) - 1; //Sun @ 23:59:59
        //error_log('To: '.userdate($sundayafter, "%D %H:%M:%S"));

        return generate_pdf($mondaybefore, $sundayafter, 
        //return generate_pdf($start->timein, $end->timeout, 
            $userid, $courseid, $method, $base, $timesheetid);

    /*
    } else {
        print_error('invalidtimesheetid', 'block_timetracker', 
            $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$courseid);
    */
    }

}

function generate_pdf($start, $end, $userid, $courseid, $method = 'I', 
    $base='', $timesheetid=-1, $unsignedonly=false){
    global $CFG,$DB;

    $htmlpages = generate_html($start, $end, $userid, $courseid, $timesheetid,
        $unsignedonly);

    // Collect Data
    $conf = get_timetracker_config($courseid);

    $month = userdate($start, "%m");
    $year = userdate($start, "%Y");

    $workerrecord = $DB->get_record('block_timetracker_workerinfo', 
        array('id'=>$userid));

    if(!$workerrecord){
        print_error('usernotexist', 'block_timetracker',
            $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$courseid);
    }

    // ********** BEGIN PDF ********** //
    // Create new PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $fn = $year.'_'.($month<10?'0'.$month:$month).'Timesheet_'.
        substr($workerrecord->firstname,0,1).
        $workerrecord->lastname. '_'.$workerrecord->mdluserid;
    
    // Set Document Data
    $pdf->setCreator(PDF_CREATOR);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetCellPadding(0);
    $pdf->SetTitle($fn);
    $pdf->SetAuthor('TimeTracker');
    $pdf->SetSubject(' ');
    $pdf->SetKeywords(' ');
    
    // Remove Default Header/Footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    foreach($htmlpages as $page){
        $pdf->AddPage();
        $pdf->writeHTML($page);
    }
    
    //create the filename
    $fn .= '.pdf';


    //Close and Output PDF document
    //change the $method from 'I' to $method -- allow more than just a single file
    //to be created
    $pdf->Output($base.'/'.$fn, $method);
    return $fn;    
}

/**
    @return an array of HTML pages used for printing - one page per array item
*/
function generate_html($start, $end, $userid, $courseid, $timesheetid=-1,
    $unsignedonly=false){
    global $CFG,$DB;
    //error_log("in generate html. $timesheetid");

    $pages = array();

    $startstring = userdate($start, "%m%Y");
    $endstring = userdate($end, "%m%Y");
    $samemonth = ($startstring == $endstring);

	$df = get_string('dateformat', 'block_timetracker');
    $tf = get_string('timeformat','block_timetracker');

    //give formatted start/end strings
	$startdateformatted = userdate($start, $df);
	$enddateformatted = userdate($end, $df);
    

    $workerrecord = $DB->get_record('block_timetracker_workerinfo', 
        array('id'=>$userid));

    if(!$workerrecord){
        print_error('usernotexist', 'block_timetracker',
            $CFG->wwwroot.'/blocks/timetracker/index.php?id='.$courseid);
    }

    // Collect Data
    $conf = get_timetracker_config($courseid);

    $firstmonth= userdate($start, "%m");
    $firstyear = userdate($start, "%Y");
    $firstdate = userdate($start, "%d");

    $endmonth= userdate($end, "%m");
    $endyear = userdate($end, "%Y");
    $enddate = userdate($end, "%d");

    $overallhoursum = 0;
    $overalldollarsum = 0;

    //get all of the split units from start->end
    $units = get_split_units($start, $end, $userid, $courseid, $timesheetid, 'ASC',
        $unsignedonly); 

    //find the monday closest preceding the first unit
    if($units){
        $firstunit = reset($units); 
        //0Sun 6Sat
        $firstmondaymidnight = strtotime("last Monday", 
            strtotime('tomorrow', $firstunit->timein));
    } else {
        $firstunit = null;
        $firstmondaymidnight = 0;
    }

    $curr = $firstmondaymidnight;
    $earnings = get_earnings_info($workerrecord->id, $courseid, $start, $end, 1, $timesheetid,
        $unsignedonly);
    $weekhours = $earnings['weekhours'];


    /** Timesheet Record **/
    if($timesheetid != -1){
        $ts = $DB->get_record('block_timetracker_timesheet', 
            array('id'=>$timesheetid));
    }
    
    //print header
    // ********** HEADER ********** //
    $header = '
        <table style="margin-left: auto; margin-right: auto" cellspacing="0"'. 
        'cellpadding="0" width="540px">
        <tr>
            <td align="center"><font size="10"><b>'.
            $workerrecord->institution.'</b></font></td>
        </tr>
        <tr>
            <td align="center"><font size="10"><b>'.
            $startdateformatted.' to '.$enddateformatted.'</b></font>
            </td>
        </tr>
    </table>
    <hr style="height: 0.5px" />';
    
    //$pdf->writeHTML($htmldoc, true, false, false, false, '');

    if($timesheetid != -1)
        $ytd = get_earnings_this_year($userid, $courseid, $ts->workersignature, true);
    else 
        $ytd = get_earnings_this_year($userid, $courseid);
    
    // ********** WORKER AND SUPERVISOR DATA ********** //
    $header .= '
        <table style="margin-left: auto: margin-right: auto" cellspacing="0"'.
        'cellpadding="0" width="540px">
        <tr>
            <td><font size="8.5"><b>WORKER: '.strtoupper($workerrecord->lastname).', '
                .strtoupper($workerrecord->firstname).'<br />'
            .'ID: '.$workerrecord->idnum.'<br />'
            .'ADDRESS: '.$workerrecord->address.'<br />
            YTD Earnings: $ '.$ytd.'</b></font></td>
            <td><font size="8.5"><b>SUPERVISOR: '.$workerrecord->supervisor.'<br />'
            .'DEPARTMENT: '.$conf['department'].'<br />'
            .'POSITION: '.$workerrecord->position.'<br />'
            .'BUDGET: '.$workerrecord->budget.'</b></font></td>
        </tr>
    </table>
    <br />';

    $dayheader = '
    
        <table border="1" cellpadding="2px" width="540px" '.
        'style="margin-right: auto; margin-left: auto">
        <tr bgcolor="#C0C0C0">
            <td class="calendar" align="center"><font size="8"><b>Monday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Tuesday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Wednesday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Thursday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Friday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Saturday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Sunday</b></font></td>
            <td class="calendar" align="center"><font size="8"><b>Total Hours</b>'.
            '</font></td>
        </tr>
    ';

    $htmldoc = $header;
    $htmldoc .= $dayheader;

    $row = 0;
    $maxpages = 1;
    //$curr is monday of the week; $end is the last date possible
    //Control the number of pages

    $date = userdate($firstmondaymidnight, "%d");
    $dayofweek = 1; //monday

    if($firstunit == null){
        $pages[] = $header.'<br /><h1 style="text-align: center">'.
            'No units for this time period</h1><br /><br />';
    }

    while ($curr <= $end && sizeof($units) > 0) {
        //error_log("in main while() curr: $curr and end: $end");

        // ********** CALENDAR DAYS HEADER (Mon - Sun) ********** //
        //Arrays for dates and vals;
        $days = array();
        $vals = array();
    
        $currmonth = userdate($curr, "%b");
        $midnight =  $curr;
        $eod =  strtotime ("+1 day", $midnight);
        $eod -= 1;



        
        // ********** START THE TABLE AND DATA ********** //
        //write blank cells to catch up to the first day of the month
        /*
        $counter = 1;
        while($counter != $firstday){
            $counter++; 
            $days[] = '<td class="calendar" style="height: 10px">&nbsp;</td>';
            $vals[] = '<td class="calendar" style="height: 68px">&nbsp;</td>';
            $counter %= 7;
        }
        */

        $dayofweek = $dayofweek % 7;
            
        //a "week" - a row in the table - max of 6 of these, depending
        //on how the days fall
        for($row=0; $row < 6, sizeof($units) > 0; $row++){

            $hasunits = false;
            $weekhoursum = 0;
            do {
                $days[] = '<td class="calendar" style="height: 10px" align="center"><b>'.
                    $currmonth.' '.$date.'</b></td>';

                //begin to print work units
                
                // Print the data in the correct date blocks
                $wustr = "";
                if($units){
                    while(sizeof($units) > 0){
                        $unit = array_shift($units);
                        //error_log("Inpsecting unit $unit->id");
                        if($unit->timein < $eod && 
                            $unit->timein >= $midnight && 
                            $unit->timein >= $start && 
                            $unit->timeout <= $end){

                            $in = userdate($unit->timein, $tf);
                                
                            $out = userdate($unit->timeout, $tf);

                            //FIXMEFIXME!
                            if(array_key_exists('round', $conf) && $conf['round'] > 0)
                                $factor = ($conf['round']/2)-1;
                            else
                                $factor = 0;

                            if(($unit->timeout - $unit->timein) > $factor){ 
                                //WHAT IF NOT ROUNDED?
                                $wustr .= "In: $in<br />Out: $out<br />";
                                
                                $hasunits = true;
                            }
                        } else { 
                            //not on this day. put it back on!
                            array_unshift($units, $unit);
                            break;
                        }
                    }
                }

                $vals[] = '<td class="calendar"  style="height: 68px"><font size="7">'.
                    $wustr.'</font></td>';
                
                //if day of week = 0 (Sunday), copy value over and reset weekly sum to 0. 
                // Calculate total hours
                if($dayofweek == 0){ //Sunday
                    //what about no units that week?
                    //Print value in weekly totals column 
                    $days[] = '<td class="calendar" style="height: 10px">&nbsp;</td>';
                    if(!$hasunits) {
                            array_shift($weekhours);
                            $weeksum = '0';
                    }
                    else $weeksum = array_shift($weekhours);
                    $vals[] = 
                        '<td class="calendar" style="height: 68px" align="center">'.
                        '<font size="10"><b><br /><br />'.
                        $weeksum.'</b><br /></font></td>';
                } 

                $midnight = strtotime('+ 1 day', $midnight); //midnight
                $eod = strtotime('+ 1 day', $eod); //23:59:59
                
                $dayofweek++; 
                $dayofweek = $dayofweek % 7;

                $curr = strtotime('+1 day', $curr);
                $date = userdate($curr, "%d");

                $currmonth = userdate($curr, "%b");

            } while($dayofweek != 1);
        }//this is a single "row" or "week"

        //error_log("Row is $row");
        
        //construct the HTML string
        $page = 1;
        $maxpages = round(($row / 6) + 0.5);

        for($i = 0; $i < $row; $i++){
            $htmldoc.="\n<tr>\n";
            for($j=0; $j<8; $j++){
                $spot = $j + (8 * $i);
                if(isset($days[$spot]))
                    $htmldoc .= "\t".$days[$spot]."\n";    
                else
                    $htmldoc .= "\t".
                        '<td class="calendar" style="height: 10px">&nbsp;</td>'."\n";
            }
            $htmldoc.="\n</tr>\n";
        
            $htmldoc.="\n<tr>\n";
            for($j=0; $j<8; $j++){
                $spot = $j + (8 * $i);
                if(isset($vals[$spot]))
                    $htmldoc .= "\t".$vals[$spot]."\n";    
                else
                    $htmldoc .="\t".
                        '<td class="calendar" style="height: 68px">&nbsp;</td>'."\n";
            }
            $htmldoc.="\n</tr>\n";

            if(($i > 0 && $i % 6 == 0) || $i == ($row - 1)) { //six rows on a page
                //or last row ($i == $row - 1)
                
                $htmldoc .= '</table><br />';
                if($i != $row - 1) { //not the last page
                    $htmldoc .= '<br /><span style="text-align: right">Page '.
                        $page.' of '.$maxpages.'</span>';
                }

                $pages[] = $htmldoc;
                $page++;


                $htmldoc = $header;
                $htmldoc .= $dayheader;
            }
        }
    
        //$htmldoc .= '</table><br />';
    
        //$pdf->writeHTML($htmldoc, true, false, false, false, '');
    
    
        // ********** FOOTER TOTALS ********** //
        /*
        $htmldoc .= '
            <table border="1" cellpadding="5px" width="540px" '.
            'style="margin-left: auto; margin-right: auto">
        <tr>
            <td style="height: 25px"><font size="13"><b>Base Pay Rate</b></font>
            <br />
                <font size="10">$'.number_format($workerrecord->currpayrate, 2).'</font></td>
            <td style="height: 20px"><font size="13"><b>Total Hours/Earnings for '.
	 	        $monthinfo['monthname'].', '.$year.'</b></font><br /><font size="10">'.
                round($earnings['hours'], 3).' / $'.
		        round($earnings['earnings'], 2) .'</font></td>
        </tr></table><br />';
        //$pdf->writeHTML($htmldoc, true, false, false, false, '');

        $overalldollarsum += $earnings['earnings'];
        $overallhoursum += $earnings['hours'];
        */

        //here is a new page!!!
        //$pages[] = $htmldoc;
    }
    //what?
    end($pages);
    $key = key($pages);
    if(sizeof($pages)>0)
        $htmldoc = $pages[$key];
    else
        $htmldoc = "";

    /************ SUMMARY BLOCK *******************/    
    $htmldoc .= '
        <table border="1" cellpadding="5px" width="540px" '.
        'style="margin-left: auto; margin-right: auto">
    <tr>
        <td style="height: 25px"><font size="13"><b>Base Pay Rate</b></font>
        <br />
            <font size="10">$'.number_format($workerrecord->currpayrate, 2).'</font></td>
        <td style="height: 20px"><font size="13"><b>Totals for '.
            $firstmonth.'/'.$firstdate.'/'.$firstyear.' - '.
            $endmonth.'/'.$enddate.'/'.$endyear.'</b></font><br /><font size="10">'.
            round($earnings['hours'], 3).' / $'.
	        number_format(round($earnings['earnings'], 2),2) .'</font></td>
    </tr></table><br />';


    // ********** OVERALL TOTALS AND SIGNATURES********** //

    /*
    if(!$samemonth){
        $desc = '';
        if($timesheetid == -1){
            $desc = 
	            userdate($start, get_string('dateformat', 'block_timetracker')).
                ' to '.
	            userdate($end, get_string('dateformat', 'block_timetracker'));
        }

        $htmldoc .='
        <tr>
        <td colspan="2" style="height: 35px"><font size="13"><b>Total Hours/Earnings  '.
            $desc.
            '</b></font><br /><font size="10">'.
            round($overallhoursum, 3).' / $'.
	        round($overalldollarsum, 2) .'</font></td>
        </tr>';
    }
    */

    if($timesheetid != -1){
        $htmldoc .= '
            <table border="1" cellpadding="5px" width="540px" '.
            'style="margin-left: auto; margin-right: auto">';
        $datestr = get_string('datetimeformat', 'block_timetracker');
        $htmldoc .='
        <tr>
            <td style="height: 45px"><font size="13"><b>Worker Signature/Date</b></font>'.
            '<br />'.
            '<font size="8">Signed by '.$workerrecord->firstname.' '.
            $workerrecord->lastname.'<br />'.
            userdate($ts->workersignature, $datestr).
            '</font></td>'.
            '<td style="height: 45px"><font size="13"><b>Supervisor Signature/Date</b>'.
            '</font><br />'.
            '<font size="8">';
        if($ts->supervisorsignature != 0){
            $super = $DB->get_record('user', array('id'=>$ts->supermdlid));
            if(!$super) print_error('Supervisor does not exist');
            $htmldoc .= 'Signed by '.$super->firstname.' '.$super->lastname.'<br />'.
                userdate($ts->supervisorsignature, $datestr);
        } else {
            $htmldoc .= 'Awaiting supervisor signature';
        }

        $htmldoc .='
            </font></td>
        </tr>
        </table><br />';

    } else {
        /*
        $htmldoc .='
        
        <tr>
            <td style="height: 45px"><font size="13"><b>Worker Signature/Date</b></font></td>
            <td style="height: 45px"><font size="13"><b>Supervisor Signature/Date</b></font></td>
        </tr>
        */
        //$htmldoc .=' </table><br />';
    }
    $htmldoc .= '<br /><span style="text-align: right">Page '.
        $maxpages.' of '.$maxpages.'</span>';

    $pages[$key] = $htmldoc;

    return $pages;
}


?>
