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
$url = new moodle_url('/mod/topaze/report/P5.php', array('id'=>$id));
if ($format == 'lms') $PAGE->set_url($url);

//
// Print the page
//

if ($format == 'lms') $title = topaze_report_print_activity_header($cm, $activity, $course);

//
// Fetch data
//

// Data
$users = array();
$userids = scormlite_report_populate_users_by_tracks($users, $course->id, $sco->id);
if (empty($users)) {
    echo '<p>'.get_string('noreportdata', 'scormlite').'</p>';
} else {
    $manifest = topaze_report_get_manifest($sco->id);

    //
    // Print table
    //
    
    // Cols
    $cols = array('userid', 'lastname', 'firstname', 'attempt', 'title', 'type', 'time', 'tracking');
	
    // Headers
    if ($format == 'lms') {
		$headers = array('UserId', get_string('lastname', 'topaze'), get_string('firstname', 'topaze'), get_string('attemptcap', 'scormlite'), 
			get_string('title', 'scormlite'), get_string('type', 'topaze'), get_string('time', 'scormlite'), get_string('tracking', 'topaze'));
    } else {
		$headers = array('UserId', get_string('lastnamecsv', 'topaze'), get_string('firstnamecsv', 'topaze'), get_string('attemptcsv', 'scormlite'), 
			get_string('title', 'scormlite'), get_string('type', 'topaze'), get_string('time', 'scormlite'), get_string('tracking', 'topaze'));
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
    $table->column_class('lastname', 'lastname');
    $table->column_class('firstname', 'firstname');
    $table->column_class('attempt', 'attempt');
	$table->column_class('title', 'title');
	$table->column_class('type', 'type');
	$table->column_class('time', 'time');
	$table->column_class('tracking', 'tracking');

    // Setup
    $table->setup();

    // Fill
    $table->start_output();
    foreach ($users as $userid => $user) {

		// Get attempts		
		$attempts = topaze_report_get_user_attempts($sco->id, $userid);
		if (empty($attempts)) continue;

		foreach ($attempts as $i => $attempt) {

			// Attempt
			$attemptno = $i + 1;

			// Get attempt data
			$tracks = topaze_report_get_user_attempt($sco->id, $user->id, $attemptno);
			$topaze = topaze_report_get_topaze_json($tracks->suspend_data);
			$steps = topaze_report_get_user_steps($topaze, $manifest, false);
			
			foreach ($steps as $step) {
				$row = array();

				// User & Attempt
				$row[] = $user->id;
				$row[] = $user->lastname;
				$row[] = $user->firstname;
				$row[] = $attemptno;
				
				if (is_array($step)) {

					// 3 periods
					$time = '';
					if ($format == 'lms') $sep = '<br>';
					else $sep = ', ';
					foreach ($step as $type => $part) {
						if (isset($part->time)) {
							$time .= get_string($type, 'topaze').': '.scormlite_format_duration_from_ms_for_csv($part->time).$sep;
						}
					}
					$time = substr($time, 0, -strlen($sep));
					$step = $part;

				} else {

					// Single time
					if (isset($step->time)) {
						$time = scormlite_format_duration_from_ms_for_csv($step->time);
					} else {
						$time = '';
					}
				}

				// Step data
				$row[] = $step->title;
				$row[] = get_string($step->type, 'topaze');
				$row[] = $time;
				if ($step->tracking) $row[] = get_string('important', 'topaze');
				else $row[] = '';

				$table->add_data($row);
			}
		}
    }
    // The end	
    $table->finish_output();

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