@php($meta = $channelMeta[$channel] ?? ['name' => '—', 'freq' => '00.0'])

<div wire:poll.2000ms="checkForMessages" class="stage" x-data="talkie()" x-cloak>

    {{-- Masthead --}}
    <header class="masthead">
        <div class="wordmark">TAWKEY</div>
        <div class="tagline">LIVING&nbsp;SIGNAL&nbsp;NETWORK</div>
    </header>

    <main class="console">

        {{-- Frequency readout --}}
        <div class="readout" :class="{ 'tuning': locking }" wire:key="rd-{{ $channel }}">
            <div class="rd-num">{{ str_pad((string) $channel, 2, '0', STR_PAD_LEFT) }}</div>
            <div class="rd-meta">
                <div class="rd-name">{{ $meta['name'] }}</div>
                <div class="rd-freq">{{ $meta['freq'] }} MHz</div>
            </div>
        </div>

        {{-- Channel spectrum selector --}}
        <div class="spectrum">
            <button type="button" class="step" wire:click="prevChannel" @pointerdown="playFx('tune')" aria-label="Previous channel">&lsaquo;</button>
            <div class="nodes">
                @for ($i = 1; $i <= \App\Livewire\WalkieTalkie::CHANNELS; $i++)
                    <button type="button"
                            class="node {{ $i === $channel ? 'on' : '' }}"
                            wire:click="setChannel({{ $i }})"
                            @pointerdown="if ({{ $i }} !== $wire.get('channel')) playFx('tune')"
                            aria-label="Channel {{ $i }}"><span></span></button>
                @endfor
            </div>
            <button type="button" class="step" wire:click="nextChannel" @pointerdown="playFx('tune')" aria-label="Next channel">&rsaquo;</button>
        </div>

        {{-- Reactive spectrum line --}}
        <div class="wave" x-ref="eq" :class="{ 'live': recording, 'rx': receiving }">
            @for ($i = 0; $i < 32; $i++)<b></b>@endfor
        </div>

        {{-- Status + incoming pill --}}
        <div style="text-align:center;">
            <div class="status" :class="statusClass" x-text="status"></div>
            <template x-if="receiving">
                <div><span class="rxpill"><span class="rxdot"></span> <span x-text="rxLabel"></span></span></div>
            </template>
        </div>

        {{-- Push to talk — the signal source --}}
        <div class="core-wrap" :class="{ 'recording': recording }">
            <button type="button"
                    class="core"
                    :class="{ 'recording': recording }"
                    @pointerdown.prevent="startTx"
                    @pointerup.prevent="stopTx"
                    @pointerleave="stopTx"
                    @pointercancel="stopTx"
                    @contextmenu.prevent>
                <span class="core-glyph" x-show="!recording">HOLD</span>
                <span class="core-glyph" x-show="recording" x-cloak>ON&nbsp;AIR</span>
            </button>
            <div class="core-halo"><span></span><span></span><span></span></div>
        </div>

        <div class="hint">HOLD&nbsp;TO&nbsp;TRANSMIT&nbsp;&nbsp;·&nbsp;&nbsp;RELEASE&nbsp;TO&nbsp;SEND</div>

        <label class="music-toggle" style="--color-accent:#57f5ff; --color-accent-foreground:#01020a;">
            <flux:switch @change="toggleMusic($event.target.checked)" checked />
            <span>MUSIC</span>
        </label>
    </main>

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

        sfx: null,
        music: null,
        arming: false,
        musicOn: true,
        musicVol: 0.05,

        init() {
            // --- audio: looping background music + radio SFX ---
            this.sfx = {
                start: new Audio('/fx/_RADIO_start.mp3'),
                end:   new Audio('/fx/_RADIO_end.mp3'),
                tune:  new Audio('/fx/_RADIO_tune.mp3'),
            };
            Object.values(this.sfx).forEach(a => { a.preload = 'auto'; a.volume = 0.6; });

            this.music = new Audio('https://lumerel.nyc3.cdn.digitaloceanspaces.com/music/Synth_Wave_Focus.mp3');
            this.music.loop = true;
            this.music.volume = this.musicVol;
            // browsers block autoplay until a gesture — kick it off on the first one
            const startMusic = () => {
                if (this.musicOn) this.music.play().catch(() => {});
                window.removeEventListener('pointerdown', startMusic);
            };
            window.addEventListener('pointerdown', startMusic);

            // channel tuned -> morph the organism to the new channel
            this.$wire.on('channel-tuned', () => {
                this.locking = true;
                this.status = 'RETUNING SIGNAL…';
                this.statusClass = '';
                window.__signal && window.__signal.morph(this.$wire.get('channel'));
                setTimeout(() => {
                    this.locking = false;
                    if (!this.recording && !this.receiving) this.status = 'STANDING BY';
                }, 900);
            });

            this.$wire.on('transmission-sent', () => {
                this.status = 'TRANSMITTED';
                this.statusClass = 'tx';
                window.__signal && window.__signal.burst();
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

            // sync organism to the initial channel
            window.__signal && window.__signal.morph(this.$wire.get('channel'));
            this.resetEq();
        },

        playFx(name) {
            const a = this.sfx && this.sfx[name];
            if (!a) return;
            try { a.currentTime = 0; a.play().catch(() => {}); } catch (e) {}
        },

        // duck the background music way down while transmitting so it can't
        // bleed into the mic / trigger echo-cancellation gain-ducking.
        duckMusic(on) {
            if (!this.music || !this.musicOn) return;
            this.music.volume = on ? 0.00 : this.musicVol;
        },

        toggleMusic(on) {
            this.musicOn = !!on;
            if (!this.music) return;
            if (this.musicOn) {
                this.music.volume = this.musicVol;
                this.music.play().catch(() => {});
            } else {
                this.music.pause();
            }
        },

        // ---------- transmit ----------
        async startTx() {
            if (this.recording || this.receiving || this.arming) return;
            this.arming = true;
            this.recording = true; // immediate UI feedback (halo, glyph, organism)
            this.status = 'TRANSMITTING…';
            this.statusClass = 'tx';
            window.__signal && window.__signal.tx();
            window.dispatchEvent(new Event('talkie:tx'));

            this.playFx('start');
            this.duckMusic(true);

            try {
                // Disable the browser's audio processing — echo-cancellation +
                // noise-suppression are what quiet the mic when other audio plays.
                this.stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: false,
                        noiseSuppression: false,
                        autoGainControl: true,
                    },
                });
            } catch (e) {
                this.status = 'MIC ACCESS DENIED';
                this.statusClass = 'err';
                this.recording = false;
                this.arming = false;
                this.duckMusic(false);
                window.__signal && window.__signal.idle();
                return;
            }

            // Released before the mic was ready — bail cleanly.
            if (!this.recording) { this.cancelArming(); return; }

            // Wait out the ~1s start blip so it isn't captured in the recording.
            const blip = this.sfx && this.sfx.start;
            const wait = (blip && isFinite(blip.duration) && blip.duration) ? blip.duration * 1000 : 700;
            await new Promise(r => setTimeout(r, Math.min(wait, 1200)));

            // Released during the delay — bail.
            if (!this.recording) { this.cancelArming(); return; }

            this.chunks = [];
            const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');
            this.recorder = mime ? new MediaRecorder(this.stream, { mimeType: mime }) : new MediaRecorder(this.stream);

            this.recorder.ondataavailable = (e) => { if (e.data.size) this.chunks.push(e.data); };
            this.recorder.onstop = () => this.finishTx();

            this.recorder.start();
            this.startedAt = performance.now();
            this.arming = false;

            this.startLiveEq();
        },

        // rolled back an arm that never became a real recording
        cancelArming() {
            this.arming = false;
            this.stopStream();
            this.duckMusic(false);
            window.__signal && window.__signal.idle();
            if (!this.receiving) { this.status = 'STANDING BY'; this.statusClass = ''; }
        },

        stopTx() {
            if (!this.recording) return;
            this.playFx('end');
            this.recording = false;
            this.duckMusic(false);
            try { this.recorder && this.recorder.state !== 'inactive' && this.recorder.stop(); } catch (e) {}
            this.stopStream();
            this.stopEq();
            window.__signal && window.__signal.idle();
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
            window.__signal && window.__signal.rx();
            window.dispatchEvent(new Event('talkie:rx'));

            let finished = false;
            const done = () => {
                if (finished) return;
                finished = true;
                if (this.rxTimer) { clearTimeout(this.rxTimer); this.rxTimer = null; }
                this.receiving = false;
                this.stopEq();
                window.__signal && window.__signal.idle();
                if (!this.recording && !this.locking) { this.status = 'STANDING BY'; this.statusClass = ''; }
                player.removeEventListener('ended', done);
                player.removeEventListener('error', done);
            };

            player.addEventListener('ended', done);
            player.addEventListener('error', done);

            // Fallback: webm blobs often report no/Infinity duration and autoplay
            // can be blocked (promise reject, no 'error' event) — always arm a timer.
            const secs = Number(data.duration) > 0 ? Number(data.duration) : 6;
            this.rxTimer = setTimeout(done, Math.ceil(secs * 1000) + 900);

            this.startRxEq();
            player.play().catch(() => done()); // autoplay blocked -> reset immediately
        },

        // ---------- equalizer ----------
        bars() { return this.$refs.eq ? this.$refs.eq.children : []; },

        resetEq() {
            const bars = this.bars();
            for (let i = 0; i < bars.length; i++) bars[i].style.height = '3px';
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
                    let sum = 0;
                    for (let i = 0; i < n; i++) {
                        const idx = Math.floor((i / n) * this.freqData.length);
                        const v = this.freqData[idx] / 255;
                        bars[i].style.height = (3 + v * 50) + 'px';
                        sum += v;
                    }
                    // feed the living organism with the voice amplitude
                    const level = Math.min(1, (sum / n) * 2.4);
                    window.__signal && window.__signal.audio(level);
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
                let sum = 0;
                for (let i = 0; i < n; i++) {
                    const wobble = Math.sin(phase + i * 0.5) * 0.5 + 0.5;
                    const env = Math.sin((i / n) * Math.PI); // taller in the middle
                    const v = wobble * env * (0.55 + Math.random() * 0.45);
                    bars[i].style.height = (3 + v * 48) + 'px';
                    sum += v;
                }
                // pulse the organism to the (synthetic) incoming waveform
                window.__signal && window.__signal.audio(Math.min(1, (sum / n) * 1.8));
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
