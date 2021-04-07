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


defined('MOODLE_INTERNAL') || die();
 
////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////
 
/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */ 
function topaze_supports($feature) {
	switch($feature) {
		case FEATURE_MOD_ARCHETYPE:				return MOD_ARCHETYPE_OTHER;  // Type of module (resource, activity or assignment)
		case FEATURE_BACKUP_MOODLE2:			return true;  // True if module supports backup/restore of moodle2 format
		case FEATURE_GROUPS:					return false; // True if module supports groups
		case FEATURE_GROUPINGS:					return false; // True if module supports groupings
		case FEATURE_GROUPMEMBERSONLY:			return true;  // True if module supports groupmembersonly
		case FEATURE_SHOW_DESCRIPTION:			return true; // True if module can show description on course main page
		case FEATURE_NO_VIEW_LINK:				return false; // True if module has no 'view' page (like label)
		case FEATURE_MOD_INTRO:					return true;  // True if module supports intro editor
		case FEATURE_COMPLETION_TRACKS_VIEWS:	return true; // True if module has code to track whether somebody viewed it
		case FEATURE_COMPLETION_HAS_RULES:		return false; // True if module has custom completion rules
		case FEATURE_MODEDIT_DEFAULT_COMPLETION:return false; // True if module has default completion
		case FEATURE_GRADE_HAS_GRADE:			return true; // True if module can provide a grade
		// Next ones should be checked
		case FEATURE_GRADE_OUTCOMES:			return false; // True if module supports outcomes
		case FEATURE_ADVANCED_GRADING:			return false; // True if module supports advanced grading methods
		case FEATURE_IDNUMBER:					return false; // True if module supports outcomes
		case FEATURE_COMMENT:					return false; // 
		case FEATURE_RATE:						return false; //  
		default: return null;
	}
} 
 
/**
 * Get icon mapping for font-awesome.
 * SF2018 - Added for 3.3 compatibility
 */
function mod_topaze_get_fontawesome_icon_map() {
    return [
        'mod_topaze:grades' => 'fa-table',
    ];
}
 
/**
 * Saves a new instance of the topaze into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $data An object from the form in mod_form.php
 * @param mod_topaze_mod_form $mform
 * @return int The id of the newly inserted topaze record
 */  
function topaze_add_instance($data, $mform=null) {
	global $DB, $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
	$transaction = $DB->start_delegated_transaction();
	{
		$scoid = scormlite_save_sco($data, $mform, $data->coursemodule, 'packagefile');
		$data->scoid = $scoid;
		$data->timemodified = time();
		$data->id = $DB->insert_record('topaze', $data);

		// Grades min and max
		list($grademin, $grademax) = topaze_save_manifest_data($data->coursemodule, $data->id, $scoid);
		$data->grademin = $grademin;
		$data->grademax = $grademax;
	}
	$DB->commit_delegated_transaction($transaction);

	// SF2018 - Gradebook
	$data->cmid = $data->coursemodule;
	$data->cmidnumber = uniqid();
    topaze_update_grades($data);
	$cm = $DB->get_record('course_modules', array('id'=>$data->cmid));
	if ($cm) {
		$cm->idnumber = $data->cmidnumber;
		$DB->update_record('course_modules', $cm);
	}
	
	return $data->id;
}

/**
 * Updates an instance of the topaze in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $data An object from the form in mod_form.php
 * @param mod_topaze_mod_form $mform
 * @return boolean Success/Fail
 */
function topaze_update_instance($data, $mform) {
	global $DB, $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
	$transaction = $DB->start_delegated_transaction();
	{
		$scoid = scormlite_save_sco($data, $mform, $data->coursemodule, 'packagefile');
		$data->timemodified = time();
		$data->id = $data->instance;

		// Grades min and max
		list($grademin, $grademax) = topaze_update_manifest_data($data->coursemodule, $data->id, $scoid);
		$DB->update_record('topaze', $data);
		$data->grademin = $grademin;
		$data->grademax = $grademax;
	}
	$DB->commit_delegated_transaction($transaction);
	
	// SF2018 - Gradebook
    $data->cmid = $data->coursemodule;
	$cm = $DB->get_record('course_modules', array('id'=>$data->cmid));
	$data->cmidnumber = $cm->idnumber;
	topaze_update_grades($data);
	
	return true;
}

/**
 * Removes an instance of the topaze from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function topaze_delete_instance($id) {
	global $DB, $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
	if (!$topaze = $DB->get_record('topaze', array('id'=>$id))) {
		return false;
	}
	scormlite_delete_sco($topaze->scoid);
	$DB->delete_records('topaze', array('id'=>$id));
	$DB->delete_records('topaze_indicators', array('scoid'=>$topaze->scoid));
	$DB->delete_records('topaze_steps', array('scoid'=>$topaze->scoid));
	
	// SF2018 - Gradebook
    topaze_grade_item_delete($topaze);
	
	return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function topaze_user_outline($course, $user, $mod, $topaze) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
	return scormlite_sco_user_outline($topaze->scoid, $user->id);
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $topaze the module instance record
 * @return void, is supposed to echp directly
 */
function topaze_user_complete($course, $user, $mod, $topaze) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/report/reportlib.php');
	echo scormlite_sco_user_complete($topaze->scoid, $user->id);
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in topaze activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function topaze_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Returns all activity in topazes since a given time
 *
 * @param array $activities sequentially indexed array of objects
 * @param int $index
 * @param int $timestart
 * @param int $courseid
 * @param int $cmid
 * @param int $userid defaults to 0
 * @param int $groupid defaults to 0
 * @return void adds items into $activities and increases $index
 */
function topaze_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see topaze_get_recent_mod_activity()}

 * @return void
 */
function topaze_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
/*
function topaze_cron () {
    return true;
}
*/

/**
 * Returns an array of users who are participanting in this topaze
 *
 * Must return an array of users who are participants for a given instance
 * of topaze. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @param int $topazeid ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function topaze_get_participants($topazeid) {
    return false;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function topaze_get_extra_capabilities() {
    return array();
}


////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $scormid id of scorm
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function topaze_get_user_grades($activity, $userid=0) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/topaze/scormlitelib.php');
	$grades = array();
	if (empty($userid)) {
		$raws = topaze_get_grades($activity);
		if (!empty($raws)) {
			foreach ($raws as $userid => $raw) {
	            $grades[$userid] = new stdClass();
	            $grades[$userid]->id         = $userid;
	            $grades[$userid]->userid     = $userid;
	            $grades[$userid]->rawgrade   = $raw;
	        }    		
    	}
    } else {
    	$raw = topaze_get_grade($userid, $activity);
		if (isset($raw)) {
    		$grades[$userid] = new stdClass();
	        $grades[$userid]->id 		= $userid;
	        $grades[$userid]->userid 	= $userid;
	        $grades[$userid]->rawgrade 	= $raw;
    	}
    }
    if (empty($grades)) return false;
    return $grades;
}

/**
 * Creates or updates grade item for the give topaze instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $topaze instance object with extra cmidnumber and modname property
 * @return void
 */
function topaze_grade_item_update($activity, $grades=null) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');
    $params = array('itemname'=>$activity->name);
    if (isset($activity->cmidnumber)) {
        $params['idnumber'] = $activity->cmidnumber;
    }
    $params['gradetype'] = GRADE_TYPE_VALUE;
	
	// Grades min and max
	if (isset($activity->grademax)) {
		$params['grademax'] = $activity->grademax;
	}
	if (isset($activity->grademin)) {
		$params['grademin'] = $activity->grademin;
	}
	
    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
	}
    $res = grade_update('mod/topaze', $activity->course, 'mod', 'topaze', $activity->id, 0, $grades, $params);
	
	return $res;
}

/**
 * Update topaze grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $topaze instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 * @return void
 */
function topaze_update_grades($activity, $userid=0, $nullifnone=true) {
	global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');
    if ($grades = topaze_get_user_grades($activity, $userid)) {
    	topaze_grade_item_update($activity, $grades);
    } else if ($userid and $nullifnone) {
    	$grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        topaze_grade_item_update($activity, $grade);
    } else {
    	topaze_grade_item_update($activity);
    }
}

/**
 * Delete grade item for given scorm
 *
 * @global stdClass
 * @param object $scorm object
 * @return object grade_item
 */
function topaze_grade_item_delete($activity) {
	global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    return grade_update('mod/topaze', $activity->course, 'mod', 'topaze', $activity->id, 0, null, array('deleted'=>1));
}



////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function topaze_get_file_areas($course, $cm, $context) {
	$areas = array();
	$areas['content'] = get_string('areacontent', 'scormlite');
	$areas['package'] = get_string('areapackage', 'scormlite');
	return $areas;
}

/**
 * File browsing support for topaze file areas
 *
 * @param stdclass $browser
 * @param stdclass $areas
 * @param stdclass $course
 * @param stdclass $cm
 * @param stdclass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return stdclass file_info instance or null if not found
 */
function topaze_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
	return scormlite_shared_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename, 'topaze');
}

/**
 * Serves the files from the topaze file areas
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return void this should never return to the caller
 */
function topaze_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
	global $CFG;
	require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
	return scormlite_shared_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options, 'topaze');
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding topaze nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the topaze module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function topaze_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the topaze settings
 *
 * This function is called when the context for the page is a topaze module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $topazenode {@link navigation_node}
 */
function topaze_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $topazenode=null) {
}

////////////////////////////////////////////////////////////////////////////////
// Reset feature                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the scorm.
 *
 * @param object $mform form passed by reference
 */
function topaze_reset_course_form_definition(&$mform) {
	$mform->addElement('header', 'scormheader', get_string('modulenameplural', 'topaze'));
	$mform->addElement('advcheckbox', 'reset_topaze', get_string('deletealltracks','scormlite'));
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function topaze_reset_course_form_defaults($course) {
	return array('reset_topaze'=>1);
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function topaze_reset_gradebook($courseid, $type='') {
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * scorm attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function topaze_reset_userdata($data) {
    global $CFG;
	$status = array();
	if (!empty($data->reset_topaze)) {
		require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
		scormlite_shared_reset_userdata($data, $status, 'topaze');
	}
	return $status;
}


////////////////////////////////////////////////////////////////////////////////
// Additional stuff (not in the template)                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function topaze_page_type_list($pagetype, $parentcontext, $currentcontext) {
	$module_pagetype = array('mod-topaze-*'=>get_string('page-mod-topaze-x', 'topaze'));
	return $module_pagetype;
}

/**
 * writes overview info for course_overview block - displays upcoming scorm objects that have a due date
 *
 * @param object $type - type of log(aicc,scorm12,scorm13) used as prefix for filename
 * @param array $htmlarray
 * @return mixed
 */
function topaze_print_overview($courses, &$htmlarray) {
}








