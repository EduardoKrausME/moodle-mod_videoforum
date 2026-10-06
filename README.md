# Video Forum

Video Forum is a video-first asynchronous discussion activity for Moodle. Topics and replies are short videos, so the interaction model is closer to a vertical Reels/TikTok-style feed than to a text forum with video attachments.

It is an independent activity module with its own data model, publication workflow, permissions, moderation rules and interface. It does not wrap or restyle `mod_forum`.

## What the activity does

A teacher creates a Video Forum and controls the maximum video duration, whether students may open topics or reply, the maximum number of replies in a thread, topic and reply deadlines, Moodle group mode, publication-before-view rules, browser recording, file upload, participation grading and activity-completion targets.

Students enter a vertical feed of large topic videos. From there they can publish a new topic, open an existing topic and navigate its threaded video replies. Replies may target the topic or another reply, preserving the conversation context without turning the page into a text-heavy discussion board.

Each publication shows the author, avatar, date, duration, video, reply count and the actions available to the current user. A short title and description are optional and deliberately limited because video is the primary content.

Only one video plays at a time. Starting playback on another publication pauses the previous player.

## Recording and upload workflow

Browser recording uses `MediaRecorder` and asks for camera and microphone permission only when the user explicitly starts recording. The recorder shows a live preview and counter, stops at the activity duration limit, releases `MediaStream` tracks when it is finished or cancelled, and switches to a reviewable playback preview before publication.

MP4 and WebM uploads are also supported when enabled by the teacher.

Publication uses a two-stage Moodle File API flow. The browser first sends the media into a draft area owned by the authenticated user, then an AJAX external function creates the publication and moves the validated file into the module file area. The server validates draft ownership, file count, extension, MIME type and the actual media duration before a publication is committed. The draft metadata is also bound to the Video Forum course module, reply target and enabled source path so a different user draft or a draft prepared for another thread cannot be reused as publication authority.

## Threads and publication-before-view

A root publication represents a topic. Replies use `parentid` for the direct reply target and `rootid` for the thread, which allows the interface to preserve reply context while still enforcing thread-wide rules such as maximum replies and locking.

When publication-before-view is enabled, students can watch the root topic but cannot list or fetch classmates' reply videos until they have published in that thread themselves. This rule is enforced in the domain layer and again when Moodle serves the stored file, so hiding a reply only in Mustache or JavaScript is not treated as access control.

## Moodle Groups

The activity uses Moodle's standard group mode. With separate groups, students can only receive publications belonging to groups they can access. With visible groups, publications remain visible according to Moodle's group semantics.

A new topic's `groupid` is resolved on the server from Moodle's current group context and the authenticated user's membership; a group id supplied by the browser is never accepted as authoritative. Replies inherit the root topic's group.

## Moderation and reports

Users with moderation capability can hide or restore a publication, soft-delete it and its media, lock or unlock new replies on a root topic, and review reports submitted by participants.

Deleting a root topic marks the complete thread as deleted and removes all associated video files. Hiding, restoring or deleting reported content also marks its open reports as reviewed, preserving the audit record instead of silently discarding reports.

Participants may report visible publications when the capability is available, but cannot report their own publication or submit the same report twice.

## Participation report

Teachers can open the activity report to see, per enrolled user:

- topics created;
- replies published;
- total publications;
- videos watched;
- most recent activity.

The report also exposes submitted publication reports to users with the dedicated report-review capability.

Playback tracking is deliberately bounded by server-known video duration. Browser values are treated as hints, not trusted counters, and a view is marked complete only when playback reports the end close to the validated media duration.

## Gradebook and completion

Participation grading is optional. When enabled, Video Forum maintains a gradebook item and calculates participation from the configured publication targets. If no custom target is set, publishing once is treated as full participation.

Custom completion can require any combination of:

- at least X root topics;
- at least X replies;
- at least X total publications.

Deleted publications do not count toward completion or participation grades.

## Security model

All state-changing browser calls are validated against the authenticated Moodle session, canonical course module, module context and capabilities. AJAX methods do not accept a user id or context id from the client.

A supplied `postid` is always reloaded with the expected `videoforumid`, parent/root relationships are resolved from the database, group ownership is resolved server-side, and file access re-runs visibility checks. The same rules are reused by publishing, moderation, reporting, playback tracking and `pluginfile` rather than being duplicated as UI-only checks.

The upload endpoint requires a valid session key and revalidates the user's ability to create a topic or reply before accepting bytes. Final publication validates the draft again, which prevents a stale or foreign draft item from being attached to a post.

## Moodle subsystem integration

Video Forum implements Moodle capabilities, groups/groupings, events, Privacy API, Backup and Restore, course reset, File API, Gradebook, custom activity completion, Mustache templates, AMD JavaScript and AJAX external functions.

User data handled by the Privacy API includes publications and their video files, submitted reports and playback progress. When an individual user's data is erased, their media and text are removed while deleted placeholders are retained where necessary to keep other users' thread references coherent.

Backup can include topics, replies, reports, playback records and publication files as user data. Restore remaps users, groups, parent/root relationships and file item ids, and skips replies whose required parent/root mapping could not be restored instead of converting them into accidental root topics.

## Automated tests

PHPUnit tests focus on domain rules that must remain valid regardless of the interface: activity-scoped post lookup, publication-before-view, reply limits, moderator bypass, separate-group visibility and custom-completion counters.

Behat scenarios exercise the browser flow with a real short WebM fixture: a student uploads a topic, replies with another video, and a teacher hides and restores student content. Camera permission itself is intentionally not automated by Behat because the browser test runner should not fake the trust decision that `getUserMedia()` is designed to expose to a real user.
