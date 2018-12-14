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
 * Define all the restore steps that will be used by the restore_topaze_activity_task.
 */
class restore_topaze_activity_structure_step extends restore_activity_structure_step {

	protected function define_structure() {

		$userinfo = $this->get_setting_value('userinfo');

		$paths = array();
		$paths[] = new restore_path_element('topaze', '/activity/topaze');
		$paths[] = new restore_path_element('indicator', '/activity/topaze/indicators/indicator');
		$paths[] = new restore_path_element('step', '/activity/topaze/steps/step');
		$paths[] = new restore_path_element('sco', '/activity/topaze/scoes/sco');
		if ($userinfo) {
			$paths[] = new restore_path_element('track', '/activity/topaze/scoes/sco/tracks/track');
		}

		// Return the paths wrapped into standard activity structure
		return $this->prepare_activity_structure($paths);
	}

	protected function process_topaze($data) {
		global $DB;
		$data = (object)$data;
        
        // Update data
		$data->course = $this->get_courseid();
		$data->timemodified = $this->apply_date_offset($data->timemodified);

		// Save in DB
		$newitemid = $DB->insert_record('topaze', $data);
		$this->apply_activity_instance($newitemid);
	}

	protected function process_indicator($data) {
		global $DB;
		$data = (object)$data;
        
        // Update data
		$data->topazeid = $this->elementsnewid['topaze'];

        // Save in DB
		$newitemid = $DB->insert_record('topaze_indicators', $data);
        
        // Mapping
		// No need to save this mapping as far as nothing depend on it
		// (child paths, file areas nor links decoder)
	}

	protected function process_step($data) {
		global $DB;
		$data = (object)$data;

        // Update data
		$data->topazeid = $this->elementsnewid['topaze'];

        // Save in DB
		$newitemid = $DB->insert_record('topaze_steps', $data);

        // Mapping
		// No need to save this mapping as far as nothing depend on it
		// (child paths, file areas nor links decoder)
	}

	protected function process_sco($data) {
		global $DB;
		$data = (object)$data;

        // Update data
		$data->timeopen = $this->apply_date_offset($data->timeopen);
		$data->timeclose = $this->apply_date_offset($data->timeclose);

        // Save in DB
		$oldid = $data->id;
		$newitemid = $DB->insert_record('scormlite_scoes', $data);
        
        // Update other tables
		$topazeid = $this->elementsnewid['topaze'];
		$DB->execute("UPDATE {topaze} SET scoid=$newitemid WHERE id=$topazeid");
		$DB->execute("UPDATE {topaze_indicators} SET scoid=$newitemid WHERE topazeid=$topazeid");
		$DB->execute("UPDATE {topaze_steps} SET scoid=$newitemid WHERE topazeid=$topazeid");
        
        // Mapping
		$this->set_mapping('sco', $oldid, $newitemid, true);
	}

	protected function process_track($data) {
		global $DB;
		$data = (object)$data;
        
		$oldid = $data->id;
		$data->scoid = $this->get_new_parentid('sco');
		$data->userid = $this->get_mappingid('user', $data->userid);
		$data->timemodified = $this->apply_date_offset($data->timemodified);

        // Save in DB
		$newitemid = $DB->insert_record('scormlite_scoes_track', $data);

        // Mapping
		// No need to save this mapping as far as nothing depend on it
		// (child paths, file areas nor links decoder)
	}

	protected function after_execute() {
		// Add topaze related files, no need to match by itemname (just internally handled context)
		$this->add_related_files('mod_topaze', 'intro', null);
		$this->add_related_files('mod_topaze', 'content', 'sco');
		$this->add_related_files('mod_topaze', 'package', 'sco');
	}
}
