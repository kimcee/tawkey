@php($meta = $channelMeta[$channel] ?? ['name' => '—', 'freq' => '00.0'])

<div wire:poll.2000ms="checkForMessages" class="app-shell" x-data="talkie()" x-cloak>

    {{-- Brand --}}
    <header class="brand">
        TALKIE
        <small>DEEP&nbsp;SPACE&nbsp;PUSH&#8209;TO&#8209;TALK&nbsp;NETWORK</small>
    </header>

    {{-- Channel dial --}}
    <section class="dial-wrap glass" style="padding: 26px 30px; margin-top: 8px;">
        <div class="dial" :class="{ 'dial-locking': locking }" wire:key="dial-{{ $channel }}">
            <div class="dial-ring"></div>

            {{-- rotating tick marks, current channel snaps to top --}}
            <div class="dial-ticks" style="transform: rotate({{ -($channel - 1) * 36 }}deg);">
                @for ($i = 1; $i <= \App\Livewire\WalkieTalkie::CHANNELS; $i++)
                    <div class="tick {{ $i === $channel ? 'on' : '' }}"
                         style="transform: rotate({{ ($i - 1) * 36 }}deg);">
                        <i></i>
                    </div>
                @endfor
            </div>

            <div class="dial-core">
                <div class="dial-ch">{{ str_pad((string) $channel, 2, '0', STR_PAD_LEFT) }}</div>
                <div class="dial-name">{{ $meta['name'] }}</div>
                <div class="dial-freq">{{ $meta['freq'] }} MHz</div>
            </div>
        </div>

        {{-- +/- channel controls --}}
        <div style="display:flex; align-items:center; justify-content:center; gap:34px; margin-top:22px;">
            <button type="button" class="nav-btn" wire:click="prevChannel" aria-label="Previous channel">&minus;</button>
            <div style="font-size:10px; letter-spacing:.34em; text-indent:.34em; color:rgba(180,205,255,.55);">CHANNEL</div>
            <button type="button" class="nav-btn" wire:click="nextChannel" aria-label="Next channel">+</button>
        </div>
    </section>

    {{-- Equalizer --}}
    <section style="width:100%; max-width:420px;">
        <div class="eq" x-ref="eq"
             :class="{ 'live': recording, 'rx': receiving }">
            @for ($i = 0; $i < 32; $i++)<b></b>@endfor
        </div>

        {{-- Status --}}
        <div class="status-line" :class="statusClass" x-text="status"></div>

        <template x-if="receiving">
            <div style="text-align:center; margin-top:2px;">
                <span class="rx-badge"><span class="rx-dot"></span> <span x-text="rxLabel"></span></span>
            </div>
        </template>
    </section>

    {{-- Push to talk --}}
    <section class="ptt-wrap" :class="{ 'recording': recording }">
        <button type="button"
                class="ptt"
                :class="{ 'recording': recording }"
                @pointerdown.prevent="startTx"
                @pointerup.prevent="stopTx"
                @pointerleave="stopTx"
                @pointercancel="stopTx"
                @contextmenu.prevent>
            <span class="ptt-inner">
                <span class="ptt-mic" x-show="!recording">🎙</span>
                <span class="ptt-mic" x-show="recording" x-cloak style="color:var(--magenta)">◉</span>
            </span>
        </button>
        <div class="ptt-halo">
            <span></span><span></span><span></span>
        </div>
    </section>

    <footer class="hint" style="margin-top:6px;">
        HOLD&nbsp;TO&nbsp;TRANSMIT&nbsp;&nbsp;·&nbsp;&nbsp;RELEASE&nbsp;TO&nbsp;SEND&nbsp;&nbsp;·&nbsp;&nbsp;LIVE&nbsp;ON&nbsp;CH&nbsp;{{ str_pad((string)$channel,2,'0',STR_PAD_LEFT) }}
    </footer>

    {{-- hidden playback element --}}
    <audio x-ref="player" playsinline></audio>
</div>

@script
<script>
    Alpine.data('talkie', () => ({
        recording: false,
        receiving: false,
        status: 'STANDING BY',
        statusClass: '',
        rxLabel: 'RECEIVING',
        locking: false,

        stream: null,
        recorder: null,
        chunks: [],
        startedAt: 0,

        audioCtx: null,
        analyser: null,
        freqData: null,
        rafId: null,
        rxTimer: null,

        init() {
            // channel tuned -> lock animation + satellite pulse
            this.$wire.on('channel-tuned', () => {
                this.locking = true;
                this.status = 'LOCKING SIGNAL…';
                this.statusClass = '';
                window.dispatchEvent(new Event('talkie:tuned'));
                setTimeout(() => {
                    this.locking = false;
                    if (!this.recording && !this.receiving) this.status = 'STANDING BY';
                }, 1100);
            });

            this.$wire.on('transmission-sent', () => {
                this.status = 'TRANSMITTED';
                this.statusClass = 'tx';
                window.dispatchEvent(new Event('talkie:tx'));
                setTimeout(() => {
                    if (!this.recording && !this.receiving) {
                        this.status = 'STANDING BY';
                        this.statusClass = '';
                    }
                }, 1400);
            });

            this.$wire.on('incoming-transmission', (payload) => {
                const data = Array.isArray(payload) ? payload[0] : payload;
                this.receive(data);
            });

            this.resetEq();
        },

        // ---------- transmit ----------
        async startTx() {
            if (this.recording || this.receiving) return;
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.status = 'MIC ACCESS DENIED';
                this.statusClass = 'err';
                return;
            }

            this.chunks = [];
            const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');
            this.recorder = mime ? new MediaRecorder(this.stream, { mimeType: mime }) : new MediaRecorder(this.stream);

            this.recorder.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
            this.recorder.onstop = () => this.finishTx();

            this.recorder.start();
            this.startedAt = performance.now();
            this.recording = true;
            this.status = 'TRANSMITTING…';
            this.statusClass = 'tx';
            window.dispatchEvent(new Event('talkie:tx'));

            this.startLiveEq();
        },

        stopTx() {
            if (!this.recording) return;
            this.recording = false;
            try { this.recorder && this.recorder.state !== 'inactive' && this.recorder.stop(); } catch (e) {}
            this.stopStream();
            this.stopEq();
        },

        finishTx() {
            const type = (this.recorder && this.recorder.mimeType) || 'audio/webm';
            const blob = new Blob(this.chunks, { type });
            const duration = (performance.now() - this.startedAt) / 1000;

            if (blob.size < 900 || duration < 0.35) {
                this.status = 'TOO SHORT — HOLD LONGER';
                this.statusClass = 'err';
                setTimeout(() => { this.status = 'STANDING BY'; this.statusClass = ''; }, 1500);
                return;
            }

            const ext = type.includes('ogg') ? 'ogg' : (type.includes('mp4') ? 'mp4' : 'webm');
            const file = new File([blob], `transmission.${ext}`, { type });

            this.status = 'UPLINK…';
            this.statusClass = 'tx';

            this.$wire.set('duration', Math.round(duration * 100) / 100, false);
            this.$wire.upload('audio', file,
                () => { this.$wire.sendTransmission(); },
                () => { this.status = 'UPLINK FAILED'; this.statusClass = 'err'; },
            );
        },

        // ---------- receive ----------
        receive(data) {
            if (!data || !data.url) return;
            if (this.recording) return; // never interrupt a live transmission

            const player = this.$refs.player;
            player.src = data.url;
            this.receiving = true;
            this.rxLabel = (data.callsign || 'STATION') + ' · CH ' + String(data.channel).padStart(2, '0');
            this.status = 'INCOMING TRANSMISSION';
            this.statusClass = 'rx';
            window.dispatchEvent(new Event('talkie:rx'));

            let finished = false;
            const done = () => {
                if (finished) return;
                finished = true;
                if (this.rxTimer) { clearTimeout(this.rxTimer); this.rxTimer = null; }
                this.receiving = false;
                this.stopEq();
                if (!this.recording && !this.locking) { this.status = 'STANDING BY'; this.statusClass = ''; }
                player.removeEventListener('ended', done);
                player.removeEventListener('error', done);
            };

            player.addEventListener('ended', done);
            player.addEventListener('error', done);

            // Fallback: webm blobs often report no/Infinity duration and autoplay
            // can be blocked (promise reject, no 'error' event) — so always arm a
            // timer off the stored duration to guarantee the animation stops.
            const secs = Number(data.duration) > 0 ? Number(data.duration) : 6;
            this.rxTimer = setTimeout(done, Math.ceil(secs * 1000) + 900);

            this.startRxEq();
            player.play().catch(() => done()); // autoplay blocked -> reset immediately
        },

        // ---------- equalizer ----------
        bars() { return this.$refs.eq ? this.$refs.eq.children : []; },

        resetEq() {
            const bars = this.bars();
            for (let i = 0; i < bars.length; i++) bars[i].style.height = '6px';
        },

        startLiveEq() {
            try {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const src = this.audioCtx.createMediaStreamSource(this.stream);
                this.analyser = this.audioCtx.createAnalyser();
                this.analyser.fftSize = 128;
                this.analyser.smoothingTimeConstant = 0.72;
                src.connect(this.analyser);
                this.freqData = new Uint8Array(this.analyser.frequencyBinCount);
            } catch (e) { this.analyser = null; }

            const bars = this.bars();
            const loop = () => {
                if (!this.recording) return;
                if (this.analyser) {
                    this.analyser.getByteFrequencyData(this.freqData);
                    const n = bars.length;
                    for (let i = 0; i < n; i++) {
                        const idx = Math.floor((i / n) * this.freqData.length);
                        const v = this.freqData[idx] / 255;
                        bars[i].style.height = (6 + v * 54) + 'px';
                    }
                }
                this.rafId = requestAnimationFrame(loop);
            };
            this.rafId = requestAnimationFrame(loop);
        },

        startRxEq() {
            const bars = this.bars();
            const n = bars.length;
            let phase = 0;
            const loop = () => {
                if (!this.receiving) return;
                phase += 0.35;
                for (let i = 0; i < n; i++) {
                    const wobble = Math.sin(phase + i * 0.5) * 0.5 + 0.5;
                    const env = Math.sin((i / n) * Math.PI); // taller in the middle
                    const v = wobble * env * (0.55 + Math.random() * 0.45);
                    bars[i].style.height = (6 + v * 52) + 'px';
                }
                this.rafId = requestAnimationFrame(loop);
            };
            this.rafId = requestAnimationFrame(loop);
        },

        stopEq() {
            if (this.rafId) cancelAnimationFrame(this.rafId);
            this.rafId = null;
            if (this.audioCtx) { try { this.audioCtx.close(); } catch (e) {} this.audioCtx = null; }
            this.analyser = null;
            this.resetEq();
        },

        stopStream() {
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
        },
    }));
</script>
@endscript
