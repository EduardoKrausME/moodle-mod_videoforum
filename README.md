# Video Forum

Video Forum is a video-first asynchronous discussion activity for Moodle. Topics and replies are short videos, so the interaction model is closer to a vertical Reels/TikTok-style feed than to a text forum with video attachments.

It is an independent activity module with its own data model, publication workflow, permissions, moderation rules and interface. It does not wrap or restyle `mod_forum`.

## Main flow

Teachers configure the maximum video duration, topic and reply permissions, reply limits, publication deadlines, Moodle groups, publication-before-view, browser recording, uploads, optional participation grading and custom completion targets.

Learners open a vertical feed, publish a video topic, open a topic and answer it with another video. A reply may target the root topic or another reply while remaining part of the same thread. Text is deliberately secondary and limited to a short title and description.

Only one video plays at a time. Starting another player pauses the current one.

## Recording and upload

Browser recording uses `MediaRecorder`. Camera and microphone permission is requested only after the learner explicitly starts recording. The interface shows a live preview and elapsed time, stops at the configured duration, lets the learner review the result and always releases the `MediaStream` tracks after stopping, cancelling or leaving the page.

MP4 and WebM uploads are supported when enabled. Uploads use a two-stage Moodle File API flow: a dedicated authenticated endpoint creates a draft item owned by the current user, then the AJAX publication call validates that draft binding and moves the file into the module file area.

The server validates the Moodle session, canonical course module, capability, publication target, draft owner, file count, extension, MIME type, size and configured duration declaration. For containers whose duration metadata can be read reliably by the server, the measured duration is also checked against the configured maximum.

## Threads and publication-before-view

A root post is a topic. Replies store both their direct `parentid` and thread `rootid`.

When publication-before-view is enabled, learners can view the topic but cannot retrieve classmates' replies until they have contributed to that thread. The same visibility rule is used by page rendering and `pluginfile`, so hiding reply metadata in JavaScript is never treated as access control.

## Groups

The activity uses Moodle group mode. New topic group ownership is resolved server-side from Moodle's current group and the authenticated user's memberships. Replies inherit the root topic group. A group id sent by the browser is never accepted as authoritative.

## Moderation and reports

Teachers with the appropriate capability can hide, restore and delete publications, lock or unlock a topic against new replies, and review participant reports. Deleting a root topic soft-deletes the complete thread and removes its video files.

The participation report shows topics, replies, total publications, completed video views and last activity for enrolled users. Moderators can also review submitted content reports.

## Gradebook and completion

Participation grading is optional. When enabled, the grade is proportional to the configured publication target and capped at the activity maximum grade.

Custom completion can require any combination of a minimum number of topics, replies and total publications. Deleted publications do not count.

## Security model

All browser state changes resolve the activity from `cmid`, call `require_login`, validate the module context and check capabilities on the server. AJAX calls never accept a user id or context id as authority.

Every supplied post id is reloaded inside the expected Video Forum instance, parent/root relationships come from the database, group access is recomputed and draft items are bound to the authenticated user and intended reply target before publication.

## Moodle integrations

Video Forum implements Moodle capabilities, Groups, Events, Privacy API, Backup/Restore, course reset, File API, Gradebook, custom completion, Mustache templates, AMD JavaScript and AJAX external functions.

## Automated tests

PHPUnit covers the main domain rules, including activity-scoped post lookup, publication-before-view, reply limits, group visibility and completion counts. Behat covers the critical learner and moderation flows using generated video posts rather than faking browser camera permission.
