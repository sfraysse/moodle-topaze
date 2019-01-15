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


// Print possible actions (or nothing if not available)

function topaze_print_myactions($cm, $sco, $trackdata, $scormopen = true) {
	$res = topaze_get_myactions($cm, $sco, $trackdata, $scormopen);
	echo $res[0];
	return $res[1];
}
function topaze_get_myactions($cm, $sco, $trackdata, $scormopen = true) {
    global $USER;
	$html = '';
	$userid = $USER->id;
	$playerurl = new moodle_url('/mod/scormlite/player.php', array('scoid' => $sco->id));
	$achieved = ($trackdata->status == 'completed' && $trackdata->exit != 'suspend');
    $attemptnumber = scormlite_get_attempt_count($sco->id, $userid);
    $action = '';

    // SF2018 - Illimited attempts except for students
    $illimitedAccess = has_capability('mod/scormlite:viewotherreport', context_module::instance($cm->id));
    
	if ($achieved) {
		if (!isloggedin() || isguestuser()) {
			// Can be played by guests
			$action = 'start';
            $playerurl .= '&attempt=1';
			$html .= '<div class="actions"><a class="btn btn-primary" href="'.$playerurl.'\'">'.get_string("start", "topaze").'</a></div>'."\n";
        } else {
            // Start a new attempt
            $attemptmax = $sco->maxattempt;
            if ($attemptmax == 0 || $illimitedAccess || ($attemptnumber < $attemptmax)) {
                // Can start new attempt
                $action = 'newattempt';
                $playerurl .= '&attempt='.($attemptnumber+1);
                $html .= '<div class="actions"><a class="btn btn-primary" href="'.$playerurl.'\'">'.get_string("newattempt", "topaze").'</a></div>'."\n";
            }
		}
	} else if ($trackdata->status == 'notattempted' && ($scormopen || $illimitedAccess))  {
		// Can start
		$action = 'start';
        if ($attemptnumber == 0) $playerurl .= '&attempt=1';
        else $playerurl .= '&attempt='.$attemptnumber;
		$html .= '<div class="actions"><a class="btn btn-primary" href="'.$playerurl.'\'">'.get_string("start", "topaze").'</a></div>'."\n";
	} else if ($scormopen || $illimitedAccess) {
		$html .= '<div class="actions">'."\n";
		// Can resume
		$action = 'resume';
        $resumeurl = $playerurl.'&attempt='.$attemptnumber;
		$html .= '<a class="btn btn-primary" href="'.$resumeurl.'\'">'.get_string("resume", "topaze").'</a>'."\n";
        
		// Can restart
        $attemptmax = $sco->maxattempt;
        if ($attemptmax == 0 || $illimitedAccess || ($attemptnumber < $attemptmax)) {
            $action = 'resume,newattempt';
            $newattempt = $playerurl.'&attempt='.($attemptnumber+1);
            $html .= ' <a class="btn btn-primary" href="'.$newattempt.'\'">'.get_string("newattempt", "topaze").'</a>'."\n";
        }
        
        // End
		$html .= '</div>'."\n";
	}
	return array($html, $action);
}

// Parse the manifest file and record data in DB

function topaze_save_manifest_data($cmid, $topazeid, $scoid) {
    global $CFG, $DB;
	require_once($CFG->dirroot.'/mod/scorm/datamodels/scormlib.php');  // For xml2array

    // Get XML
	$context = context_module::instance($cmid); // KD2014 - 2.6 compliance
    $fs = get_file_storage();
    $manifest = $fs->get_file($context->id, 'mod_topaze', 'content', $scoid, '/', 'topaze.xml'); 
    $xmltext = $manifest->get_content();
    $pattern = '/&(?!\w{2,6};)/';
    $replacement = '&amp;';
    $xmltext = preg_replace($pattern, $replacement, $xmltext);
    $objXML = new xml2Array();
    $xml = $objXML->parse($xmltext);

    // Parse 1st level
    foreach ($xml[0]['children'] as $xmltop) {
        if ($xmltop['name'] == 'SCORE') {
            // TBD
        } else if ($xmltop['name'] == 'INDEXES') {
            list($indicators, $mainindic) = topaze_get_indicators($xmltop, $topazeid, $scoid);
        } else if ($xmltop['name'] == 'STEPS') {
            list($steps, $tracking) = topaze_get_steps($xmltop, $topazeid, $scoid);
        }
    }
    
    // Update DB
    $DB->set_field('topaze', 'mainindicator', $mainindic, array('scoid' => $scoid));
    $DB->set_field('topaze', 'pathtracking', $tracking, array('scoid' => $scoid));
    foreach ($indicators as $indic) {
        $DB->insert_record('topaze_indicators', $indic);
    }
    foreach ($steps as $step) {
        $DB->insert_record('topaze_steps', $step);
    }
}

// Get manifest indicators 

function topaze_get_indicators($xmlindics, $topazeid, $scoid) {
    $mainindic = '';
    $indicators = array();
    if (isset($xmlindics['children'])) { 
        foreach ($xmlindics['children'] as $xmlindic) {
            if ($xmlindic['name'] == 'GLOBALINDEX') {
                $mainindic = $xmlindic['attrs']['ID'];
            }
            $indic = new stdClass();
            $indic->topazeid = $topazeid;
            $indic->manifestid = $xmlindic['attrs']['ID'];
            $indic->type = $xmlindic['attrs']['TYPE'];
            $indic->title = $xmlindic['attrs']['TITLE'];
            $indic->scoid = $scoid;
            array_push($indicators, $indic);
        }
    }
    return array($indicators, $mainindic);
}

// Get manifest steps 

function topaze_get_steps($xmlsteps, $topazeid, $scoid)
{
    $steps = array();
    if (isset($xmlsteps['attrs']['ROUTE']) && $xmlsteps['attrs']['ROUTE'] == 'true') $tracking = 1;
    else $tracking = 0;
    foreach ($xmlsteps['children'] as $xmlstep) {
        $step = new stdClass();
        $step->topazeid = $topazeid;
        $step->manifestid = $xmlstep['attrs']['ID'];
        $step->type = $xmlstep['attrs']['TYPE'];
        $step->title = $xmlstep['attrs']['TITLE'];
        $step->scoid = $scoid;
        if (isset($xmlstep['attrs']['FOLLOWED']) && $xmlstep['attrs']['FOLLOWED'] == 'false') $step->tracking = 0;
        else $step->tracking = 1;
        array_push($steps, $step);
    }
    return array($steps, $tracking);
}

function topaze_update_manifest_data($cmid, $topazeid, $scoid) {
	global $DB;
	$DB->delete_records('topaze_indicators', array('scoid'=>$scoid));
	$DB->delete_records('topaze_steps', array('scoid'=>$scoid));
    topaze_save_manifest_data($cmid, $topazeid, $scoid);
}



