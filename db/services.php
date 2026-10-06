<?php
// This file is part of Moodle - http://moodle.org/.

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
