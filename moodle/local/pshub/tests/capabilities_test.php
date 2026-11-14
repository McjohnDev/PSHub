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
 * The role boundaries of spec §2.1 and §5.3, asserted rather than assumed.
 *
 * AC-11 says a learner reaching an admin surface is refused. That is only
 * meaningful if something checks it against real role assignments, because the
 * usual way this breaks is a capability quietly inheriting through an
 * archetype nobody re-read.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class capabilities_test extends \advanced_testcase {

    /** @var string[] Capabilities no learner may ever hold. */
    private const ADMIN_CAPS = [
        'local/pshub:viewadminportal',
        'local/pshub:publishcourse',
        'local/pshub:validatepayment',
        'local/pshub:managecoursetypes',
        'local/pshub:manageteachers',
        'local/pshub:managexprules',
        'local/pshub:viewaccounting',
        'local/pshub:grantaccess',
        'local/pshub:manageadmins',
    ];

    /**
     * Every capability the plugin declares is actually registered. A typo in
     * db/access.php otherwise fails open at the call site, because
     * has_capability() on an unknown capability is a silent false in some
     * contexts and a debugging notice in others.
     */
    public function test_all_capabilities_are_registered(): void {
        global $DB;
        $this->resetAfterTest();

        $declared = $DB->get_fieldset_select(
            'capabilities', 'name', "component = :c", ['c' => 'local_pshub']
        );

        $expected = array_merge(self::ADMIN_CAPS, [
            'local/pshub:viewteacherportal',
            'local/pshub:submitcourse',
            'local/pshub:schedulesession',
        ]);

        foreach ($expected as $cap) {
            $this->assertContains($cap, $declared, "Capability not registered: {$cap}");
        }
        $this->assertCount(12, $expected);
    }

    /**
     * AC-11. An ordinary learner holds none of the administrative
     * capabilities, at system level or inside a course they are enrolled in.
     */
    public function test_learner_holds_no_admin_capability(): void {
        $this->resetAfterTest();

        $learner = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');

        foreach ([\context_system::instance(), \context_course::instance($course->id)] as $context) {
            foreach (self::ADMIN_CAPS as $cap) {
                $this->assertFalse(
                    has_capability($cap, $context, $learner),
                    "A learner must not hold {$cap} in " . $context->get_level_name()
                );
            }
        }
    }

    /**
     * AC-1's structural half. A teacher may submit a course but must never be
     * able to approve one, including their own. The plugin sets this to
     * CAP_PROHIBIT rather than leaving it unassigned, so no later role edit
     * can grant it back by accident.
     */
    public function test_teacher_can_submit_but_never_publish(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $coursecontext = \context_course::instance($course->id);

        $this->assertTrue(has_capability('local/pshub:submitcourse', $coursecontext, $teacher));
        $this->assertTrue(has_capability('local/pshub:schedulesession', $coursecontext, $teacher));

        $this->assertFalse(has_capability('local/pshub:publishcourse', $coursecontext, $teacher));
        $this->assertFalse(
            has_capability('local/pshub:publishcourse', \context_system::instance(), $teacher)
        );
    }

    /**
     * Only a site administrator may create administrators. The capability is
     * declared with no archetype at all, which is what keeps the "1 compte"
     * constraint of cahier §4.1 enforceable rather than merely stated.
     */
    public function test_manageadmins_has_no_archetype(): void {
        global $DB;
        $this->resetAfterTest();

        $roles = $DB->get_records_sql(
            "SELECT r.shortname
               FROM {role_capabilities} rc
               JOIN {role} r ON r.id = rc.roleid
              WHERE rc.capability = :cap AND rc.permission = :allow",
            ['cap' => 'local/pshub:manageadmins', 'allow' => CAP_ALLOW]
        );

        $this->assertEmpty(
            $roles,
            'No role may be granted manageadmins by default: ' . implode(', ', array_keys($roles))
        );
    }

    /**
     * A site administrator holds everything implicitly, including the
     * capability no role grants.
     */
    public function test_site_admin_holds_manageadmins(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue(
            has_capability('local/pshub:manageadmins', \context_system::instance())
        );
    }
}
