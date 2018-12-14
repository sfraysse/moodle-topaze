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


// Each module using scormlite must provide a file such as this one, providing the following functions

function topaze_get_activity_from_scoid($scoid) {
	global $DB;
	return $DB->get_record('topaze', array('scoid' => $scoid), '*', MUST_EXIST);
}

// Returns the activity completion

function topaze_is_activity_completed($userid, $activity) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
	$tracks = scormlite_get_tracks($activity->scoid, $userid);
	if ($tracks->completion_status == "completed") {
		return true;
	}
	return false;
}

// Returns the user grade for this activity or NULL if there is no grade to record
// SF2018 - Gradebook.

function topaze_get_grade($userid, $activity) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
	$tracks = scormlite_get_tracks($activity->scoid, $userid);
	if ($tracks->completion_status == "completed") {
		return (isset($tracks->score_raw) && is_numeric($tracks->score_raw) ? $tracks->score_raw : null);
	}
}

// Returns the grades for this activity
// SF2018 - Gradebook.

function topaze_get_grades($activity) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
    $grades = array();
    if ($usertracks = scormlite_get_tracks($activity->scoid)) {
        foreach ($usertracks as $userid => $tracks) {
			if ($tracks->completion_status == "completed" && isset($tracks->score_raw) && is_numeric($tracks->score_raw)) {
	            $grades[$userid] = $tracks->score_raw;
			}
        }
    }
	return $grades;
}


// Hook function called when a track is recorded
// SF2018 - Create a cmi.score.raw track based on the main indicator.

function topaze_record_track_hook($track) {
	global $CFG;
	if ($track->element == 'cmi.suspend_data') {
		require_once($CFG->dirroot.'/mod/topaze/report/reportlib.php');
		$json = topaze_report_get_topaze_json($track->value);
		$indicator = topaze_report_get_main_indicator($json);
		if (!is_null($indicator) && is_numeric($indicator->value)) {
			$val = round(floatval($indicator->value), 1);
			scormlite_insert_track($track->userid, $track->scoid, $track->attempt, 'cmi.score.raw', strval($val), 'topaze');
		}	
	}
}

