// This file is part of Moodle - http://moodle.org/.

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const pauseOtherVideos = (current) => {
    document.querySelectorAll('.videoforum-video').forEach((video) => {
        if (video !== current && !video.paused) {
            video.pause();
        }
    });
};

const track = (cmid, video, ended) => {
    const postid = Number(video.dataset.postid || 0);
    if (!postid) {
        return;
    }

    Ajax.call([{
        methodname: 'mod_videoforum_track_view',
        args: {
            cmid,
            postid,
            position: Number(video.currentTime || 0),
            ended: Boolean(ended),
        },
    }])[0].catch(Notification.exception);
};

const moderate = (cmid, button) => {
    const action = button.dataset.moderation;
    const postid = Number(button.dataset.postid || 0);

    if (action === 'delete' && !window.confirm(button.textContent.trim() + '?')) {
        return;
    }

    button.disabled = true;
    Ajax.call([{
        methodname: 'mod_videoforum_moderate_post',
        args: {cmid, postid, action},
    }])[0].then(() => window.location.reload()).catch((error) => {
        button.disabled = false;
        Notification.exception(error);
    });
};

const report = (cmid, button) => {
    const postid = Number(button.dataset.postid || 0);
    const details = window.prompt(button.textContent.trim());
    if (details === null) {
        return;
    }

    button.disabled = true;
    Ajax.call([{
        methodname: 'mod_videoforum_report_post',
        args: {
            cmid,
            postid,
            reason: 'other',
            details,
        },
    }])[0].then(() => {
        button.disabled = true;
    }).catch((error) => {
        button.disabled = false;
        Notification.exception(error);
    });
};

export const init = (config) => {
    const cmid = Number(config.cmid || 0);
    if (!cmid) {
        return;
    }

    document.querySelectorAll('.videoforum-video').forEach((video) => {
        video.addEventListener('play', () => pauseOtherVideos(video));
        video.addEventListener('pause', () => {
            if (!video.ended && video.currentTime > 0) {
                track(cmid, video, false);
            }
        });
        video.addEventListener('ended', () => track(cmid, video, true));
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }
        if (button.dataset.action === 'moderate') {
            moderate(cmid, button);
        } else if (button.dataset.action === 'report') {
            report(cmid, button);
        }
    });
};
