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


//
// Headers
//

function topaze_report_get_link_P1($activityid) {
	global $CFG, $OUTPUT;
	$reporturl = $CFG->wwwroot.'/mod/topaze/report/P1.php?id='.$activityid;
	$strreport = get_string('P1', 'topaze');

	// SF2018 - Icons
	//$reportlink = '<a title="'.$strreport.'" href="'.$reporturl.'"><img src="'.$OUTPUT->pix_url('i/grades') . '" class="icon" alt="'.$strreport.'" /></a>';
	$reportlink = '<a title="'.$strreport.'" href="'.$reporturl.'">'.$OUTPUT->pix_icon('grades', $strreport, 'mod_topaze').'</a>';

	return $reportlink;
}
 
function topaze_report_get_link_P2($activityid, $userid) {
	global $CFG, $OUTPUT;
	$reporturl = $CFG->wwwroot.'/mod/topaze/report/P2.php?id='.$activityid.'&userid='.$userid;
	$strreport = get_string('P2', 'topaze');

	// SF2018 - Icons
	//$reportlink = '<a title="'.$strreport.'" href="'.$reporturl.'"><img src="'.$OUTPUT->pix_url('i/grades') . '" class="icon" alt="'.$strreport.'" /></a>';
	$reportlink = '<a title="'.$strreport.'" href="'.$reporturl.'">'.$OUTPUT->pix_icon('grades', $strreport, 'mod_topaze').'</a>';

	return $reportlink;
}
 
function topaze_report_print_activity_header($cm, $activity, $course, $titlelink = '', $userid = null, $subtitlelink = '', $attempt = null) {
    global $OUTPUT, $CFG, $DB;
    // Start
    $pagetitle = scormlite_print_header($cm, $activity, $course);
    // Tabs
    $playurl = "$CFG->wwwroot/mod/topaze/view.php?id=$cm->id";
    $reporturl = "$CFG->wwwroot/mod/topaze/report/P1.php?id=$cm->id";
    scormlite_print_tabs($cm, $activity, $playurl, $reporturl, 'report');
    // Title and description
	echo $OUTPUT->box_start('generalbox mdl-align');
    scormlite_print_title($cm, $activity, $titlelink);
    // User
    if (isset($userid)) {
        $user = $DB->get_record('user', array('id'=>$userid));
        $username =  $user->lastname." ".$user->firstname;
        echo '<h3>'.$username.' '.$subtitlelink.'</h3>';
    }
    // Attempt
    if (isset($attempt)) {
        echo '<h4>'.get_string('attemptcap', 'scormlite').' '.$attempt.'</h4>';
    }
    echo $OUTPUT->box_end();
    return $pagetitle;
}


//
// Get and populate data
//

function topaze_report_populate_activity_results(&$users, $userids, $scoid, $manifest = null) {
    if (!isset($manifest)) $manifest = topaze_report_get_manifest($scoid);
	foreach ($users as $userid => $user) {
        // First attempts
   		$timetracks = scormlite_get_sco_runtime($scoid, $userid, 1);
		$user->start = $timetracks->start;
        // Last attempt
		$timetracks = scormlite_get_sco_runtime($scoid, $userid);
		$user->last = $timetracks->finish;
        // Attempt number        
        $scotracks = scormlite_get_tracks($scoid, $userid);
		$user->attemptnb = $scotracks->attemptnb;
		
        // Topaze data
        $user->topaze = topaze_report_get_topaze_json($scotracks->suspend_data);
        $user->indicator = topaze_report_get_main_indicator($user->topaze, $manifest);
		
		/* KD2015 - Version 2.6.4 */
		if (is_string($user->topaze)) {
			$user->error = '<span style="color:#ff0000;">'.$user->topaze.'</span>';
		}
    }
}

function topaze_report_get_user_attempts($scoid, $userid) {
    $attempts = array();
    $attemptnb = scormlite_get_attempt_count($scoid, $userid);
	for ($i=0; $i<$attemptnb; $i++) {
        $attempts[] = topaze_report_get_user_attempt($scoid, $userid, $i+1);
    }
    return $attempts;
}

function topaze_report_get_user_attempt($scoid, $userid, $attempt) {
    $tracks = scormlite_get_tracks($scoid, $userid, $attempt);
    $time = scormlite_get_sco_runtime($scoid, $userid, $attempt);
    $tracks->start = $time->start;
    $tracks->finish = $time->finish;
    return $tracks;
}

function topaze_report_populate_attempt_results(&$attempts, $scoid, $manifest = null) {
    if (!isset($manifest)) $manifest = topaze_report_get_manifest($scoid);
    foreach($attempts as $attempt) {        
        $topaze = topaze_report_get_topaze_json($attempt->suspend_data);
        $attempt->indicator = topaze_report_get_main_indicator($topaze, $manifest);

		/* KD2015 - Version 2.6.4 */
		if (is_string($topaze)) {
			$attempt->error = '<span style="color:#ff0000;">'.$topaze.'</span>';
		}
    }
}

//
// Get Topaze manifest data
//

function topaze_report_get_manifest($scoid) {
    global $DB;
    $manifest = new stdClass();
    // Indicators
    $indicators = array();
    $records = $DB->get_records('topaze_indicators', array('scoid'=>$scoid));
    foreach($records as $record) {
        $indicator = new stdClass();
        $indicator->type = $record->type;
        $indicator->title = $record->title;
        $indicator->scope = 'local';
        $indicators[$record->manifestid] = $indicator;
    }
    $manifest->indicators = $indicators;
    // Main indicator
    $topaze = $DB->get_record('topaze', array('scoid'=>$scoid));
    if (!empty($topaze->mainindicator)) {
        $indicator = $manifest->indicators[$topaze->mainindicator];
        $mainindicator = new stdClass();
        $mainindicator->type = $indicator->type;
        $mainindicator->title = $indicator->title;
        $indicator->scope = 'global';
        $manifest->mainindicator = $mainindicator;
    }
    // Steps
    $steps = array();
    $records = $DB->get_records('topaze_steps', array('scoid'=>$scoid));
    foreach($records as $record) {
        $step = new stdClass();
        $step->type = $record->type;
        $step->title = $record->title;
        $step->tracking = $record->tracking;
        $steps[$record->manifestid] = $step;
    }
    $manifest->steps = $steps;
    return $manifest;
}

//
// Print the report export buttons
//

function topaze_report_print_exportbuttons($buttons, $class = 'mdl-align exportcommands') { 
	echo '<div class="'.$class.'">';
	foreach($buttons as $button) {
		$exporturl = new moodle_url($button['url'], array('format'=>$button['format']));
		if (isset($button['label'])) $label = $button['label'];
		else $label = get_string('export'.$button['format'], 'scormlite');
		echo '<input type="button" class="btn btn-default" value="'.$label.'" onClick="window.open(\''.$exporturl.'\');"/>';			
		echo '&nbsp;&nbsp;&nbsp;';
	}
	echo '</div>';
}

//
// Get Topaze suspend data
//

function topaze_report_get_topaze_json($suspend_data) {
	
	/* KD2015 - Version 2.6.4 */
	if (empty($suspend_data)) {
		return get_string('error_empty_suspend_data', 'topaze');
	}
	
    $suspend = $suspend_data;
    $suspend = str_replace("\\", "", $suspend); /* KD2015 - Version 2.6.5 - Patch: Remove all escape chars to avoid problems in history with some chars (':,) */
    $suspend = str_replace("\"Infinity\"", "Infinity", $suspend); /* KD2015 - Version 2.6.4 - Patch: Add quotes to Infinity indicator value (invalid JSON)  */
    $suspend = str_replace("Infinity", "\"Infinity\"", $suspend);
    $suspend_data = json_decode($suspend, true);
	
	/* KD2015 - Version 2.6.4 */
    if (!isset($suspend_data)) {
		return get_string('error_json_decode', 'topaze');
    }
	
    if (isset($suspend_data['topaze'])) {
        return $suspend_data['topaze'];
    }
}

function topaze_report_get_main_indicator($topaze_json, $manifest = null) {
	
	/* KD2015 - Version 2.6.4 */
    if (!isset($topaze_json) || !is_array($topaze_json)) return null;
	
    if (!isset($topaze_json) || !isset($topaze_json['globalindex'])) {
        return null;
    } else {
        $indicator = new stdClass();
        $first = reset($topaze_json['globalindex']);
        $indicator->id = key($topaze_json['globalindex']);
        $indicator->value = $first['v'];
        if (isset($manifest)) {
            $indicator->title = $manifest->indicators[$indicator->id]->title;
            $indicator->type = $manifest->indicators[$indicator->id]->type;
        }
        return $indicator;
    }
}

function topaze_report_get_user_indicators($topaze_json, $manifest) {
    if (!isset($topaze_json) || (!isset($topaze_json['globalindex']) && !isset($topaze_json['indexes']))) {
        return array();
    } else {
        $indicators = array();
        // Main indicator
        $mainindicator = topaze_report_get_main_indicator($topaze_json, $manifest);
        if (isset($mainindicator) && !empty($mainindicator->id)) $indicators[] = $mainindicator;  // Patch !!!!!!!!!!!!!!!!!!!!!!!!
        // Other indicators
        if (isset($topaze_json['indexes'])) {
            foreach($topaze_json['indexes'] as $id => $val) {
                $indicator = new stdClass();
                $indicator->id = $id;
                $indicator->value = $val['v'];
                $indicator->title = $manifest->indicators[$id]->title;
                $indicator->type = $manifest->indicators[$id]->type;
                if (!empty($indicator->id)) $indicators[] = $indicator;  // Patch !!!!!!!!!!!!!!!!!!!!!!!!
            }
        }
        return $indicators;
    }    
}

function topaze_report_get_user_steps($topaze_json, $manifest, $associative = true) {
    if (!isset($topaze_json) || (!isset($topaze_json['route']) && !isset($topaze_json['steps']))) {
        return array();
    } else {
        if (isset($topaze_json['route'])) $items = $topaze_json['route'];
        else $items = $topaze_json['steps'];
        $steps = array();
        foreach($items as $item) {
            $step = new stdClass();
            $step->id = $item['id'];
            $step->title = $manifest->steps[$step->id]->title;
            $step->type = $manifest->steps[$step->id]->type;
            $step->tracking = $manifest->steps[$step->id]->tracking;
            $step->time = $item['t'];
            if ($associative) {
                if (isset($item['partExo'])) {
                    if (!isset($steps[$step->id])) $steps[$step->id] = array();
                    $steps[$step->id][$item['partExo']] = $step;
                } else {
                    $steps[$step->id] = $step;
                }
            } else {
                if (isset($item['partExo'])) {
                    $step->title .= ' ('.get_string($item['partExo'], 'topaze').')';
                }
                $steps[] = $step;
            }
        }
        return $steps;
    }    
}

function topaze_report_get_manifest_steps($topaze_json, $manifest) {
    $steps = array();
    $manifest_steps = $manifest->steps;
    $user_steps = topaze_report_get_user_steps($topaze_json, $manifest);
    foreach($manifest_steps as $id => $step) {
        if (isset($user_steps[$id])) {
            $steps[$id] = $user_steps[$id];
        } else {
            $steps[$id] = $step;
        }
    }
    return $steps;
}

function topaze_report_has_important_steps($steps) {
    foreach($steps as $step) {
        if ($step->tracking) return true;
    }
    return false; 
}



