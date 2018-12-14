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


defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/scormlite/sharedlib.php');

    // Manual opening of the activity: Use dates / Open / Closed
    $settings->add(new admin_setting_configselect('topaze/manualopen', get_string('manualopen','scormlite'), get_string('manualopendesc','scormlite'), 1, scormlite_get_manualopen_display_array()));

    // Display mode: current window or popup
    $settings->add(new admin_setting_configselect('topaze/popup', get_string('display','scormlite'), get_string('displaydesc','scormlite'), 1, scormlite_get_popup_display_array()));

    // Maximum number of attempts
    $settings->add(new admin_setting_configselect('topaze/maxattempt', get_string('maximumattempts', 'scormlite'), '', 0, scormlite_get_attempts_array()));

    // Score to keep when multiple attempts
    $settings->add(new admin_setting_configselect('topaze/whatgrade', get_string('whatgrade', 'scormlite'), get_string('whatgradedesc', 'scormlite'), 2, scormlite_get_what_grade_array()));
	
}

