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
 * External service definitions for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_videoforum_create_post' => [
        'classname' => 'mod_videoforum\\external\\api',
        'methodname' => 'create_post',
        'description' => 'Publish a topic or reply from an authenticated draft video.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_videoforum_moderate_post' => [
        'classname' => 'mod_videoforum\\external\\api',
        'methodname' => 'moderate_post',
        'description' => 'Hide, restore, delete, lock or unlock a publication.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_videoforum_report_post' => [
        'classname' => 'mod_videoforum\\external\\api',
        'methodname' => 'report_post',
        'description' => 'Report a visible publication.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'mod_videoforum_track_view' => [
        'classname' => 'mod_videoforum\\external\\api',
        'methodname' => 'track_view',
        'description' => 'Record bounded video viewing progress.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
