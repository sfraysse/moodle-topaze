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


if (!defined('MOODLE_INTERNAL')) {
	die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');
require_once($CFG->dirroot.'/mod/topaze/locallib.php');


class mod_topaze_mod_form extends moodleform_mod {

	function definition()
	{
		global $CFG, $COURSE;
		$config = get_config('topaze');
		$mform = $this->_form;

		//-------------------------------------------------------------------------------
		// General

		$mform->addElement('header', 'general', get_string('general', 'scormlite'));

		// Name
		$mform->addElement('text', 'name', get_string('name'));
		$mform->setType('name', PARAM_TEXT);
		$mform->addRule('name', null, 'required', null, 'client');

		// Summary
        // KD2015-61 � add_intro_editor to be replaced by standard_intro_elements
		// $this->add_intro_editor();
        $this->standard_intro_elements();

		// New local package upload
        
        // KD2014 - Removed for 2.5 compliance
		// $maxbytes = get_max_upload_file_size($CFG->maxbytes, $COURSE->maxbytes);
		// $mform->setMaxFileSize($maxbytes);
        
		$mform->addElement('filepicker', 'packagefile', get_string('package','scormlite'));
		$mform->addHelpButton('packagefile', 'package', 'scormlite');
		$mform->addRule('packagefile', null, 'required', null, 'client');

		// Grade max
		$mform->addElement('text', 'grademax', get_string('grademax','topaze'), 'maxlength="3" size="3"');
		$mform->setDefault('grademax', 100);
		$mform->setType('grademax', PARAM_INT);
		$mform->addRule('grademax', null, 'numeric', null, 'client');
		$mform->addRule('grademax', null, 'nopunctuation', null, 'client');
		$mform->addElement('html', '<p class="text-danger">'.get_string('grademax_warning','topaze').'</p>');
		

		//-------------------------------------------------------------------------------
		// Availability

		$mform->addElement('header', 'timerestricthdr', get_string('timerestrict', 'scormlite'));

		// Manual opening
		$mform->addElement('select', 'manualopen', get_string('manualopen', 'scormlite'), scormlite_get_manualopen_display_array());
		$mform->setDefault('manualopen', $config->manualopen);

		// Opening date
		$mform->addElement('date_time_selector', 'timeopen', get_string("scormopen", "scormlite"));
		$mform->disabledIf('timeopen', 'manualopen', 'neq', 0);

		// Closing date
		$mform->addElement('date_time_selector', 'timeclose', get_string("scormclose", "scormlite"));
		$mform->disabledIf('timeclose', 'manualopen', 'neq', 0);


		//-------------------------------------------------------------------------------
		// Advanced settings

		$mform->addElement('header', 'advanced', get_string('othersettings', 'form'));

		// Framed / Popup Window
		$mform->addElement('select', 'popup', get_string('display', 'scormlite'), scormlite_get_popup_display_array());
		$mform->setDefault('popup', $config->popup);

        // Max Attempts
        $mform->addElement('select', 'maxattempt', get_string('maximumattempts', 'scormlite'), scormlite_get_attempts_array());
        $mform->addHelpButton('maxattempt', 'maximumattempts', 'scormlite');
        $mform->setDefault('maxattempt', $config->maxattempt);

        // What Attempt
        $mform->addElement('select', 'whatgrade', get_string('whatgrade', 'scormlite'),  scormlite_get_what_grade_array());
        $mform->disabledIf('whatgrade', 'maxattempt', 'eq', 1);
        $mform->addHelpButton('whatgrade', 'whatgrade', 'scormlite');
        $mform->setDefault('whatgrade', $config->whatgrade);


		//-------------------------------------------------------------------------------
		// Common settings

		$this->standard_coursemodule_elements();

		//-------------------------------------------------------------------------------
		// Buttons

		$this->add_action_buttons();

		//-------------------------------------------------------------------------------
		// Hidden

		// Activity props
		$mform->addElement('hidden', 'scoid', 0);
        $mform->setType('scoid', PARAM_INT);  // KD2014 - For 2.5 compliance

		// SCOs props
		$mform->addElement('hidden', 'containertype', 'topaze');
        $mform->setType('containertype', PARAM_ALPHA);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'maxtime', 0);
        $mform->setType('maxtime', PARAM_INT);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'passingscore', 50);
        $mform->setType('passingscore', PARAM_INT);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'displaychrono', 0);
        $mform->setType('displaychrono', PARAM_INT);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'colors', '');
        $mform->setType('colors', PARAM_ALPHA);  // KD2014 - For 2.5 compliance

        
		// Should check if this is necessary... (in BD but unused)		
		$mform->addElement('hidden', 'scormtype', 'local');
        $mform->setType('scormtype', PARAM_ALPHA);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'reference', '');
        $mform->setType('reference', PARAM_ALPHA);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'sha1hash', '');
        $mform->setType('sha1hash', PARAM_RAW);  // KD2014 - For 2.5 compliance

		$mform->addElement('hidden', 'revision', 0);
        $mform->setType('revision', PARAM_INT);  // KD2014 - For 2.5 compliance
	}

	//
	// Form pre-processing
	//
	
	function data_preprocessing(&$default_values) {	
		global $DB;

		// Max grade
		if (isset($this->_cm)) {
			$grade = $DB->get_record('grade_items', array('courseid'=>$this->_course->id, 'itemmodule'=>$this->_modname, 'iteminstance'=>$this->_cm->instance));
			if ($grade) $default_values['grademax'] = intval($grade->grademax);
		}

		// Get SCO data and assign it to the form  
		if (isset($default_values['scoid']) && $default_values['scoid'] != null) {
			global $DB;
			$scodata = $DB->get_record('scormlite_scoes', array('id'=>$default_values['scoid']), '*', false, MUST_EXIST);
			foreach ($scodata as $name => $value) {
				if ($name == 'id') $name = 'scoid';
				$default_values[$name] = $value;
			}
			$scoid = $scodata->id;
		} else {
			$scoid = 0;
		}

		// Packaging
		$draftitemid = file_get_submitted_draft_itemid('packagefile');
		file_prepare_draft_area($draftitemid, $this->context->id, 'mod_topaze', 'package', $scoid);
		$default_values['packagefile'] = $draftitemid;

		// Time
		if (empty($default_values['timeopen'])) {
			$default_values['timeopen'] = 0;
		}
		if (empty($default_values['timeclose'])) {
			$default_values['timeclose'] = 0;
		}

		parent::data_preprocessing($default_values);
	}

	//
	// Form validation
	//
	
	function validation($data, $files) 	{
		$errors = array();
		
		// Max grade
		if ($data['grademax'] < 1) {
			$errors['grademax'] = get_string('notvalidmaxgrade', 'scormlite');
		}

		// SCORM 2004 Lite Package
		
		if (empty($data['packagefile'])) {
			// If no file
			$errors['packagefile'] = get_string('required');
		} else {
			$files = $this->get_draft_files('packagefile');
			if (!$files || count($files)<1) {
				// If no file
				$errors['packagefile'] = get_string('required');
				return $errors;
			}
			// Upload and try to unzip
			$file = reset($files);
			global $CFG;
			$filename = "{$CFG->tempdir}/topazeimport/topaze_".time();
			make_temp_directory('topazeimport');
			$file->copy_content_to($filename);
			$packer = get_file_packer('application/zip');
			$filelist = $packer->list_files($filename);			
			if (!is_array($filelist)) {
				// If not a package
				$errors['packagefile'] = get_string('notvalidpackage', 'scormlite');
			} else {
				// Check if the index.html file is at the package root
                $indexfound = false;
				foreach ($filelist as $info) {
					if ($info->pathname == 'index.html') {
						$indexfound = true;
						break;
					}
				}
				if (!$indexfound) {
					$errors['packagefile'] = get_string('notvalidpackage', 'scormlite');
    				return $errors;
				}
				// Check if the topaze.xml file is at the package root
                $indexfound = false;
				foreach ($filelist as $info) {
					if ($info->pathname == 'topaze.xml') {
						$indexfound = true;
						break;
					}
				}
				if (!$indexfound) {
					$errors['packagefile'] = get_string('notvalidpackage', 'scormlite');
    				return $errors;
				}
			}
			unlink($filename);
		}
		
		return array_merge($errors, parent::validation($data, $files));
	}

}

