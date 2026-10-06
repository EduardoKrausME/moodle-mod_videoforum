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
 * Authenticated video upload endpoint for Video Forum.
 *
 * @package    mod_videoforum
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_login();
require_once($CFG->libdir . '/filelib.php');

use mod_videoforum\local\manager;
use mod_videoforum\local\media_probe;

header('Content-Type: application/json; charset=utf-8');

try {
    require_sesskey();

    $cmid = required_param('cmid', PARAM_INT);
    $parentid = optional_param('parentid', 0, PARAM_INT);
    $declaredduration = required_param('duration', PARAM_FLOAT);
    $source = required_param('source', PARAM_ALPHA);

    [$cm, $course, $activity, $context] = manager::require_runtime($cmid);
    manager::assert_can_publish($cm, $activity, $parentid, (int)$USER->id);

    if ($source === 'recording') {
        if (empty($activity->allowrecording)) {
            throw new moodle_exception('invalidvideo', 'videoforum');
        }
    } else if ($source === 'upload') {
        if (empty($activity->allowupload)) {
            throw new moodle_exception('invalidvideo', 'videoforum');
        }
    } else {
        throw new moodle_exception('invalidvideo', 'videoforum');
    }

    if (!isset($_FILES['video']) || !is_uploaded_file($_FILES['video']['tmp_name'])) {
        throw new moodle_exception('invalidvideo', 'videoforum');
    }

    $upload = $_FILES['video'];
    if ((int)$upload['error'] !== UPLOAD_ERR_OK || (int)$upload['size'] <= 0) {
        throw new moodle_exception('uploadfailed', 'videoforum');
    }

    $maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes, 0);
    if ($maxbytes > 0 && (int)$upload['size'] > $maxbytes) {
        throw new moodle_exception('uploadfailed', 'videoforum');
    }

    $filename = clean_param((string)$upload['name'], PARAM_FILE);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['mp4', 'webm'], true)) {
        throw new moodle_exception('invalidvideo', 'videoforum');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimetype = (string)$finfo->file($upload['tmp_name']);
    $allowedmimes = [
        'video/mp4' => 'video/mp4',
        'application/mp4' => 'video/mp4',
        'video/webm' => 'video/webm',
        'video/x-matroska' => 'video/webm',
    ];
    if (!isset($allowedmimes[$mimetype])) {
        throw new moodle_exception('invalidvideo', 'videoforum');
    }
    $mimetype = $allowedmimes[$mimetype];

    if ($declaredduration <= 0 || $declaredduration > (int)$activity->maxduration + 0.5) {
        throw new moodle_exception('videotoolong', 'videoforum');
    }

    $measuredduration = media_probe::duration($upload['tmp_name'], $mimetype);
    if ($measuredduration !== null && $measuredduration > (int)$activity->maxduration + 0.5) {
        throw new moodle_exception('videotoolong', 'videoforum');
    }
    $duration = $measuredduration ?? $declaredduration;

    $draftitemid = file_get_unused_draft_itemid();
    $usercontext = context_user::instance((int)$USER->id);
    $record = [
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftitemid,
        'filepath' => '/',
        'filename' => $filename ?: ('videoforum.' . $extension),
        'userid' => (int)$USER->id,
        'mimetype' => $mimetype,
    ];

    get_file_storage()->create_file_from_pathname($record, $upload['tmp_name']);

    $DB->delete_records_select(
        'videoforum_draft',
        'userid = :userid AND timecreated < :expired',
        ['userid' => $USER->id, 'expired' => time() - DAYSECS]
    );

    $DB->insert_record('videoforum_draft', (object)[
        'videoforumid' => (int)$activity->id,
        'userid' => (int)$USER->id,
        'draftitemid' => $draftitemid,
        'parentid' => $parentid,
        'filename' => $record['filename'],
        'mimetype' => $mimetype,
        'duration' => $duration,
        'timecreated' => time(),
    ]);

    echo json_encode([
        'success' => true,
        'draftitemid' => $draftitemid,
        'duration' => $duration,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception instanceof moodle_exception
            ? $exception->getMessage()
            : get_string('uploadfailed', 'videoforum'),
    ], JSON_THROW_ON_ERROR);
}
