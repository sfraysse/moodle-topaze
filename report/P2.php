<?php

/* * *************************************************************
 *  This script has been developed for Moodle - http://moodle.org/
 *
 *  You can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
  *
 * ************************************************************* */

// Includes
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
require_once($CFG->dirroot.'/mod/topaze/report/reportlib.php');

// Params
$id = required_param('id', PARAM_INT); 
$userid = required_param('userid', PARAM_INT); 
$format  = optional_param('format', 'lms', PARAM_ALPHA);  // 'lms', 'csv', 'html', 'xls'

// Useful objects and vars
$cm = get_coursemodule_from_id('topaze', $id, 0, false, MUST_EXIST);
$course = $DB->get_record("course", array("id"=>$cm->course), '*', MUST_EXIST);
$activity = $DB->get_record("topaze", array("id"=>$cm->instance), '*', MUST_EXIST);
$sco = $DB->get_record("scormlite_scoes", array("id"=>$activity->scoid), '*', MUST_EXIST);

//
// Page setup 
//

$context = context_course::instance($course->id); // KD2014 - 2.6 compliance
require_login($course->id, false, $cm);
require_capability('mod/scormlite:viewotherreport', $context);
$url = new moodle_url('/mod/topaze/report/P2.php', array('id'=>$id, 'userid'=>$userid));
if ($format == 'lms') $PAGE->set_url($url);

//
// Print the page
//

if ($format == 'lms') $titlelink = topaze_report_get_link_P1($cm->id);
else $titlelink = null;
if ($format == 'lms') $title = topaze_report_print_activity_header($cm, $activity, $course, $titlelink, $userid);

//
// Fetch data
//

// Data
$attempts = topaze_report_get_user_attempts($sco->id, $userid);
if (empty($attempts)) {
    echo '<p>'.get_string('noreportdata', 'scormlite').'</p>';
} else {
    $manifest = topaze_report_get_manifest($sco->id);
    topaze_report_populate_attempt_results($attempts, $sco->id, $manifest);
    $withindic = isset($manifest->mainindicator);

    //
    // Print table
    //
    
    // Cols
    $cols = array('attempt', 'status', 'start', 'last', 'time');
    if ($withindic) $cols[] = 'indicator';
		
    // Headers
    if ($format == 'lms') {
        $headers = array(get_string('attemptcap', 'scormlite'), get_string('status', 'scormlite'), get_string('started', 'scormlite'), get_string('last', 'scormlite'), get_string('time', 'scormlite'));
        if ($withindic) $headers[] = get_string('result', 'topaze').'*';
    } else {
        $headers = array(get_string('attemptcsv', 'scormlite'), get_string('statuscsv', 'scormlite'), get_string('startedcsv', 'scormlite'), get_string('lastcsv', 'scormlite'), get_string('timecsv', 'scormlite'));
        if ($withindic) $headers[] = get_string('resultcsv', 'topaze');
    }

    // Define table object
    $table = new flexible_table('mod-scormlite-report');
    $table->define_columns($cols);
    $table->define_headers($headers);
    $table->define_baseurl($url);

    // Presentation
    //$table->sheettitle = ''; // workaround to avoid moodle table crash when using exporter
    $exporter = new scormlite_table_lms_export_format();
    if ($format == 'csv') $exporter = new scormlite_table_csv_export_format();
    else $exporter = new scormlite_table_lms_export_format();
    $table->export_class_instance($exporter);

    // Styles
    $table->column_class('attempt', 'attempt');
    $table->column_class('status', 'status');
    $table->column_class('start', 'start');
    $table->column_class('last', 'last');
    $table->column_class('time', 'time');
    if ($withindic) $table->column_class('indicator', 'indicator');
    
    // Setup
    $table->setup();

    // Fill
    $table->start_output();
    $title = '';
    foreach ($attempts as $i => $attempt) {
        $row = array();
        $attemptno = $i + 1;
        $row[] = $attemptno;
        if ($format == 'lms') {
            if ($attempt->status == 'completed' || $attempt->status == 'incomplete') {
                $urlP3 = new moodle_url('/mod/topaze/report/P3.php', array('id'=>$id, 'userid'=>$userid, 'attempt'=>$attemptno));
                $status = '<a href="'.$urlP3.'">'.get_string($attempt->status, 'scormlite').'</a>';            
            } else {
                $status = get_string('started', 'topaze');            
            }
        } else {
            if ($attempt->status == 'completed' || $attempt->status == 'incomplete') {
                $status = get_string($attempt->status.'csv', 'scormlite');            
            } else {
                $status = get_string('startedcsv', 'topaze');            
            }
        }
		/* KD2015 - Version 2.6.4 */
		if (isset($attempt->error)) $status .= '<br>'.$attempt->error;
		$row[] = $status;
		
        $row[] = userdate($attempt->start, get_string('strftimedatetimeshort', 'scormlite'));
        $row[] = userdate($attempt->finish, get_string('strftimedatetimeshort', 'scormlite'));
        $row[] = scormlite_format_duration_for_csv($attempt->total_time);
        if ($withindic) {
            if (isset($attempt->indicator->value)) $row[] = $attempt->indicator->value;
            else $row[] = '';
        }
        $table->add_data($row);
    }
    // The end	
    $table->finish_output();
    
    // Legend
    if ($format == 'lms' && $withindic) {
        echo '<p>*'.$manifest->mainindicator->title.'</p>';
    }
    
    // Export buttons
    if ($format == 'lms') {
        scormlite_print_exportbuttons(array('csv'=>$url));
    }

}

//
// The end
//

echo $OUTPUT->footer();

?>