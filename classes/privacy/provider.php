<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_topaze\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy class for requesting user data.
 */
class provider implements
  \core_privacy\local\metadata\provider,
  \core_privacy\local\request\core_userlist_provider,
  \core_privacy\local\request\plugin\provider
{

  /**
   * Return the fields which contain personal data.
   *
   * @param   collection $collection The initialised collection to add items to.
   * @return  collection A listing of user data stored through this system.
   */
  public static function get_metadata(collection $collection): collection
  {
    $collection->add_database_table('scormlite_scoes_track', [
      'userid' => 'privacy:metadata:userid',
      'attempt' => 'privacy:metadata:attempt',
      'element' => 'privacy:metadata:scoes_track:element',
      'value' => 'privacy:metadata:scoes_track:value',
      'timemodified' => 'privacy:metadata:timemodified'
    ], 'privacy:metadata:scormlite_scoes_track');

    return $collection;
  }

  /**
   * Get the list of contexts that contain user information for the specified user.
   *
   * @param int $userid The user to search.
   * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
   */
  public static function get_contexts_for_userid(int $userid): contextlist
  {
    $sql = "SELECT ctx.id
                  FROM {%s} sst
                  JOIN {topaze} s
                    ON s.scoid = sst.scoid
                  JOIN {modules} m
                    ON m.name = 'topaze'
                  JOIN {course_modules} cm
                    ON cm.instance = s.id
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modlevel
                 WHERE sst.userid = :userid";

    $params = ['modlevel' => CONTEXT_MODULE, 'userid' => $userid];
    $contextlist = new contextlist();
    $contextlist->add_from_sql(sprintf($sql, 'scormlite_scoes_track'), $params);

    return $contextlist;
  }

  /**
   * Get the list of users who have data within a context.
   *
   * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
   */
  public static function get_users_in_context(userlist $userlist)
  {
    $context = $userlist->get_context();

    if (!is_a($context, \context_module::class)) {
      return;
    }

    $sql = "SELECT sst.userid
                  FROM {%s} sst
                  JOIN {topaze} s
                    ON s.scoid = sst.scoid
                  JOIN {modules} m
                    ON m.name = 'topaze'
                  JOIN {course_modules} cm
                    ON cm.instance = s.id
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                   AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";

    $params = ['modlevel' => CONTEXT_MODULE, 'contextid' => $context->id];

    $userlist->add_from_sql('userid', sprintf($sql, 'scormlite_scoes_track'), $params);
  }

  /**
   * Export all user data for the specified user, in the specified contexts.
   *
   * @param approved_contextlist $contextlist The approved contexts to export information for.
   */
  public static function export_user_data(approved_contextlist $contextlist)
  {
    global $DB;

    // Remove contexts different from COURSE_MODULE.
    $contexts = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
      if ($context->contextlevel == CONTEXT_MODULE) {
        $carry[] = $context->id;
      }
      return $carry;
    }, []);

    if (empty($contexts)) {
      return;
    }

    $userid = $contextlist->get_user()->id;
    list($insql, $inparams) = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);

    // Get scoes_track data.
    $sql = "SELECT sst.id,
                       sst.attempt,
                       sst.element,
                       sst.value,
                       sst.timemodified,
                       ctx.id as contextid
                  FROM {scormlite_scoes_track} sst
                  JOIN {topaze} s
                    ON s.scoid = sst.scoid
                  JOIN {course_modules} cm
                    ON cm.instance = s.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND sst.userid = :userid";
    $params = array_merge($inparams, ['userid' => $userid]);

    $alldata = [];
    $scoestracks = $DB->get_recordset_sql($sql, $params);
    foreach ($scoestracks as $track) {
      $alldata[$track->contextid][$track->attempt][] = (object) [
        'element' => $track->element,
        'value' => $track->value,
        'timemodified' => transform::datetime($track->timemodified),
      ];
    }
    $scoestracks->close();

    // The scoes_track data is organised in: {Course name}/{Topaze activity name}/{Attempt X}/data.json
    // where X is the attempt number.
    array_walk($alldata, function ($attemptsdata, $contextid) {
      $context = \context::instance_by_id($contextid);
      array_walk($attemptsdata, function ($data, $attempt) use ($context) {
        $subcontext = [
          get_string('myattempts', 'scorm'),
          get_string('attempt', 'scorm') . " $attempt"
        ];
        writer::with_context($context)->export_data(
          $subcontext,
          (object) ['scoestrack' => $data]
        );
      });
    });
  }

  /**
   * Delete all user data which matches the specified context.
   *
   * @param context $context A user context.
   */
  public static function delete_data_for_all_users_in_context(\context $context)
  {
    // This should not happen, but just in case.
    if ($context->contextlevel != CONTEXT_MODULE) {
      return;
    }

    // Prepare SQL to gather all IDs to delete.
    $sql = "SELECT sst.id
                  FROM {%s} sst
                  JOIN {topaze} s
                    ON s.scoid = sst.scoid
                  JOIN {modules} m
                    ON m.name = 'topaze'
                  JOIN {course_modules} cm
                    ON cm.instance = s.id
                   AND cm.module = m.id
                 WHERE cm.id = :cmid";
    $params = ['cmid' => $context->instanceid];

    static::delete_data('scormlite_scoes_track', $sql, $params);
  }

  /**
   * Delete all user data for the specified user, in the specified contexts.
   *
   * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
   */
  public static function delete_data_for_user(approved_contextlist $contextlist)
  {
    global $DB;

    // Remove contexts different from COURSE_MODULE.
    $contextids = array_reduce($contextlist->get_contexts(), function ($carry, $context) {
      if ($context->contextlevel == CONTEXT_MODULE) {
        $carry[] = $context->id;
      }
      return $carry;
    }, []);

    if (empty($contextids)) {
      return;
    }
    $userid = $contextlist->get_user()->id;
    // Prepare SQL to gather all completed IDs.
    list($insql, $inparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
    $sql = "SELECT sst.id
                  FROM {%s} sst
                  JOIN {topaze} s
                    ON s.scoid = sst.scoid
                  JOIN {modules} m
                    ON m.name = 'topaze'
                  JOIN {course_modules} cm
                    ON cm.instance = s.id
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                 WHERE sst.userid = :userid
                   AND ctx.id $insql";
    $params = array_merge($inparams, ['userid' => $userid]);

    static::delete_data('scormlite_scoes_track', $sql, $params);
  }

  /**
   * Delete multiple users within a single context.
   *
   * @param   approved_userlist       $userlist The approved context and user information to delete information for.
   */
  public static function delete_data_for_users(approved_userlist $userlist)
  {
    global $DB;
    $context = $userlist->get_context();

    if (!is_a($context, \context_module::class)) {
      return;
    }

    // Prepare SQL to gather all completed IDs.
    $userids = $userlist->get_userids();
    list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

    $sql = "SELECT sst.id
                  FROM {%s} sst
                  JOIN {topaze} s
                    ON s.scoid = sst.scoid
                  JOIN {modules} m
                    ON m.name = 'topaze'
                  JOIN {course_modules} cm
                    ON cm.instance = s.id
                   AND cm.module = m.id
                  JOIN {context} ctx
                    ON ctx.instanceid = cm.id
                 WHERE ctx.id = :contextid
                   AND sst.userid $insql";
    $params = array_merge($inparams, ['contextid' => $context->id]);

    static::delete_data('scormlite_scoes_track', $sql, $params);
  }

  /**
   * Delete data from $tablename with the IDs returned by $sql query.
   *
   * @param  string $tablename  Table name where executing the SQL query.
   * @param  string $sql    SQL query for getting the IDs of the scoestrack entries to delete.
   * @param  array  $params SQL params for the query.
   */
  protected static function delete_data(string $tablename, string $sql, array $params)
  {
    global $DB;

    $scoestracksids = $DB->get_fieldset_sql(sprintf($sql, $tablename), $params);
    if (!empty($scoestracksids)) {
      list($insql, $inparams) = $DB->get_in_or_equal($scoestracksids, SQL_PARAMS_NAMED);
      $DB->delete_records_select($tablename, "id $insql", $inparams);
    }
  }
}
