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
 * recorder.js
 *
 * @package   mod_videoforum
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const formatCounter = (seconds) => {
    const whole = Math.max(0, Math.floor(seconds));
    const minutes = String(Math.floor(whole / 60)).padStart(2, '0');
    const remainder = String(whole % 60).padStart(2, '0');
    return `${minutes}:${remainder}`;
};

const getDuration = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const video = document.createElement('video');
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
        const duration = Number(video.duration || 0);
        URL.revokeObjectURL(url);
        if (!Number.isFinite(duration) || duration <= 0) {
            reject(new Error('Invalid video duration'));
            return;
        }
        resolve(duration);
    };
    video.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Invalid video'));
    };
    video.src = url;
});

class Recorder {
    constructor(root, config) {
        this.root = root;
        this.cmid = Number(config.cmid);
        this.maxduration = Number(root.dataset.maxduration || config.maxduration || 120);
        this.preview = root.querySelector('[data-role="preview"]');
        this.counter = root.querySelector('[data-role="counter"]');
        this.parent = root.querySelector('[data-field="parentid"]');
        this.fileinput = root.querySelector('[data-field="file"]');
        this.publishbutton = root.querySelector('[data-action="publish"]');
        this.recordbutton = root.querySelector('[data-action="record"]');
        this.stopbutton = root.querySelector('[data-action="stop"]');

        this.stream = null;
        this.mediaRecorder = null;
        this.chunks = [];
        this.file = null;
        this.source = null;
        this.duration = 0;
        this.startedAt = 0;
        this.timer = null;
        this.objectUrl = null;

        this.bind();
    }

    bind() {
        this.recordbutton?.addEventListener('click', () => this.startRecording());
        this.stopbutton?.addEventListener('click', () => this.stopRecording());
        this.root.querySelector('[data-action="cancel"]')?.addEventListener('click', () => this.reset());
        this.publishbutton?.addEventListener('click', () => this.publish());

        this.fileinput?.addEventListener('change', async() => {
            const file = this.fileinput.files?.[0];
            if (!file) {
                return;
            }
            try {
                const duration = await getDuration(file);
                if (duration > this.maxduration + 0.5) {
                    throw new Error('Video exceeds maximum duration');
                }
                this.stopStream();
                this.setPreview(file);
                this.file = file;
                this.source = 'upload';
                this.duration = duration;
                this.publishbutton.disabled = false;
                this.updateCounter(duration);
            } catch (error) {
                Notification.exception(error);
                this.fileinput.value = '';
            }
        });

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="reply"]');
            if (!button || !this.root.isConnected) {
                return;
            }
            this.parent.value = String(Number(button.dataset.postid || 0));
            this.root.scrollIntoView({behavior: 'smooth', block: 'center'});
        });

        window.addEventListener('beforeunload', () => this.stopStream());
    }

    async startRecording() {
        try {
            this.reset(false);
            this.stream = await navigator.mediaDevices.getUserMedia({video: true, audio: true});
            this.preview.srcObject = this.stream;
            this.preview.muted = true;
            await this.preview.play();

            let options = {};
            for (const type of ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']) {
                if (window.MediaRecorder?.isTypeSupported(type)) {
                    options = {mimeType: type};
                    break;
                }
            }

            this.chunks = [];
            this.mediaRecorder = new MediaRecorder(this.stream, options);
            this.mediaRecorder.addEventListener('dataavailable', (event) => {
                if (event.data?.size) {
                    this.chunks.push(event.data);
                }
            });
            this.mediaRecorder.addEventListener('stop', () => this.finishRecording());
            this.mediaRecorder.start(1000);

            this.startedAt = performance.now();
            this.recordbutton?.classList.add('d-none');
            this.stopbutton?.classList.remove('d-none');
            this.timer = window.setInterval(() => {
                const elapsed = (performance.now() - this.startedAt) / 1000;
                this.updateCounter(elapsed);
                if (elapsed >= this.maxduration) {
                    this.stopRecording();
                }
            }, 250);
        } catch (error) {
            this.stopStream();
            Notification.exception(error);
        }
    }

    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.duration = Math.min(this.maxduration, (performance.now() - this.startedAt) / 1000);
            this.mediaRecorder.stop();
        } else {
            this.stopStream();
        }
    }

    finishRecording() {
        window.clearInterval(this.timer);
        this.timer = null;

        const type = this.mediaRecorder?.mimeType || 'video/webm';
        const blob = new Blob(this.chunks, {type});
        this.file = new File([blob], 'videoforum-' + Date.now() + '.webm', {type: 'video/webm'});
        this.source = 'recording';
        this.setPreview(this.file);
        this.preview.muted = false;
        this.stopStream();

        this.recordbutton?.classList.remove('d-none');
        this.stopbutton?.classList.add('d-none');
        this.publishbutton.disabled = this.duration <= 0;
        this.updateCounter(this.duration);
    }

    stopStream() {
        if (this.stream) {
            this.stream.getTracks().forEach((track) => track.stop());
        }
        this.stream = null;
        if (this.preview) {
            this.preview.srcObject = null;
        }
    }

    setPreview(file) {
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
        }
        this.objectUrl = URL.createObjectURL(file);
        this.preview.srcObject = null;
        this.preview.src = this.objectUrl;
        this.preview.controls = true;
    }

    updateCounter(seconds) {
        this.counter.textContent = `${formatCounter(seconds)} / ${formatCounter(this.maxduration)}`;
    }

    reset(clearFile = true) {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        window.clearInterval(this.timer);
        this.timer = null;
        this.stopStream();

        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
        this.preview.removeAttribute('src');
        this.preview.load();
        this.preview.muted = false;

        if (clearFile) {
            this.file = null;
            this.source = null;
            this.duration = 0;
            if (this.fileinput) {
                this.fileinput.value = '';
            }
            this.publishbutton.disabled = true;
            this.updateCounter(0);
        }

        this.recordbutton?.classList.remove('d-none');
        this.stopbutton?.classList.add('d-none');
    }

    async publish() {
        if (!this.file || !this.source || this.duration <= 0) {
            return;
        }

        this.publishbutton.disabled = true;
        try {
            const form = new FormData();
            form.append('video', this.file);
            form.append('cmid', String(this.cmid));
            form.append('parentid', this.parent.value || '0');
            form.append('duration', String(this.duration));
            form.append('source', this.source);
            form.append('sesskey', M.cfg.sesskey);

            const response = await fetch(M.cfg.wwwroot + '/mod/videoforum/upload.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Video upload failed');
            }

            await Ajax.call([{
                methodname: 'mod_videoforum_create_post',
                args: {
                    cmid: this.cmid,
                    draftitemid: Number(payload.draftitemid),
                    parentid: Number(this.parent.value || 0),
                    title: this.root.querySelector('[data-field="title"]').value || '',
                    description: this.root.querySelector('[data-field="description"]').value || '',
                },
            }])[0];

            window.location.reload();
        } catch (error) {
            this.publishbutton.disabled = false;
            Notification.exception(error);
        }
    }
}

export const init = (config) => {
    document.querySelectorAll('.videoforum-recorder').forEach((root) => {
        if (root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';
        new Recorder(root, config);
    });
};
