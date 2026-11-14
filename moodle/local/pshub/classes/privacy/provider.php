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

namespace local_pshub\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_user;

/**
 * Privacy provider for local_pshub.
 *
 * Moodle will not accept a plugin that stores user data without declaring it:
 * core's own test suite fails the plugin otherwise. Every table here holds
 * something about a person, and this class is the record of what and why.
 *
 * One deliberate asymmetry worth understanding before changing it. Most data
 * here is deleted on request, but **transactions and the audit trail are
 * not**. A payment record is a financial document and the audit trail exists
 * to answer "who let this money through" months later; erasing either on
 * request would defeat the purpose of keeping them, and both are retained
 * under the legitimate-interest and legal-obligation bases rather than
 * consent. What is removed instead is the link to the person: see
 * anonymise_financial_records().
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Declare everything this plugin stores about a person.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('local_pshub_user_profile', [
            'userid' => 'privacy:metadata:userprofile:userid',
            'phone' => 'privacy:metadata:userprofile:phone',
            'preferred_lang' => 'privacy:metadata:userprofile:lang',
            'audience' => 'privacy:metadata:userprofile:audience',
        ], 'privacy:metadata:userprofile');

        $collection->add_database_table('local_pshub_access', [
            'userid' => 'privacy:metadata:access:userid',
            'target_type' => 'privacy:metadata:access:target',
            'target_id' => 'privacy:metadata:access:target',
            'source' => 'privacy:metadata:access:source',
            'status' => 'privacy:metadata:access:status',
            'grantreason' => 'privacy:metadata:access:reason',
        ], 'privacy:metadata:access');

        $collection->add_database_table('local_pshub_transaction', [
            'userid' => 'privacy:metadata:transaction:userid',
            'amount' => 'privacy:metadata:transaction:amount',
            'method' => 'privacy:metadata:transaction:method',
            'payer_msisdn' => 'privacy:metadata:transaction:msisdn',
            'status' => 'privacy:metadata:transaction:status',
        ], 'privacy:metadata:transaction');

        $collection->add_database_table('local_pshub_session', [
            'teacherid' => 'privacy:metadata:session:teacherid',
            'title' => 'privacy:metadata:session:title',
            'scheduled_start' => 'privacy:metadata:session:start',
        ], 'privacy:metadata:session');

        $collection->add_database_table('local_pshub_attendance', [
            'userid' => 'privacy:metadata:attendance:userid',
            'joined_at' => 'privacy:metadata:attendance:joined',
            'duration_seconds' => 'privacy:metadata:attendance:duration',
            'counted_present' => 'privacy:metadata:attendance:present',
        ], 'privacy:metadata:attendance');

        $collection->add_database_table('local_pshub_teacher_assign', [
            'teacherid' => 'privacy:metadata:assign:teacherid',
            'brief' => 'privacy:metadata:assign:brief',
            'status' => 'privacy:metadata:assign:status',
        ], 'privacy:metadata:assign');

        $collection->add_database_table('local_pshub_xp_ledger', [
            'userid' => 'privacy:metadata:xp:userid',
            'event_key' => 'privacy:metadata:xp:event',
            'points' => 'privacy:metadata:xp:points',
        ], 'privacy:metadata:xp');

        $collection->add_database_table('local_pshub_learner', [
            'userid' => 'privacy:metadata:learner:userid',
            'xp_total' => 'privacy:metadata:learner:xp',
            'streak_days' => 'privacy:metadata:learner:streak',
        ], 'privacy:metadata:learner');

        $collection->add_database_table('local_pshub_group_member', [
            'userid' => 'privacy:metadata:groupmember:userid',
            'joined_at' => 'privacy:metadata:groupmember:joined',
        ], 'privacy:metadata:groupmember');

        $collection->add_database_table('local_pshub_push_token', [
            'userid' => 'privacy:metadata:pushtoken:userid',
            'token' => 'privacy:metadata:pushtoken:token',
            'platform' => 'privacy:metadata:pushtoken:platform',
        ], 'privacy:metadata:pushtoken');

        $collection->add_database_table('local_pshub_audit', [
            'actorid' => 'privacy:metadata:audit:actorid',
            'action' => 'privacy:metadata:audit:action',
            'ip' => 'privacy:metadata:audit:ip',
        ], 'privacy:metadata:audit');

        return $collection;
    }

    /**
     * All of this plugin's data hangs off the person, not off a course, so the
     * user context is the only one in play.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_user_context($userid);

        return $contextlist;
    }

    /**
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof context_user) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || $context->instanceid != $userid) {
                continue;
            }

            $data = [];
            foreach (self::user_tables() as $table => $field) {
                $records = $DB->get_records($table, [$field => $userid]);
                if ($records) {
                    $data[$table] = array_values($records);
                }
            }

            if ($data) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_pshub')],
                    (object) $data
                );
            }
        }
    }

    /**
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context instanceof context_user) {
            self::delete_for_user((int) $context->instanceid);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && $context->instanceid == $userid) {
                self::delete_for_user($userid);
            }
        }
    }

    /**
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            if ($userid == $context->instanceid) {
                self::delete_for_user((int) $userid);
            }
        }
    }

    /**
     * Tables holding data about a person, and the column that identifies them.
     *
     * @return array<string, string>
     */
    protected static function user_tables(): array {
        return [
            'local_pshub_user_profile' => 'userid',
            'local_pshub_access' => 'userid',
            'local_pshub_transaction' => 'userid',
            'local_pshub_session' => 'teacherid',
            'local_pshub_attendance' => 'userid',
            'local_pshub_teacher_assign' => 'teacherid',
            'local_pshub_xp_ledger' => 'userid',
            'local_pshub_learner' => 'userid',
            'local_pshub_group_member' => 'userid',
            'local_pshub_push_token' => 'userid',
            'local_pshub_audit' => 'actorid',
        ];
    }

    /**
     * Erase one person's data.
     *
     * @param int $userid
     * @return void
     */
    protected static function delete_for_user(int $userid): void {
        global $DB;

        // Deleted outright: nothing downstream depends on keeping these.
        $erase = [
            'local_pshub_user_profile' => 'userid',
            'local_pshub_access' => 'userid',
            'local_pshub_attendance' => 'userid',
            'local_pshub_xp_ledger' => 'userid',
            'local_pshub_learner' => 'userid',
            'local_pshub_group_member' => 'userid',
            'local_pshub_push_token' => 'userid',
            'local_pshub_teacher_assign' => 'teacherid',
        ];
        foreach ($erase as $table => $field) {
            $DB->delete_records($table, [$field => $userid]);
        }

        self::anonymise_financial_records($userid);
    }

    /**
     * Break the link between a person and records that must survive them.
     *
     * A transaction is a financial document and the audit trail answers who
     * authorised what. Deleting either on request would destroy the reason for
     * holding them, so the person is unlinked instead: the money and the
     * decision remain, the identity does not.
     *
     * @param int $userid
     * @return void
     */
    protected static function anonymise_financial_records(int $userid): void {
        global $DB;

        $DB->set_field('local_pshub_transaction', 'payer_msisdn', null, ['userid' => $userid]);
        $DB->set_field('local_pshub_transaction', 'userid', 0, ['userid' => $userid]);

        $DB->set_field('local_pshub_audit', 'ip', null, ['actorid' => $userid]);
        $DB->set_field('local_pshub_audit', 'actorid', 0, ['actorid' => $userid]);

        // A session is the teacher's work product, not the learner's data. It
        // is kept and unlinked so the course history stays coherent.
        $DB->set_field('local_pshub_session', 'teacherid', 0, ['teacherid' => $userid]);
    }
}
