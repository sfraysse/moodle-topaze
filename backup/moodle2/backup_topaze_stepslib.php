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

/**
 * Define the complete scorm structure for backup, with file and id annotations
 */
class backup_topaze_activity_structure_step extends backup_activity_structure_step {

	protected function define_structure() {

		// To know if we are including userinfo
		$userinfo = $this->get_setting_value('userinfo');

		// Define each element separated
        
        $topaze = new backup_nested_element('topaze', array('id'), array(
			'course', 'name', 'intro', 'introformat', 'scoid', 'timemodified', 'pathtracking', 'mainindicator'));

		$indicators = new backup_nested_element('indicators');
		$indicator = new backup_nested_element('indicator', array('id'), array(
			'topazeid', 'manifestid', 'type', 'title', 'scoid'));

		$steps = new backup_nested_element('steps');
		$step = new backup_nested_element('step', array('id'), array(
			'topazeid', 'manifestid', 'type', 'title', 'tracking', 'scoid'));

		$scoes = new backup_nested_element('scoes');
		$sco = new backup_nested_element('sco', array('id'), array(
			'containertype', 'scormtype', 'reference', 'sha1hash', 'md5hash', 'revision', 'timeopen', 'timeclose',
			'manualopen', 'maxtime', 'passingscore', 'displaychrono', 'colors', 'popup', 'maxattempt', 'whatgrade'));

		if ($userinfo) {
            $tracks = new backup_nested_element('tracks');
            $track = new backup_nested_element('track', array('id'), array(
                'userid', 'scoid', 'attempt', 'element', 'value', 'timemodified'));
        }

		// Build the tree
        
		$topaze->add_child($indicators);
		$indicators->add_child($indicator);
		$topaze->add_child($steps);
		$steps->add_child($step);
		$topaze->add_child($scoes);
		$scoes->add_child($sco);
		if ($userinfo) {
			$sco->add_child($tracks);
			$tracks->add_child($track);
		}

		// Define sources
		$topaze->set_source_table('topaze', array('id' => backup::VAR_ACTIVITYID));
        
		$sql = '
			SELECT TI.*
			FROM {topaze_indicators} TI
			INNER JOIN {topaze} T ON T.scoid=TI.scoid
			WHERE T.id=?';
		$indicator->set_source_sql($sql, array(backup::VAR_PARENTID));
        
		$sql = '
			SELECT TS.*
			FROM {topaze_steps} TS
			INNER JOIN {topaze} T ON T.scoid=TS.scoid
			WHERE T.id=?';
		$step->set_source_sql($sql, array(backup::VAR_PARENTID));
        
		$sql = '
			SELECT SS.*
			FROM {scormlite_scoes} SS
			INNER JOIN {topaze} T ON T.scoid=SS.id
			WHERE T.id=?';
		$sco->set_source_sql($sql, array(backup::VAR_PARENTID));
        
		if ($userinfo) {
			$track->set_source_table('scormlite_scoes_track', array('scoid' => backup::VAR_PARENTID));
            
			// Define id annotations
			$track->annotate_ids('user', 'userid');
		}

		// Define file annotations
		$topaze->annotate_files('mod_topaze', 'intro', null); // This file area hasn't itemid
		$sco->annotate_files('mod_topaze', 'content', 'id');
		$sco->annotate_files('mod_topaze', 'package', 'id');

		// Return the root element, wrapped into standard activity structure
		return $this->prepare_activity_structure($topaze);
	}
}
