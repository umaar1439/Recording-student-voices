/*
 * Application level Alpine components.
 *
 * Livewire dispatches `alpine:init`, so registering here guarantees the
 * components are available before any Livewire component is booted.
 */

const formatClock = (value) => {
    const seconds = Math.max(0, Math.floor(Number(value) || 0));
    const minutes = Math.floor(seconds / 60);

    return `${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
};

const registerComponents = () => {
    if (!window.Alpine || window.__quranAlpineRegistered) {
        return;
    }

    window.__quranAlpineRegistered = true;

    /**
     * Toast notifications dispatched from Livewire via `$this->dispatch('notify', ...)`.
     */
    window.Alpine.data('toastStack', () => ({
        toasts: [],

        init() {
            window.addEventListener('notify', (event) => {
                const detail = event.detail ?? {};
                const message = Array.isArray(detail) ? detail[0] : detail.message;

                if (message) {
                    this.push(message, detail.type ?? 'success');
                }
            });
        },

        push(message, type = 'success') {
            const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;

            this.toasts.push({ id, message, type });
            window.setTimeout(() => this.dismiss(id), 5000);
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },

        icon(type) {
            return {
                success: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                error: 'M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                info: 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
            }[type] ?? '';
        },
    }));

    /**
     * Playback widget for authenticated recording streams.
     */
    window.Alpine.data('audioPlayer', (src = '') => ({
        src,
        audio: null,
        playing: false,
        loading: true,
        current: 0,
        duration: 0,
        speed: 1,
        error: null,

        init() {
            this.audio = new Audio();
            this.audio.preload = 'metadata';
            this.audio.src = this.src;

            this.audio.addEventListener('loadedmetadata', () => {
                this.loading = false;
                this.duration = Number.isFinite(this.audio.duration) ? this.audio.duration : 0;
            });

            this.audio.addEventListener('timeupdate', () => {
                this.current = this.audio.currentTime;
            });

            this.audio.addEventListener('play', () => {
                this.playing = true;
            });

            this.audio.addEventListener('pause', () => {
                this.playing = false;
            });

            this.audio.addEventListener('ended', () => {
                this.playing = false;
                this.current = 0;
                this.audio.currentTime = 0;
            });

            this.audio.addEventListener('error', () => {
                this.loading = false;
                this.playing = false;
                this.error = 'تعذّر تحميل الملف الصوتي.';
            });
        },

        get percent() {
            return this.duration > 0 ? Math.min(100, (this.current / this.duration) * 100) : 0;
        },

        get currentLabel() {
            return formatClock(this.current);
        },

        get durationLabel() {
            return formatClock(this.duration);
        },

        toggle() {
            if (!this.audio || this.error) {
                return;
            }

            if (this.audio.paused) {
                this.audio.play().catch(() => {
                    this.playing = false;
                    this.error = 'تعذّر تشغيل التسجيل في هذا المتصفح.';
                });
            } else {
                this.audio.pause();
            }
        },

        seek(event) {
            const value = Number(event.target.value);

            if (this.audio && Number.isFinite(value)) {
                this.audio.currentTime = value;
                this.current = value;
            }
        },

        cycleSpeed() {
            const speeds = [1, 1.25, 1.5, 0.75];
            const index = speeds.indexOf(this.speed);
            this.speed = speeds[(index + 1) % speeds.length];

            if (this.audio) {
                this.audio.playbackRate = this.speed;
            }
        },

        destroy() {
            if (this.audio) {
                this.audio.pause();
                this.audio.src = '';
            }
        },
    }));

    /**
     * Microphone recorder that converts the capture to 16 kHz mono WAV and
     * uploads it through the student raw-audio endpoint (the Livewire `audio`
     * property remains supported as a fallback for tests and older clients).
     */
    window.Alpine.data('recitationRecorder', (config = {}) => ({
        state: 'idle',
        seconds: 0,
        maxSeconds: config.maxSeconds ?? 120,
        uploadUrl: config.uploadUrl ?? '/student/recordings/audio',
        mimeType: '',
        chunks: [],
        blob: null,
        previewUrl: null,
        progress: 0,
        durationMs: 0,
        error: null,
        supported: true,
        timerId: null,
        mediaRecorder: null,
        stream: null,
        permission: 'unknown',
        permissionHelp: false,
        embedded: false,
        browser: 'other',

        async init() {
            this.supported = typeof window.MediaRecorder !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;
            this.embedded = window.self !== window.top;
            this.browser = this.detectBrowser();

            if (this.supported) {
                await this.watchPermission();
            }
        },

        get isRecording() {
            return this.state === 'recording';
        },

        get isBusy() {
            return this.state === 'uploading' || this.state === 'requesting' || this.state === 'processing';
        },

        get formattedTime() {
            return formatClock(this.seconds);
        },

        get formattedLimit() {
            return formatClock(this.maxSeconds);
        },

        get formattedDuration() {
            return formatClock(Math.round(this.durationMs / 1000));
        },

        get elapsedPercent() {
            return this.maxSeconds > 0 ? Math.min(100, Math.round((this.seconds / this.maxSeconds) * 100)) : 0;
        },

        get extension() {
            const type = (this.mimeType || '').toLowerCase();

            if (type.includes('webm')) return 'webm';
            if (type.includes('ogg') || type.includes('opus')) return 'ogg';
            if (type.includes('mp4') || type.includes('m4a') || type.includes('aac')) return 'm4a';
            if (type.includes('wav')) return 'wav';
            if (type.includes('mpeg') || type.includes('mp3')) return 'mp3';

            return 'webm';
        },

        async toggle() {
            if (this.state === 'recording') {
                this.stop();
                return;
            }

            if (this.state === 'idle') {
                await this.start();
            }
        },

        async start() {
            this.error = null;

            if (!window.isSecureContext) {
                this.error = 'تسجيل الصوت يتطلب اتصالًا آمنًا (HTTPS) أو تشغيل الموقع على localhost.';

                return;
            }

            if (!this.supported) {
                this.error = 'متصفحك لا يدعم تسجيل الصوت. جرّب Chrome أو Firefox أو Edge الحديث.';

                return;
            }

            this.state = 'requesting';

            try {
                this.stream = await this.openMicrophone();
            } catch (error) {
                this.state = 'idle';
                this.permission = await this.queryPermissionState();
                this.permissionHelp = this.isPermissionError(error);
                this.error = this.permissionMessage(error);

                return;
            }

            const candidates = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/ogg;codecs=opus',
                'audio/ogg',
            ];

            const mimeType = candidates.find((type) => {
                try {
                    return window.MediaRecorder.isTypeSupported(type);
                } catch {
                    return false;
                }
            });

            try {
                this.mediaRecorder = mimeType
                    ? new window.MediaRecorder(this.stream, { mimeType })
                    : new window.MediaRecorder(this.stream);
            } catch {
                this.cleanupStream();
                this.state = 'idle';
                this.error = 'تعذّر تهيئة المسجّل الصوتي على هذا الجهاز.';

                return;
            }

            this.chunks = [];
            this.mimeType = this.mediaRecorder.mimeType || mimeType || 'audio/webm';
            this.seconds = 0;
            this.progress = 0;
            this.durationMs = 0;

            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    this.chunks.push(event.data);
                }
            };

            this.mediaRecorder.onstop = () => this.finalize();
            this.mediaRecorder.onerror = () => {
                this.cleanupStream();
                this.state = 'idle';
                this.error = 'حدث خطأ أثناء التسجيل. جرّب مرة أخرى.';
            };

            this.mediaRecorder.start(250);
            this.state = 'recording';
            this.startTimer();
        },

        async openMicrophone() {
            try {
                return await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        channelCount: { ideal: 1 },
                        // The browser may ignore these hints; the recording is
                        // resampled to exactly 16 kHz mono WAV after capture.
                        sampleRate: { ideal: 16000 },
                        sampleSize: { ideal: 16 },
                    },
                });
            } catch (error) {
                if (!this.isConstraintError(error)) {
                    throw error;
                }

                // Some browsers reject the processing hints outright. The WAV
                // conversion already enforces 16 kHz mono, so retry with the
                // most permissive audio request instead of failing the student.
                return navigator.mediaDevices.getUserMedia({ audio: true });
            }
        },

        stop() {
            if (this.state !== 'recording') {
                return;
            }

            this.stopTimer();
            // Give immediate feedback, and make repeated clicks harmless while
            // the browser finishes flushing the last MediaRecorder chunk.
            this.state = 'processing';

            if (!this.mediaRecorder || this.mediaRecorder.state === 'inactive') {
                this.finalize();

                return;
            }

            try {
                this.mediaRecorder.stop();
            } catch {
                this.cleanupStream();
                this.state = 'idle';
                this.error = 'تعذّر إيقاف التسجيل. أغلق الصفحة وأعد المحاولة.';
            }
        },

        async finalize() {
            this.cleanupStream();

            if (!this.chunks.length) {
                this.state = 'idle';
                this.error = 'لم يتم التقاط صوت. تحقّق من الميكروفون ثم أعد المحاولة.';

                return;
            }

            const sourceType = this.mimeType || 'audio/webm';
            const source = new Blob(this.chunks, { type: sourceType });
            this.chunks = [];

            this.state = 'processing';
            this.error = null;

            try {
                const { blob, duration } = await this.convertToWav(source);

                this.blob = blob;
                this.mimeType = 'audio/wav';
                this.durationMs = duration;
            } catch {
                this.blob = null;
                this.state = 'idle';
                this.error = 'تعذّر تحويل التسجيل إلى WAV 16kHz على هذا المتصفح. استخدم Chrome أو Edge أو Firefox حديثًا وأعد المحاولة.';

                return;
            }

            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
            }

            this.previewUrl = URL.createObjectURL(this.blob);
            this.state = 'recorded';
        },

        /**
         * Decode the captured audio, resample it to 16 kHz mono and encode a
         * standard 16-bit PCM WAV file. No external libraries are required.
         */
        async convertToWav(source) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            const OfflineContextClass = window.OfflineAudioContext || window.webkitOfflineAudioContext;

            if (!AudioContextClass || !OfflineContextClass) {
                throw new Error('web-audio-unavailable');
            }

            const arrayBuffer = await source.arrayBuffer();
            const decodingContext = new AudioContextClass();
            let decoded;

            try {
                decoded = await decodingContext.decodeAudioData(arrayBuffer);
            } finally {
                if (decodingContext.close) {
                    await decodingContext.close();
                }
            }

            const sampleRate = 16000;
            const frames = Math.max(1, Math.ceil(decoded.duration * sampleRate));
            let offline;

            try {
                offline = new OfflineContextClass({ numberOfChannels: 1, length: frames, sampleRate });
            } catch {
                offline = new OfflineContextClass(1, frames, sampleRate);
            }

            const sourceNode = offline.createBufferSource();
            sourceNode.buffer = decoded;
            sourceNode.connect(offline.destination);
            sourceNode.start();

            const rendered = await offline.startRendering();

            return {
                blob: this.encodeWav(rendered),
                duration: Math.max(1000, Math.round(rendered.duration * 1000)),
            };
        },

        /**
         * Encode mono PCM samples as a RIFF/WAVE file (16-bit, little endian).
         */
        encodeWav(audioBuffer) {
            const samples = audioBuffer.getChannelData(0);
            const buffer = new ArrayBuffer(44 + samples.length * 2);
            const view = new DataView(buffer);
            const writeString = (offset, value) => {
                for (let index = 0; index < value.length; index += 1) {
                    view.setUint8(offset + index, value.charCodeAt(index));
                }
            };

            writeString(0, 'RIFF');
            view.setUint32(4, 36 + samples.length * 2, true);
            writeString(8, 'WAVE');
            writeString(12, 'fmt ');
            view.setUint32(16, 16, true);
            view.setUint16(20, 1, true);
            view.setUint16(22, 1, true);
            view.setUint32(24, audioBuffer.sampleRate, true);
            view.setUint32(28, audioBuffer.sampleRate * 2, true);
            view.setUint16(32, 2, true);
            view.setUint16(34, 16, true);
            writeString(36, 'data');
            view.setUint32(40, samples.length * 2, true);

            let offset = 44;

            for (let index = 0; index < samples.length; index += 1) {
                const sample = Math.max(-1, Math.min(1, samples[index]));

                view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
                offset += 2;
            }

            return new Blob([view], { type: 'audio/wav' });
        },

        retake() {
            this.discard();
            this.start();
        },

        discard() {
            this.stopTimer();
            this.cleanupStream();

            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
            }

            this.previewUrl = null;
            this.blob = null;
            this.chunks = [];
            this.seconds = 0;
            this.durationMs = 0;
            this.progress = 0;
            this.error = null;
            this.state = 'idle';
        },

        async upload(key) {
            if (!this.blob || this.state !== 'recorded') {
                return;
            }

            this.error = null;
            this.progress = 0;
            this.state = 'uploading';

            try {
                const token = await this.uploadAudio();
                const saved = await this.$wire.call('submit', key, this.durationMs, token);

                // Keep the preview so the student can retry if validation
                // failed (e.g. no teacher selected) or the task was claimed by
                // another student before the submission landed.
                if (saved !== true) {
                    this.state = 'recorded';

                    return;
                }

                this.discard();
            } catch (error) {
                this.state = 'recorded';
                this.error = error?.message === 'too-large'
                    ? 'حجم التسجيل كبير جدًا على الخادم. جرّب تسجيلًا أقصر.'
                    : 'تعذّر رفع التسجيل. تحقّق من اتصال الإنترنت ثم حاول مجددًا.';
            }
        },

        /**
         * Send the WAV as a raw request body. Uploading as multipart would be
         * capped by PHP's upload_max_filesize (2 MB by default); a raw body is
         * only limited by post_max_size and is stored under a one-time token.
         */
        uploadAudio() {
            return new Promise((resolve, reject) => {
                const request = new XMLHttpRequest();

                request.open('POST', this.uploadUrl);
                request.setRequestHeader('Content-Type', 'audio/wav');
                request.setRequestHeader('Accept', 'application/json');
                request.setRequestHeader(
                    'X-CSRF-TOKEN',
                    document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                );

                request.upload.onprogress = (event) => {
                    if (event.lengthComputable && event.total > 0) {
                        this.progress = Math.round((event.loaded / event.total) * 100);
                    }
                };

                request.onload = () => {
                    if (request.status >= 200 && request.status < 300) {
                        try {
                            const data = JSON.parse(request.responseText);

                            if (data.token) {
                                resolve(data.token);

                                return;
                            }
                        } catch {
                            // Fall through to the generic failure below.
                        }

                        reject(new Error('upload-failed'));

                        return;
                    }

                    reject(new Error(request.status === 413 ? 'too-large' : 'upload-failed'));
                };

                request.onerror = () => reject(new Error('upload-failed'));
                request.send(this.blob);
            });
        },

        startTimer() {
            this.stopTimer();
            this.timerId = window.setInterval(() => {
                this.seconds = Math.min(this.seconds + 1, this.maxSeconds);

                if (this.seconds >= this.maxSeconds) {
                    this.stop();
                }
            }, 1000);
        },

        stopTimer() {
            if (this.timerId) {
                window.clearInterval(this.timerId);
                this.timerId = null;
            }
        },

        cleanupStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
        },

        get permissionTitle() {
            if (this.embedded) {
                return 'الميكروفون ممنوع داخل الإطار المضمّن';
            }

            return this.permission === 'denied'
                ? 'الميكروفون محظور في هذا المتصفح'
                : 'يجب السماح بالوصول إلى الميكروفون';
        },

        get permissionIntro() {
            if (this.embedded) {
                return 'الموقع مفتوح داخل إطار مضمّن، والمتصفح يمنع الوصول إلى الميكروفون من الإطارات. افتح الموقع في نافذة مستقلة ثم أعد المحاولة.';
            }

            if (this.permission === 'denied') {
                return 'حظرتَ إذن الميكروفون سابقًا، ولا يستطيع الموقع إعادة فتح رسالة الإذن بنفسه. اسمح بالوصول من إعدادات المتصفح كما في الخطوات التالية ثم اضغط «إعادة المحاولة».';
            }

            return 'اسمح بالوصول إلى الميكروفون عندما يطلب المتصفح ذلك، أو من إعدادات الموقع، ثم اضغط «إعادة المحاولة».';
        },

        get permissionSteps() {
            const steps = {
                chrome: [
                    'اضغط أيقونة القفل (أو «ضبط الأذونات») بجوار عنوان الموقع.',
                    'اختر «إعدادات الموقع» ثم «الميكروفون».',
                    'غيّر الإذن إلى «السماح» ثم اضغط «إعادة المحاولة».',
                ],
                edge: [
                    'اضغط أيقونة القفل بجوار عنوان الموقع.',
                    'اختر «الأذونات الخاصة بهذا الموقع» ثم «الميكروفون».',
                    'غيّر الإذن إلى «السماح» ثم اضغط «إعادة المحاولة».',
                ],
                firefox: [
                    'اضغط أيقونة القفل بجوار عنوان الموقع.',
                    'اختر «مسح الإذن» أو «إعدادات الأذونات» لميكروفون هذا الموقع.',
                    'اسمح بالميكروفون ثم اضغط «إعادة المحاولة».',
                ],
                safari: [
                    'من شريط القوائم: Safari ← الإعدادات ← المواقع.',
                    'اختر «الميكروفون» ثم اسمح بهذا الموقع.',
                    'أعد تحميل الصفحة ثم اضغط «إعادة المحاولة».',
                ],
                ios: [
                    'من إعدادات الجهاز: الإعدادات ← Safari (أو Chrome) ← الميكروفون ← السماح.',
                    'أو اضغط «aA» في شريط العنوان ← إعدادات الموقع ← الميكروفون ← السماح (Safari).',
                    'أعد تحميل الصفحة ثم اضغط «إعادة المحاولة».',
                ],
                android: [
                    'اضغط قائمة المتصفح (⋮) ← الإعدادات ← إعدادات المواقع.',
                    'اختر «الميكروفون» ثم اسمح بهذا الموقع.',
                    'أعد تحميل الصفحة ثم اضغط «إعادة المحاولة».',
                ],
                other: [
                    'افتح إعدادات الأذونات أو المواقع في متصفحك.',
                    'اسمح للميكروفون لهذا الموقع.',
                    'أعد تحميل الصفحة ثم اضغط «إعادة المحاولة».',
                ],
            };

            return steps[this.browser] ?? steps.other;
        },

        async watchPermission() {
            const status = await this.permissionStatus();

            if (status === null) {
                return;
            }

            this.applyPermissionState(status.state);

            status.onchange = () => this.applyPermissionState(status.state);
        },

        applyPermissionState(state) {
            this.permission = state;

            if (state === 'granted') {
                this.permissionHelp = false;
                this.error = null;
            }

            if (state === 'denied' && this.state === 'idle' && window.isSecureContext) {
                this.permissionHelp = true;
            }
        },

        async queryPermissionState() {
            const status = await this.permissionStatus();

            return status?.state ?? 'unknown';
        },

        async permissionStatus() {
            try {
                if (!navigator.permissions?.query) {
                    return null;
                }

                return await navigator.permissions.query({ name: 'microphone' });
            } catch {
                // Safari and some embedded browsers do not expose the
                // microphone descriptor; the guarded request still works.
                return null;
            }
        },

        isPermissionError(error) {
            return ['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(error?.name);
        },

        isConstraintError(error) {
            return ['OverconstrainedError', 'ConstraintNotSatisfiedError', 'TypeError'].includes(error?.name);
        },

        retryPermission() {
            if (this.state !== 'idle') {
                return;
            }

            this.start();
        },

        openInNewWindow() {
            window.open(window.location.href, '_blank', 'noopener');
        },

        reloadPage() {
            window.location.reload();
        },

        detectBrowser() {
            const agent = navigator.userAgent;

            if (/iPhone|iPad|iPod/i.test(agent)) return 'ios';
            if (/Android/i.test(agent) && /Chrome|CriOS/i.test(agent)) return 'android';
            if (/Edg\//i.test(agent)) return 'edge';
            if (/Firefox\/|FxiOS\//i.test(agent)) return 'firefox';
            if (/Chrome\/|CriOS\//i.test(agent)) return 'chrome';
            if (/Safari\//i.test(agent)) return 'safari';

            return 'other';
        },

        permissionMessage(error) {
            switch (error?.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                case 'SecurityError':
                    return this.permission === 'denied'
                        ? 'تم حظر إذن الميكروفون لهذا الموقع. اتّبع خطوات الأذونات التالية ثم أعد المحاولة.'
                        : 'لم يُمنح إذن الميكروفون. اسمح بالوصول من رسالة المتصفح أو إعدادات الموقع ثم أعد المحاولة.';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    return 'لم يتم العثور على ميكروفون متصل بهذا الجهاز.';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'الميكروفون مشغول بتطبيق آخر. أغلق التطبيقات الأخرى ثم حاول مجددًا.';
                case 'OverconstrainedError':
                case 'ConstraintSatisfiedError':
                    return 'الميكروفون لا يدعم الإعدادات المطلوبة. جرّب جهازًا آخر.';
                default:
                    return 'تعذّر بدء التسجيل. تأكد من توصيل الميكروفون ومنح الإذن.';
            }
        },

        destroy() {
            this.stopTimer();

            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                try {
                    this.mediaRecorder.stop();
                } catch {
                    // The stream is stopped below even if the recorder is gone.
                }
            }

            this.mediaRecorder = null;
            this.cleanupStream();

            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
            }
        },
    }));
};

document.addEventListener('alpine:init', registerComponents);
registerComponents();

