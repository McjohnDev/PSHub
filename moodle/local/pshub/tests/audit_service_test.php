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

use local_pshub\audit\audit_service;

/**
 * Tests for the audit trail.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\audit\audit_service
 */
final class audit_service_test extends \advanced_testcase {

    /**
     * A recorded transition answers who, what and when, months later.
     */
    public function test_record_captures_actor_and_states(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $id = (new audit_service())->record(
            audit_service::ACTION_COURSE_PUBLISHED,
            audit_service::TARGET_COURSE,
            42,
            ['review_status' => 'pending'],
            ['review_status' => 'published']
        );

        $row = $DB->get_record('local_pshub_audit', ['id' => $id], '*', MUST_EXIST);

        $this->assertEquals($user->id, $row->actorid);
        $this->assertEquals(audit_service::ACTION_COURSE_PUBLISHED, $row->action);
        $this->assertEquals(audit_service::TARGET_COURSE, $row->target_type);
        $this->assertEquals(42, $row->target_id);
        $this->assertEquals('pending', json_decode($row->before_json)->review_status);
        $this->assertEquals('published', json_decode($row->after_json)->review_status);
        $this->assertGreaterThan(0, $row->timecreated);
    }

    /**
     * Auto-validation has no human actor. Passing 0 explicitly must not be
     * replaced by the current user, or the trail would claim a person approved
     * something the system did (spec §3.8).
     */
    public function test_system_actor_is_recorded_as_zero(): void {
        global $DB;
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());

        $id = (new audit_service())->record(
            audit_service::ACTION_PAYMENT_VALIDATED,
            audit_service::TARGET_TRANSACTION,
            7,
            null,
            ['status' => 'validated'],
            0
        );

        $this->assertEquals(0, $DB->get_field('local_pshub_audit', 'actorid', ['id' => $id]));
    }

    /**
     * Support staff read this trail during an incident, which is exactly when
     * a credential sitting in a log column does the most damage.
     */
    public function test_secrets_are_stripped_from_snapshots(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = (new audit_service())->record(
            audit_service::ACTION_PAYMENT_RECEIVED,
            audit_service::TARGET_TRANSACTION,
            9,
            null,
            [
                'status' => 'paid_unvalidated',
                'provider_token' => 'tok_live_9f2b',
                'api_secret' => 'shhh',
                'user_password' => 'hunter2',
            ]
        );

        $after = $DB->get_field('local_pshub_audit', 'after_json', ['id' => $id]);

        $this->assertStringContainsString('paid_unvalidated', $after);
        $this->assertStringNotContainsString('tok_live_9f2b', $after);
        $this->assertStringNotContainsString('shhh', $after);
        $this->assertStringNotContainsString('hunter2', $after);
    }

    /**
     * Creation has no before state. It must round-trip as null, not as the
     * string "null" or an empty object.
     */
    public function test_null_states_stay_null(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = (new audit_service())->record(
            audit_service::ACTION_ACCESS_GRANTED,
            audit_service::TARGET_ACCESS,
            3,
            null,
            ['status' => 'active']
        );

        $row = $DB->get_record('local_pshub_audit', ['id' => $id], '*', MUST_EXIST);
        $this->assertNull($row->before_json);
        $this->assertNotNull($row->after_json);
    }

    /**
     * History reads back newest first, scoped to one target.
     */
    public function test_history_is_scoped_and_ordered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $service = new audit_service();
        $service->record(audit_service::ACTION_COURSE_SUBMITTED, audit_service::TARGET_COURSE, 1);
        $service->record(audit_service::ACTION_COURSE_REJECTED, audit_service::TARGET_COURSE, 1);
        $service->record(audit_service::ACTION_COURSE_SUBMITTED, audit_service::TARGET_COURSE, 2);

        $history = array_values($service->history(audit_service::TARGET_COURSE, 1));

        $this->assertCount(2, $history);
        $this->assertEquals(audit_service::ACTION_COURSE_REJECTED, $history[0]->action);
        $this->assertEquals(audit_service::ACTION_COURSE_SUBMITTED, $history[1]->action);
    }

    /**
     * Accented French content survives the round trip. The platform's default
     * language is French, so a trail that mangles it is a trail nobody trusts.
     */
    public function test_unicode_survives_the_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $id = (new audit_service())->record(
            audit_service::ACTION_COURSE_REJECTED,
            audit_service::TARGET_COURSE,
            11,
            null,
            ['review_comment' => "Séance trop courte, révisez l'évaluation."]
        );

        $after = $DB->get_field('local_pshub_audit', 'after_json', ['id' => $id]);
        $this->assertEquals(
            "Séance trop courte, révisez l'évaluation.",
            json_decode($after)->review_comment
        );
    }
}
