<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>TALKIE — Deep Space Comms</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">

    @fonts
    @vite(['resources/css/app.css'])
    @livewireStyles

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

    <style>
        :root {
            --void-0:   #02030a;
            --void-1:   #060a1c;
            --plasma:   #3ef0ff;
            --plasma-2: #7c5cff;
            --magenta:  #ff4ecd;
            --amber:    #ffb347;
            --signal:   #5cffb1;
        }

        html, body {
            margin: 0;
            height: 100%;
            background: radial-gradient(ellipse at 50% -10%, #0b1436 0%, var(--void-1) 42%, var(--void-0) 100%);
            color: #eaf2ff;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        /* three.js starfield / satellite canvas sits behind everything */
        #cosmos {
            position: fixed;
            inset: 0;
            z-index: 0;
        }

        /* faint scanning grid + vignette overlays */
        .fx-overlay {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }
        .fx-grid {
            background-image:
                linear-gradient(to right, rgba(62,240,255,.04) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(62,240,255,.04) 1px, transparent 1px);
            background-size: 46px 46px;
            mask-image: radial-gradient(ellipse at 50% 65%, #000 0%, transparent 78%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 65%, #000 0%, transparent 78%);
        }
        .fx-vignette {
            box-shadow: inset 0 0 320px 80px rgba(0,0,0,.85);
        }
        .fx-scan {
            background: linear-gradient(to bottom, transparent 0%, rgba(62,240,255,.06) 50%, transparent 100%);
            height: 42%;
            animation: scanSweep 7s linear infinite;
            opacity: .5;
        }
        @keyframes scanSweep {
            0%   { transform: translateY(-60%); }
            100% { transform: translateY(240%); }
        }

        .app-shell {
            position: relative;
            z-index: 2;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: clamp(18px, 4vh, 46px) 18px;
        }

        .brand {
            text-align: center;
            letter-spacing: .55em;
            font-weight: 600;
            font-size: clamp(15px, 2.4vw, 22px);
            text-indent: .55em;
            background: linear-gradient(90deg, var(--plasma), #fff 55%, var(--plasma-2));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            filter: drop-shadow(0 0 18px rgba(62,240,255,.35));
        }
        .brand small {
            display: block;
            margin-top: 10px;
            letter-spacing: .42em;
            text-indent: .42em;
            font-size: 10px;
            font-weight: 500;
            color: rgba(180,205,255,.5);
            -webkit-text-fill-color: rgba(180,205,255,.5);
        }

        /* soft frosted panels */
        .glass {
            background: linear-gradient(180deg, rgba(20,30,64,.42), rgba(8,12,30,.30));
            border: 1px solid rgba(120,160,255,.16);
            box-shadow:
                0 0 0 1px rgba(0,0,0,.3) inset,
                0 24px 60px -30px rgba(0,0,0,.9),
                0 0 42px -18px rgba(62,240,255,.4);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 26px;
        }

        /* ---- channel dial ---- */
        .dial-wrap { text-align: center; }
        .dial {
            position: relative;
            width: clamp(230px, 62vw, 320px);
            height: clamp(230px, 62vw, 320px);
            margin: 0 auto;
        }
        .dial-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1px solid rgba(120,160,255,.18);
            box-shadow: 0 0 60px -12px rgba(62,240,255,.5), inset 0 0 60px -20px rgba(124,92,255,.6);
        }
        .dial-ticks {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            transition: transform .7s cubic-bezier(.16,1,.3,1);
        }
        .tick {
            position: absolute;
            top: 50%; left: 50%;
            width: 2px; height: 50%;
            transform-origin: top center;
            margin-left: -1px;
        }
        .tick i {
            display: block;
            width: 2px; height: 12px;
            margin: 8px auto 0;
            background: rgba(150,185,255,.4);
            border-radius: 2px;
        }
        .tick.on i {
            height: 20px;
            background: var(--plasma);
            box-shadow: 0 0 12px var(--plasma), 0 0 22px var(--plasma);
        }
        .dial-core {
            position: absolute;
            inset: 20%;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 35%, rgba(30,44,92,.9), rgba(6,10,26,.96));
            border: 1px solid rgba(120,160,255,.22);
            box-shadow: inset 0 0 40px -6px rgba(62,240,255,.35), 0 0 30px -8px rgba(0,0,0,.9);
        }
        .dial-ch {
            font-size: clamp(44px, 12vw, 68px);
            font-weight: 600;
            line-height: 1;
            letter-spacing: -.02em;
            background: linear-gradient(180deg, #fff, var(--plasma));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            filter: drop-shadow(0 0 16px rgba(62,240,255,.5));
        }
        .dial-name {
            margin-top: 6px;
            font-size: 13px;
            letter-spacing: .4em;
            text-indent: .4em;
            text-transform: uppercase;
            color: var(--plasma);
        }
        .dial-freq {
            margin-top: 3px;
            font-size: 11px;
            letter-spacing: .22em;
            color: rgba(180,205,255,.55);
            font-variant-numeric: tabular-nums;
        }
        .dial-locking .dial-ring { animation: lockPulse 1.1s ease-out; }
        @keyframes lockPulse {
            0%   { box-shadow: 0 0 0 0 rgba(62,240,255,.55), inset 0 0 60px -20px rgba(124,92,255,.6); }
            100% { box-shadow: 0 0 0 40px rgba(62,240,255,0), inset 0 0 60px -20px rgba(124,92,255,.6); }
        }

        .nav-btn {
            width: 58px; height: 58px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #dfe9ff;
            background: linear-gradient(180deg, rgba(30,44,92,.7), rgba(10,16,38,.7));
            border: 1px solid rgba(120,160,255,.28);
            box-shadow: 0 8px 24px -12px rgba(0,0,0,.9), inset 0 0 18px -8px rgba(62,240,255,.7);
            cursor: pointer;
            transition: transform .12s ease, box-shadow .2s ease, background .2s ease;
        }
        .nav-btn:hover { box-shadow: 0 0 26px -6px rgba(62,240,255,.7), inset 0 0 18px -6px rgba(62,240,255,.8); }
        .nav-btn:active { transform: scale(.9); }

        /* ---- equalizer ---- */
        .eq {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 4px;
            height: 60px;
        }
        .eq b {
            width: 5px;
            height: 6px;
            border-radius: 4px;
            background: linear-gradient(180deg, var(--plasma), var(--plasma-2));
            box-shadow: 0 0 10px rgba(62,240,255,.55);
            transition: height .06s linear, opacity .2s ease;
            opacity: .35;
        }
        .eq.live b { opacity: 1; }
        .eq.rx b { background: linear-gradient(180deg, var(--signal), #2bd0ff); box-shadow: 0 0 10px rgba(92,255,177,.6); }

        /* ---- push to talk ---- */
        .ptt-wrap { position: relative; display: grid; place-items: center; }
        .ptt-halo {
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            pointer-events: none;
        }
        .ptt-halo span {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1px solid rgba(255,78,205,.45);
            opacity: 0;
        }
        .ptt-wrap.recording .ptt-halo span { animation: pttRing 1.2s ease-out infinite; }
        .ptt-wrap.recording .ptt-halo span:nth-child(2) { animation-delay: .4s; }
        .ptt-wrap.recording .ptt-halo span:nth-child(3) { animation-delay: .8s; }
        @keyframes pttRing {
            0%   { transform: scale(.55); opacity: .9; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        .ptt {
            position: relative;
            width: 138px; height: 138px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: .28em;
            text-indent: .28em;
            background:
                radial-gradient(circle at 50% 34%, rgba(255,255,255,.25), transparent 45%),
                conic-gradient(from 210deg, var(--plasma-2), var(--plasma), var(--signal), var(--plasma-2));
            box-shadow:
                0 0 0 1px rgba(255,255,255,.15) inset,
                0 20px 50px -18px rgba(62,240,255,.9),
                0 0 60px -10px rgba(124,92,255,.7);
            transition: transform .12s ease, box-shadow .2s ease, filter .2s ease;
            touch-action: none;
        }
        .ptt:active { transform: scale(.94); }
        .ptt.recording {
            background:
                radial-gradient(circle at 50% 34%, rgba(255,255,255,.3), transparent 45%),
                conic-gradient(from 210deg, var(--magenta), #ff7a3d, var(--amber), var(--magenta));
            box-shadow:
                0 0 0 1px rgba(255,255,255,.25) inset,
                0 0 90px -6px rgba(255,78,205,.9);
            filter: saturate(1.3);
        }
        .ptt-inner {
            position: absolute;
            inset: 10px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: radial-gradient(circle at 50% 40%, rgba(10,16,40,.5), rgba(4,6,18,.85));
            border: 1px solid rgba(255,255,255,.12);
        }
        .ptt-mic { font-size: 34px; filter: drop-shadow(0 0 10px rgba(62,240,255,.7)); }

        .status-line {
            min-height: 20px;
            text-align: center;
            font-size: 11px;
            letter-spacing: .3em;
            text-indent: .3em;
            text-transform: uppercase;
            color: rgba(180,205,255,.6);
            transition: color .3s ease;
        }
        .status-line.rx  { color: var(--signal); }
        .status-line.tx  { color: var(--magenta); }
        .status-line.err { color: #ff6b6b; }

        .rx-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 11px;
            letter-spacing: .2em;
            color: var(--signal);
            background: rgba(92,255,177,.08);
            border: 1px solid rgba(92,255,177,.3);
        }
        .rx-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--signal);
            box-shadow: 0 0 10px var(--signal);
            animation: blink 1s steps(2) infinite;
        }
        @keyframes blink { 50% { opacity: .2; } }

        .hint { font-size: 10.5px; letter-spacing: .18em; color: rgba(150,175,230,.4); text-align:center; }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
    <div id="cosmos"></div>

    <div class="fx-overlay fx-grid"></div>
    <div class="fx-overlay fx-scan"></div>
    <div class="fx-overlay fx-vignette"></div>

    @livewire('walkie-talkie')

    @livewireScripts

    <script>
        // ------------------------------------------------------------------
        //  Deep-space three.js scene: starfield + satellite + signal rings
        // ------------------------------------------------------------------
        (function () {
            if (!window.THREE) return;
            const mount = document.getElementById('cosmos');
            const scene = new THREE.Scene();
            scene.fog = new THREE.FogExp2(0x02030a, 0.055);

            const camera = new THREE.PerspectiveCamera(60, innerWidth / innerHeight, 0.1, 100);
            camera.position.set(0, 0, 15);

            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
            renderer.setSize(innerWidth, innerHeight);
            mount.appendChild(renderer.domElement);

            const clock = new THREE.Clock();

            // ---- starfield ----
            const starGeo = new THREE.BufferGeometry();
            const STAR_COUNT = 2600;
            const positions = new Float32Array(STAR_COUNT * 3);
            const colors = new Float32Array(STAR_COUNT * 3);
            const palette = [
                new THREE.Color(0x3ef0ff),
                new THREE.Color(0x7c5cff),
                new THREE.Color(0xffffff),
                new THREE.Color(0xff4ecd),
            ];
            for (let i = 0; i < STAR_COUNT; i++) {
                const r = 12 + Math.pow(Math.random(), 0.6) * 46;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                positions[i*3]   = r * Math.sin(phi) * Math.cos(theta);
                positions[i*3+1] = r * Math.sin(phi) * Math.sin(theta);
                positions[i*3+2] = r * Math.cos(phi);
                const c = palette[(Math.random() * palette.length) | 0];
                colors[i*3] = c.r; colors[i*3+1] = c.g; colors[i*3+2] = c.b;
            }
            starGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            starGeo.setAttribute('color', new THREE.BufferAttribute(colors, 3));
            const stars = new THREE.Points(starGeo, new THREE.PointsMaterial({
                size: 0.16, vertexColors: true, transparent: true, opacity: 0.9,
                depthWrite: false, blending: THREE.AdditiveBlending,
            }));
            scene.add(stars);

            // ---- the satellite / transmitter core ----
            const core = new THREE.Group();
            scene.add(core);

            const beacon = new THREE.Mesh(
                new THREE.SphereGeometry(0.6, 32, 32),
                new THREE.MeshBasicMaterial({ color: 0x3ef0ff })
            );
            core.add(beacon);

            const glow = new THREE.Mesh(
                new THREE.SphereGeometry(1.1, 32, 32),
                new THREE.MeshBasicMaterial({ color: 0x3ef0ff, transparent: true, opacity: 0.18, blending: THREE.AdditiveBlending })
            );
            core.add(glow);

            // orbiting dish rings
            const dishMat = new THREE.MeshBasicMaterial({ color: 0x7c5cff, transparent: true, opacity: 0.55, side: THREE.DoubleSide });
            const dishA = new THREE.Mesh(new THREE.TorusGeometry(1.7, 0.03, 12, 90), dishMat);
            const dishB = new THREE.Mesh(new THREE.TorusGeometry(2.3, 0.02, 12, 90), dishMat.clone());
            dishB.material.color = new THREE.Color(0x3ef0ff);
            core.add(dishA, dishB);

            // ---- expanding signal rings (pool) ----
            const rings = [];
            const RING_POOL = 5;
            for (let i = 0; i < RING_POOL; i++) {
                const m = new THREE.Mesh(
                    new THREE.RingGeometry(1, 1.03, 96),
                    new THREE.MeshBasicMaterial({ color: 0x5cffb1, transparent: true, opacity: 0, side: THREE.DoubleSide, blending: THREE.AdditiveBlending })
                );
                m.userData = { t: -99, delay: i * 0.55 };
                core.add(m);
                rings.push(m);
            }
            let pulsing = false;
            const pulseColor = new THREE.Color(0x5cffb1);

            function emitSignal(color) {
                pulsing = true;
                pulseColor.set(color || 0x5cffb1);
                rings.forEach(r => { r.userData.t = -r.userData.delay; });
            }
            window.__talkieEmit = emitSignal;

            // ---- interaction: subtle parallax ----
            let targetX = 0, targetY = 0;
            addEventListener('pointermove', e => {
                targetX = (e.clientX / innerWidth - 0.5);
                targetY = (e.clientY / innerHeight - 0.5);
            });

            function animate() {
                requestAnimationFrame(animate);
                const t = clock.getElapsedTime();

                stars.rotation.y = t * 0.012;
                stars.rotation.x = Math.sin(t * 0.05) * 0.05;

                core.rotation.y = t * 0.15;
                dishA.rotation.x = Math.PI / 2 + Math.sin(t * 0.6) * 0.25;
                dishA.rotation.y = t * 0.4;
                dishB.rotation.x = Math.PI / 2 + Math.cos(t * 0.5) * 0.3;
                dishB.rotation.z = t * 0.3;

                const beat = 1 + Math.sin(t * 3) * 0.06;
                beacon.scale.setScalar(beat);
                glow.scale.setScalar(beat * (1 + Math.sin(t * 1.7) * 0.08));

                // idle ambient rings
                // if (!pulsing && Math.sin(t * 0.9) > 0.995) emitSignal(0x3ef0ff);

                let anyAlive = false;
                rings.forEach(r => {
                    if (r.userData.t < -90) { r.material.opacity = 0; return; }
                    r.userData.t += 0.016;
                    const lt = r.userData.t;
                    if (lt < 0) { r.material.opacity = 0; anyAlive = true; return; }
                    if (lt > 2.2) { r.userData.t = -99; r.material.opacity = 0; return; }
                    anyAlive = true;
                    const life = lt / 2.2;
                    r.scale.setScalar(1 + life * 9);
                    r.material.color.copy(pulseColor);
                    r.material.opacity = (1 - life) * 0.5;
                    r.lookAt(camera.position);
                });
                if (!anyAlive) pulsing = false;

                camera.position.x += (targetX * 4 - camera.position.x) * 0.04;
                camera.position.y += (-targetY * 2.5 - camera.position.y) * 0.04;
                camera.lookAt(0, 0, 0);

                renderer.render(scene, camera);
            }
            animate();

            addEventListener('resize', () => {
                camera.aspect = innerWidth / innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(innerWidth, innerHeight);
            });

            // React to Livewire channel tuning + transmissions
            // window.addEventListener('talkie:tuned', () => emitSignal(0x3ef0ff));
            window.addEventListener('talkie:tx',   () => emitSignal(0xff4ecd));
            window.addEventListener('talkie:rx',   () => emitSignal(0x5cffb1));
        })();
    </script>
</body>
</html>
