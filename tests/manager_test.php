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

/**
 * Tests for Video Forum domain rules.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videoforum;

use mod_videoforum\local\manager;

/**
 * Domain rule tests.
 *
 * @package mod_videoforum
 * @covers \mod_videoforum\local\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Test Counts Topics And Replies.
     *
     * @return void
     */
    public function test_counts_topics_and_replies(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $activity = $this->getDataGenerator()->create_module('videoforum', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoforum');

        $root = $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $user->id,
            'title' => 'Root',
        ]);
        $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $user->id,
            'parentid' => $root->id,
            'rootid' => $root->id,
            'title' => 'Reply',
        ]);

        $this->assertSame(
            ['topics' => 1, 'replies' => 1, 'total' => 2],
            manager::count_user_posts((int)$activity->id, (int)$user->id)
        );
    }

    /**
     * Test Post Before View Hides Classmate Reply Until Contribution.
     *
     * @return void
     */
    public function test_post_before_view_hides_classmate_reply_until_contribution(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);
        $this->getDataGenerator()->enrol_user($viewer->id, $course->id);
        $this->getDataGenerator()->enrol_user($other->id, $course->id);

        $activity = $this->getDataGenerator()->create_module('videoforum', [
            'course' => $course->id,
            'postbeforeview' => 1,
        ]);
        $cm = get_coursemodule_from_instance('videoforum', $activity->id, $course->id, false, MUST_EXIST);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoforum');

        $root = $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $author->id,
        ]);
        $reply = $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $other->id,
            'parentid' => $root->id,
            'rootid' => $root->id,
        ]);

        $this->assertFalse(manager::can_view_post($cm, $activity, $reply, (int)$viewer->id));

        $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $viewer->id,
            'parentid' => $root->id,
            'rootid' => $root->id,
        ]);

        $this->assertTrue(manager::can_view_post($cm, $activity, $reply, (int)$viewer->id));
    }

    /**
     * Test Reply Limit Is Checked From Canonical Thread.
     *
     * @return void
     */
    public function test_reply_limit_is_checked_from_canonical_thread(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $activity = $this->getDataGenerator()->create_module('videoforum', [
            'course' => $course->id,
            'maxreplies' => 1,
        ]);
        $cm = get_coursemodule_from_instance('videoforum', $activity->id, $course->id, false, MUST_EXIST);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_videoforum');

        $root = $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $user->id,
        ]);
        $generator->create_post((object)[
            'videoforumid' => $activity->id,
            'userid' => $user->id,
            'parentid' => $root->id,
            'rootid' => $root->id,
        ]);

        $this->setUser($user);
        $this->expectException(\moodle_exception::class);
        manager::assert_can_publish($cm, $activity, (int)$root->id, (int)$user->id);
    }

    /**
     * Test Separate Group Visibility.
     *
     * @return void
     */
    public function test_separate_group_visibility(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($userone->id, $course->id);
        $this->getDataGenerator()->enrol_user($usertwo->id, $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $userone->id]);

        $activity = $this->getDataGenerator()->create_module('videoforum', [
            'course' => $course->id,
            'groupmode' => SEPARATEGROUPS,
        ]);
        $cm = get_coursemodule_from_instance('videoforum', $activity->id, $course->id, false, MUST_EXIST);

        $this->assertTrue(manager::can_access_group($cm, (int)$group->id, (int)$userone->id));
        $this->assertFalse(manager::can_access_group($cm, (int)$group->id, (int)$usertwo->id));
    }
}
