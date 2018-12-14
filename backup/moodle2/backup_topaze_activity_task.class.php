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

require_once($CFG->dirroot . '/mod/topaze/backup/moodle2/backup_topaze_stepslib.php');

/**
 * Backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_topaze_activity_task extends backup_activity_task {

	protected function define_my_settings() {
	}

	protected function define_my_steps() {
		$this->add_step(new backup_topaze_activity_structure_step('topaze_structure', 'topaze_bkp.xml'));
	}

	static public function encode_content_links($content) {
		return $content;
	}

}
