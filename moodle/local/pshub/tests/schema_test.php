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

namespace local_pshub;

/**
 * The schema's guarantees, asserted against a real database.
 *
 * The specification repeatedly says idempotency is enforced by a database
 * constraint "not a code check". That claim is only worth making if something
 * proves the database actually rejects the duplicate, so these tests insert
 * one and require the write to fail.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class schema_test extends \advanced_testcase {

    /**
     * Every table the design mandate requires in P1 exists, including those
     * whose features do not ship until P2 (spec §1.1).
     */
    public function test_all_tables_exist(): void {
        global $DB;
        $this->resetAfterTest();

        $expected = [
            'local_pshub_user_profile', 'local_pshub_course_type',
            'local_pshub_course_type_map', 'local_pshub_course',
            'local_pshub_pack', 'local_pshub_pack_course',
            'local_pshub_access', 'local_pshub_transaction',
            'local_pshub_session', 'local_pshub_attendance',
            'local_pshub_reminder', 'local_pshub_teacher_assign',
            'local_pshub_xp_rule', 'local_pshub_xp_ledger',
            'local_pshub_learner', 'local_pshub_group',
            'local_pshub_group_member', 'local_pshub_push_token',
            'local_pshub_audit',
        ];

        $actual = $DB->get_tables();
        foreach ($expected as $table) {
            $this->assertContains($table, $actual, "Missing table: {$table}");
        }
        $this->assertCount(19, $expected);
    }

    /**
     * AC-12. A duplicate provider_ref must be impossible, not merely unlikely.
     * This is what makes a replayed payment callback a no-op under concurrency,
     * where a read-then-write check in PHP would race.
     */
    public function test_duplicate_provider_ref_is_rejected_by_the_database(): void {
        global $DB;
        $this->resetAfterTest();

        $row = (object) [
            'userid' => 1, 'target_type' => 'course', 'target_id' => 1,
            'amount' => 1500000, 'currency' => 'XAF', 'method' => 'mtn_momo',
            'provider_ref' => 'momo-ref-0001', 'status' => 'paid_unvalidated',
            'timecreated' => 1, 'timemodified' => 1,
        ];
        $DB->insert_record('local_pshub_transaction', $row);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_pshub_transaction', $row);
    }

    /**
     * AC-13. The same award event, replayed, cannot inflate a total.
     */
    public function test_duplicate_xp_award_is_rejected_by_the_database(): void {
        global $DB;
        $this->resetAfterTest();

        $row = (object) [
            'userid' => 1, 'event_key' => 'live_attendance', 'points' => 50,
            'contextid' => 1, 'itemid' => 77, 'timecreated' => 1,
        ];
        $DB->insert_record('local_pshub_xp_ledger', $row);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_pshub_xp_ledger', $row);
    }

    /**
     * AC-6. A cron run that fires twice must not send two reminders.
     */
    public function test_duplicate_reminder_is_rejected_by_the_database(): void {
        global $DB;
        $this->resetAfterTest();

        $row = (object) [
            'sessionid' => 1, 'offset_minutes' => 60,
            'audience' => 'learners', 'sent_at' => 1,
        ];
        $DB->insert_record('local_pshub_reminder', $row);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_pshub_reminder', $row);
    }

    /**
     * One access right per user per target. Two concurrent grants cannot
     * produce two rows that later disagree about expiry.
     */
    public function test_duplicate_access_right_is_rejected_by_the_database(): void {
        global $DB;
        $this->resetAfterTest();

        $row = (object) [
            'userid' => 1, 'target_type' => 'course', 'target_id' => 5,
            'source' => 'payment', 'status' => 'active', 'expires_at' => 0,
            'timecreated' => 1, 'timemodified' => 1,
        ];
        $DB->insert_record('local_pshub_access', $row);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_pshub_access', $row);
    }

    /**
     * A different target for the same user is a different right, so the
     * constraint must not be over-broad.
     */
    public function test_same_user_may_hold_rights_over_different_targets(): void {
        global $DB;
        $this->resetAfterTest();

        $base = [
            'userid' => 1, 'source' => 'payment', 'status' => 'active',
            'expires_at' => 0, 'timecreated' => 1, 'timemodified' => 1,
        ];
        $DB->insert_record('local_pshub_access',
            (object) ($base + ['target_type' => 'course', 'target_id' => 5]));
        $DB->insert_record('local_pshub_access',
            (object) ($base + ['target_type' => 'course', 'target_id' => 6]));
        $DB->insert_record('local_pshub_access',
            (object) ($base + ['target_type' => 'pack', 'target_id' => 5]));

        $this->assertEquals(3, $DB->count_records('local_pshub_access', ['userid' => 1]));
    }

    /**
     * Pending transactions have no provider reference yet. A unique index over
     * a nullable column must still allow many NULLs, or checkout breaks the
     * moment two people start paying at once.
     */
    public function test_many_transactions_may_have_no_provider_ref(): void {
        global $DB;
        $this->resetAfterTest();

        $base = [
            'target_type' => 'course', 'target_id' => 1, 'amount' => 1000,
            'currency' => 'XAF', 'method' => 'mtn_momo', 'status' => 'pending',
            'provider_ref' => null, 'timecreated' => 1, 'timemodified' => 1,
        ];
        $DB->insert_record('local_pshub_transaction', (object) ($base + ['userid' => 1]));
        $DB->insert_record('local_pshub_transaction', (object) ($base + ['userid' => 2]));
        $DB->insert_record('local_pshub_transaction', (object) ($base + ['userid' => 3]));

        $this->assertEquals(3, $DB->count_records('local_pshub_transaction', ['status' => 'pending']));
    }
}
