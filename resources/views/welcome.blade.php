<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>TALKIE — Living Signal</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">

    @fonts
    @vite(['resources/css/app.css'])
    @livewireStyles

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

    <style>
        :root {
            --ink-0:   #01020a;
            --ink-1:   #05081a;
            --lume:    #57f5ff;
            --lume-2:  #9b7bff;
            --lume-3:  #ff5cc8;
            --lume-4:  #5cffc0;
            --paper:   #eaf3ff;
            --dim:     rgba(180,205,255,.52);
            --dimmer:  rgba(150,178,230,.34);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            height: 100%;
            background: #01020a;
            color: var(--paper);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        /* the living-signal WebGL organism sits behind everything */
        #signal {
            position: fixed;
            inset: 0;
            z-index: 0;
            display: block;
        }

        /* film grain + chromatic edge for a premium, alive surface */
        .fx {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
        }
        .fx-grain {
            opacity: .05;
            mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            animation: grain 6s steps(6) infinite;
        }
        @keyframes grain {
            0%,100% { transform: translate(0,0); }
            20% { transform: translate(-4%,3%); }
            40% { transform: translate(3%,-5%); }
            60% { transform: translate(-3%,2%); }
            80% { transform: translate(4%,-2%); }
        }
        .fx-vign {
            box-shadow: inset 0 0 260px 30px rgba(0,0,0,.75);
        }
        /* readability scrim: darkens the middle where the console lives */
        .fx-scrim {
            background: radial-gradient(ellipse 44% 58% at 50% 55%,
                rgba(1,2,10,.74) 0%, rgba(1,2,10,.42) 38%, rgba(1,2,10,.12) 60%, transparent 76%);
        }

        /* ---------------------------------------------------------------- */
        /*  UI — "pure light" console, no skeuomorphism                     */
        /* ---------------------------------------------------------------- */
        .stage {
            position: relative;
            z-index: 2;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: clamp(20px, 4.4vh, 50px) 20px clamp(24px, 4vh, 44px);
        }

        /* masthead */
        .masthead { text-align: center; }
        .wordmark {
            font-weight: 600;
            font-size: clamp(16px, 2.6vw, 24px);
            letter-spacing: .62em;
            text-indent: .62em;
            background: linear-gradient(90deg, var(--lume) 0%, #ffffff 48%, var(--lume-2) 100%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            filter: drop-shadow(0 0 22px rgba(87,245,255,.4));
        }
        .tagline {
            margin-top: 11px;
            font-size: 9.5px;
            font-weight: 500;
            letter-spacing: .48em;
            text-indent: .48em;
            color: var(--dim);
            text-shadow: 0 2px 12px rgba(1,2,10,.9);
        }

        /* console column */
        .console {
            width: 100%;
            max-width: 460px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: clamp(14px, 2.6vh, 26px);
        }

        /* big frequency readout */
        .readout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .rd-num {
            font-size: clamp(58px, 17vw, 104px);
            font-weight: 600;
            line-height: .82;
            letter-spacing: -.03em;
            font-variant-numeric: tabular-nums;
            background: linear-gradient(180deg, #ffffff 0%, var(--lume) 90%);
            -webkit-background-clip: text; background-clip: text; color: transparent;
            filter: drop-shadow(0 0 30px rgba(87,245,255,.45));
            transition: filter .5s ease;
        }
        .rd-meta { text-align: left; }
        .rd-name {
            font-size: clamp(15px, 3.6vw, 20px);
            font-weight: 600;
            letter-spacing: .34em;
            text-transform: uppercase;
            color: var(--paper);
            filter: drop-shadow(0 0 14px rgba(155,123,255,.5));
        }
        .rd-freq {
            margin-top: 8px;
            font-size: 12px;
            letter-spacing: .26em;
            color: var(--dim);
            font-variant-numeric: tabular-nums;
            text-shadow: 0 2px 14px rgba(1,2,10,.95);
        }
        .tuning .rd-num { filter: drop-shadow(0 0 46px rgba(87,245,255,.9)); }

        /* channel spectrum selector */
        .spectrum {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .nodes {
            display: flex;
            align-items: center;
            gap: clamp(8px, 2.4vw, 15px);
            padding: 12px 18px;
            border-radius: 999px;
            background: linear-gradient(180deg, rgba(20,30,64,.28), rgba(6,10,26,.16));
            border: 1px solid rgba(120,160,255,.14);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .node {
            position: relative;
            width: 12px; height: 12px;
            padding: 0;
            border: none;
            background: none;
            cursor: pointer;
            display: grid; place-items: center;
        }
        .node span {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: rgba(150,180,255,.28);
            transition: all .35s cubic-bezier(.16,1,.3,1);
        }
        .node:hover span { background: rgba(160,200,255,.55); transform: scale(1.25); }
        .node.on span {
            width: 12px; height: 12px;
            background: var(--lume);
            box-shadow: 0 0 10px var(--lume), 0 0 22px var(--lume), 0 0 34px rgba(87,245,255,.6);
        }
        .step {
            width: 42px; height: 42px;
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: 20px; line-height: 1;
            color: var(--paper);
            background: linear-gradient(180deg, rgba(28,40,84,.5), rgba(8,14,32,.5));
            border: 1px solid rgba(120,160,255,.22);
            cursor: pointer;
            transition: transform .12s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .step:hover {
            border-color: rgba(120,190,255,.55);
            box-shadow: 0 0 22px -4px rgba(87,245,255,.7), inset 0 0 16px -6px rgba(87,245,255,.6);
        }
        .step:active { transform: scale(.88); }

        /* reactive spectrum line (equalizer bars, restyled to pure light) */
        .wave {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            height: 56px;
            width: 100%;
            max-width: 360px;
        }
        .wave b {
            width: 4px;
            height: 3px;
            border-radius: 3px;
            background: linear-gradient(180deg, var(--lume), var(--lume-2));
            box-shadow: 0 0 8px rgba(87,245,255,.5);
            opacity: .34;
            transition: height .07s linear, opacity .25s ease, background .35s ease;
            transform-origin: center;
        }
        .wave.live b { opacity: 1; }
        .wave.rx b {
            opacity: 1;
            background: linear-gradient(180deg, var(--lume-4), var(--lume));
            box-shadow: 0 0 9px rgba(92,255,192,.6);
        }

        /* status + rx pill */
        .status {
            min-height: 16px;
            font-size: 11px;
            letter-spacing: .32em;
            text-indent: .32em;
            text-transform: uppercase;
            color: var(--dim);
            text-align: center;
            transition: color .3s ease;
            text-shadow: 0 2px 14px rgba(1,2,10,.95);
        }
        .status.tx  { color: var(--lume-3); }
        .status.rx  { color: var(--lume-4); }
        .status.err { color: #ff6b6b; }

        .rxpill {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 6px 15px;
            border-radius: 999px;
            font-size: 10.5px;
            letter-spacing: .2em;
            color: var(--lume-4);
            background: rgba(92,255,192,.07);
            border: 1px solid rgba(92,255,192,.28);
        }
        .rxdot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--lume-4);
            box-shadow: 0 0 10px var(--lume-4);
            animation: blink 1s steps(2) infinite;
        }
        @keyframes blink { 50% { opacity: .2; } }

        /* PTT — the signal source: a disc of living light */
        .core-wrap {
            position: relative;
            display: grid;
            place-items: center;
            margin-top: 2px;
        }
        .core-halo {
            position: absolute;
            width: 190px; height: 190px;
            border-radius: 50%;
            pointer-events: none;
        }
        .core-halo span {
            position: absolute; inset: 0;
            border-radius: 50%;
            border: 1px solid rgba(255,92,200,.5);
            opacity: 0;
        }
        .core-wrap.recording .core-halo span { animation: emit 1.3s ease-out infinite; }
        .core-wrap.recording .core-halo span:nth-child(2) { animation-delay: .43s; }
        .core-wrap.recording .core-halo span:nth-child(3) { animation-delay: .86s; }
        @keyframes emit {
            0%   { transform: scale(.5);  opacity: .85; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        .core {
            position: relative;
            width: 150px; height: 150px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            color: #fff;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at 50% 36%, rgba(255,255,255,.32), rgba(87,245,255,.10) 42%, transparent 62%),
                conic-gradient(from 200deg, var(--lume-2), var(--lume), var(--lume-4), var(--lume-2));
            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,.18),
                inset 0 0 46px -10px rgba(0,0,0,.85),
                0 22px 54px -20px rgba(87,245,255,.85),
                0 0 66px -12px rgba(155,123,255,.75);
            transition: transform .14s cubic-bezier(.16,1,.3,1), box-shadow .25s ease, filter .25s ease;
            touch-action: none;
            animation: coreBreathe 5s ease-in-out infinite;
        }
        @keyframes coreBreathe {
            0%,100% { box-shadow: inset 0 0 0 1px rgba(255,255,255,.18), inset 0 0 46px -10px rgba(0,0,0,.85), 0 22px 54px -20px rgba(87,245,255,.7),  0 0 60px -14px rgba(155,123,255,.6); }
            50%     { box-shadow: inset 0 0 0 1px rgba(255,255,255,.22), inset 0 0 46px -10px rgba(0,0,0,.85), 0 22px 60px -20px rgba(87,245,255,.95), 0 0 84px -10px rgba(155,123,255,.85); }
        }
        .core::before {
            content: "";
            position: absolute;
            inset: 13px;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 42%, rgba(10,16,42,.42), rgba(3,5,16,.9));
            border: 1px solid rgba(255,255,255,.1);
        }
        .core:active { transform: scale(.93); }
        .core.recording {
            background:
                radial-gradient(circle at 50% 36%, rgba(255,255,255,.4), rgba(255,92,200,.14) 42%, transparent 62%),
                conic-gradient(from 200deg, var(--lume-3), #ff884d, #ffcf5c, var(--lume-3));
            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,.28),
                inset 0 0 46px -10px rgba(0,0,0,.7),
                0 0 110px -6px rgba(255,92,200,.95);
            filter: saturate(1.28);
            animation: none;
        }
        .core-glyph {
            position: relative;
            z-index: 1;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .28em;
            text-indent: .28em;
            filter: drop-shadow(0 0 12px rgba(87,245,255,.8));
        }
        .core.recording .core-glyph { filter: drop-shadow(0 0 14px rgba(255,92,200,.9)); }

        .hint {
            font-size: 10px;
            letter-spacing: .2em;
            color: var(--dim);
            text-align: center;
            text-shadow: 0 2px 12px rgba(1,2,10,.9);
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
    <canvas id="signal"></canvas>

    <div class="fx fx-scrim"></div>
    <div class="fx fx-grain"></div>
    <div class="fx fx-vign"></div>

    @livewire('walkie-talkie')

    @livewireScripts

    <script>
    // ==================================================================
    //  LIVING SIGNAL — a GPU flow-field organism that is the signal.
    //  ~45k light particles advected by curl-ish noise, shaped by a
    //  per-channel spherical field, deformed by the voice, bursting on
    //  transmit and flooding on receive — through a real bloom pipeline.
    // ==================================================================
    (function () {
        if (!window.THREE) return;
        const canvas = document.getElementById('signal');
        const renderer = new THREE.WebGLRenderer({ canvas, antialias: false, alpha: false, powerPreference: 'high-performance' });
        const DPR = Math.min(devicePixelRatio || 1, 2);
        renderer.setPixelRatio(DPR);
        renderer.setSize(innerWidth, innerHeight);
        renderer.autoClear = false;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(58, innerWidth / innerHeight, 0.1, 100);
        camera.position.set(0, 0, 10.6);

        const clock = new THREE.Clock();

        // ---- particle organism ------------------------------------------
        const COUNT = (innerWidth < 720 ? 22000 : 46000);
        const geo = new THREE.BufferGeometry();
        const seed = new Float32Array(COUNT * 3);
        const rnd  = new Float32Array(COUNT);
        for (let i = 0; i < COUNT; i++) {
            // uniform point on sphere
            const u = Math.random() * 2 - 1;
            const t = Math.random() * Math.PI * 2;
            const s = Math.sqrt(1 - u * u);
            seed[i*3]   = s * Math.cos(t);
            seed[i*3+1] = u;
            seed[i*3+2] = s * Math.sin(t);
            rnd[i] = Math.random();
        }
        geo.setAttribute('position', new THREE.BufferAttribute(seed, 3)); // reused as aSeed
        geo.setAttribute('aRand', new THREE.BufferAttribute(rnd, 1));

        const SNOISE = `
            vec4 permute(vec4 x){return mod(((x*34.0)+1.0)*x,289.0);}
            vec4 taylorInvSqrt(vec4 r){return 1.79284291400159 - 0.85373472095314 * r;}
            float snoise(vec3 v){
                const vec2 C = vec2(1.0/6.0, 1.0/3.0);
                const vec4 D = vec4(0.0,0.5,1.0,2.0);
                vec3 i  = floor(v + dot(v, C.yyy));
                vec3 x0 = v - i + dot(i, C.xxx);
                vec3 g = step(x0.yzx, x0.xyz);
                vec3 l = 1.0 - g;
                vec3 i1 = min(g.xyz, l.zxy);
                vec3 i2 = max(g.xyz, l.zxy);
                vec3 x1 = x0 - i1 + C.xxx;
                vec3 x2 = x0 - i2 + C.yyy;
                vec3 x3 = x0 - D.yyy;
                i = mod(i, 289.0);
                vec4 p = permute( permute( permute(
                            i.z + vec4(0.0, i1.z, i2.z, 1.0))
                          + i.y + vec4(0.0, i1.y, i2.y, 1.0))
                          + i.x + vec4(0.0, i1.x, i2.x, 1.0));
                float n_ = 1.0/7.0;
                vec3 ns = n_ * D.wyz - D.xzx;
                vec4 j = p - 49.0 * floor(p * ns.z * ns.z);
                vec4 x_ = floor(j * ns.z);
                vec4 y_ = floor(j - 7.0 * x_);
                vec4 x = x_ * ns.x + ns.yyyy;
                vec4 y = y_ * ns.x + ns.yyyy;
                vec4 h = 1.0 - abs(x) - abs(y);
                vec4 b0 = vec4(x.xy, y.xy);
                vec4 b1 = vec4(x.zw, y.zw);
                vec4 s0 = floor(b0)*2.0 + 1.0;
                vec4 s1 = floor(b1)*2.0 + 1.0;
                vec4 sh = -step(h, vec4(0.0));
                vec4 a0 = b0.xzyw + s0.xzyw*sh.xxyy;
                vec4 a1 = b1.xzyw + s1.xzyw*sh.zzww;
                vec3 p0 = vec3(a0.xy,h.x);
                vec3 p1 = vec3(a0.zw,h.y);
                vec3 p2 = vec3(a1.xy,h.z);
                vec3 p3 = vec3(a1.zw,h.w);
                vec4 norm = taylorInvSqrt(vec4(dot(p0,p0), dot(p1,p1), dot(p2,p2), dot(p3,p3)));
                p0 *= norm.x; p1 *= norm.y; p2 *= norm.z; p3 *= norm.w;
                vec4 m = max(0.6 - vec4(dot(x0,x0), dot(x1,x1), dot(x2,x2), dot(x3,x3)), 0.0);
                m = m * m;
                return 42.0 * dot(m*m, vec4(dot(p0,x0), dot(p1,x1), dot(p2,x2), dot(p3,x3)));
            }
            vec3 palette(float t){
                return 0.5 + 0.5*cos(6.28318*(vec3(0.0,0.33,0.67) + t));
            }
        `;

        const particleMat = new THREE.ShaderMaterial({
            uniforms: {
                uTime:   { value: 0 },
                uMorph:  { value: 1 },
                uAudio:  { value: 0 },
                uBurst:  { value: 0 },
                uRx:     { value: 0 },
                uSize:   { value: 2.3 },
                uPixelRatio: { value: DPR },
            },
            transparent: true,
            depthWrite: false,
            depthTest: false,
            blending: THREE.AdditiveBlending,
            vertexShader: SNOISE + `
                attribute float aRand;
                uniform float uTime, uMorph, uAudio, uBurst, uRx, uSize, uPixelRatio;
                varying vec3 vColor;
                varying float vGlow;
                void main(){
                    vec3 dir = normalize(position + 0.0001);
                    float theta = atan(dir.z, dir.x);
                    float phi   = acos(clamp(dir.y, -1.0, 1.0));
                    float ch = uMorph;

                    // per-channel organic shape field
                    float a = 3.0 + ch * 0.75;
                    float b = 2.0 + ch * 0.55;
                    float disp = 0.34 * sin(a*theta + uTime*0.25) * sin(b*phi);
                    disp += 0.18 * sin((a+2.0)*phi - uTime*0.18 + ch);
                    disp += 0.10 * sin(5.0*theta + uTime*0.4);
                    float R = 1.0 + disp;

                    float baseRadius = 3.15 * (1.0 + uAudio*0.34);
                    vec3 pos = dir * R * baseRadius;

                    // living flow
                    vec3 np = pos*0.34 + vec3(0.0, 0.0, uTime*0.06);
                    vec3 flow = vec3(snoise(np+11.0), snoise(np+47.0), snoise(np+93.0));
                    pos += flow * (0.5 + uAudio*1.2);

                    // transmit burst outward, receive contraction pulse
                    pos += dir * uBurst * (2.4 + aRand*4.2);
                    pos -= dir * uRx * (0.55 + 0.45*sin(aRand*30.0 + uTime*4.0));

                    vec4 mv = modelViewMatrix * vec4(pos, 1.0);
                    gl_Position = projectionMatrix * mv;

                    float energy = uAudio*0.6 + uBurst*0.85 + uRx*0.7;
                    float hue = ch*0.093 + length(flow)*0.05 + aRand*0.04;
                    vec3 col = palette(hue);
                    col = mix(col, vec3(1.0), clamp(energy, 0.0, 0.92));
                    vColor = col;
                    vGlow = 0.45 + energy;

                    float size = uSize * uPixelRatio * (0.5 + aRand*1.25) * (1.0 + energy*1.7);
                    gl_PointSize = size * (30.0 / -mv.z);
                }
            `,
            fragmentShader: `
                precision highp float;
                varying vec3 vColor;
                varying float vGlow;
                void main(){
                    vec2 uv = gl_PointCoord - 0.5;
                    float d = length(uv);
                    float a = smoothstep(0.5, 0.0, d);
                    a = pow(a, 1.55);
                    vec3 c = vColor * (0.55 + vGlow);
                    c += vec3(1.0) * pow(a, 4.0) * 0.55;
                    gl_FragColor = vec4(c * a, a);
                }
            `,
        });

        const organism = new THREE.Points(geo, particleMat);
        const orbit = new THREE.Group();
        orbit.add(organism);
        scene.add(orbit);

        // ---- bloom + composite pipeline ---------------------------------
        const rtOpts = { minFilter: THREE.LinearFilter, magFilter: THREE.LinearFilter, format: THREE.RGBAFormat, type: THREE.UnsignedByteType, depthBuffer: false, stencilBuffer: false };
        let W = Math.floor(innerWidth * DPR), H = Math.floor(innerHeight * DPR);
        let HW = Math.max(1, Math.floor(W/2)), HH = Math.max(1, Math.floor(H/2));
        let rtScene = new THREE.WebGLRenderTarget(W, H, rtOpts);
        let rtA = new THREE.WebGLRenderTarget(HW, HH, rtOpts);
        let rtB = new THREE.WebGLRenderTarget(HW, HH, rtOpts);

        const quadScene = new THREE.Scene();
        const quadCam = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
        function quadPass(material){
            const m = new THREE.Mesh(new THREE.PlaneBufferGeometry(2, 2), material);
            const s = new THREE.Scene(); s.add(m);
            return { scene: s, mesh: m, material };
        }

        const brightMat = new THREE.ShaderMaterial({
            uniforms: { tDiffuse: { value: null } },
            vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = vec4(position.xy, 0.0, 1.0); }`,
            fragmentShader: `
                precision highp float; varying vec2 vUv; uniform sampler2D tDiffuse;
                void main(){
                    vec3 c = texture2D(tDiffuse, vUv).rgb;
                    float l = dot(c, vec3(0.299,0.587,0.114));
                    c *= smoothstep(0.42, 0.85, l);
                    gl_FragColor = vec4(c, 1.0);
                }`,
        });
        const blurMat = new THREE.ShaderMaterial({
            uniforms: { tDiffuse: { value: null }, uDir: { value: new THREE.Vector2() } },
            vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = vec4(position.xy, 0.0, 1.0); }`,
            fragmentShader: `
                precision highp float; varying vec2 vUv;
                uniform sampler2D tDiffuse; uniform vec2 uDir;
                void main(){
                    vec3 s = vec3(0.0);
                    s += texture2D(tDiffuse, vUv).rgb * 0.227027;
                    s += texture2D(tDiffuse, vUv + uDir*1.3846).rgb * 0.316216;
                    s += texture2D(tDiffuse, vUv - uDir*1.3846).rgb * 0.316216;
                    s += texture2D(tDiffuse, vUv + uDir*3.2308).rgb * 0.070270;
                    s += texture2D(tDiffuse, vUv - uDir*3.2308).rgb * 0.070270;
                    gl_FragColor = vec4(s, 1.0);
                }`,
        });
        const compMat = new THREE.ShaderMaterial({
            uniforms: {
                tScene: { value: null }, tBloom: { value: null },
                uBloom: { value: 0.92 }, uAspect: { value: innerWidth/innerHeight },
                uMorph: { value: 1 }, uAudio: { value: 0 }, uTime: { value: 0 },
            },
            vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = vec4(position.xy, 0.0, 1.0); }`,
            fragmentShader: `
                precision highp float; varying vec2 vUv;
                uniform sampler2D tScene, tBloom;
                uniform float uBloom, uAspect, uMorph, uAudio, uTime;
                vec3 palette(float t){ return 0.5 + 0.5*cos(6.28318*(vec3(0.0,0.33,0.67) + t)); }
                void main(){
                    vec3 sc = texture2D(tScene, vUv).rgb;
                    vec3 bl = texture2D(tBloom, vUv).rgb;
                    vec2 p = vUv - 0.5; p.x *= uAspect;
                    float r = length(p);
                    vec3 bg = mix(vec3(0.020,0.028,0.055), vec3(0.004,0.005,0.017), smoothstep(0.0, 0.95, r));
                    vec3 tint = palette(uMorph*0.093);
                    bg += tint * (0.055 + uAudio*0.08) * exp(-r*3.4);
                    // faint drifting nebular haze
                    bg += tint * 0.02 * (0.5 + 0.5*sin(uTime*0.2 + vUv.x*6.0 + vUv.y*4.0)) * exp(-r*2.2);
                    vec3 col = bg + sc + bl * uBloom;
                    col = col / (col + vec3(1.0));      // reinhard tonemap
                    col = pow(col, vec3(0.90));
                    gl_FragColor = vec4(col, 1.0);
                }`,
        });

        const passBright = quadPass(brightMat);
        const passBlur   = quadPass(blurMat);
        const passComp   = quadPass(compMat);

        // ---- signal API (driven by the Livewire UI) ---------------------
        const state = {
            morphTarget: 1, morph: 1,
            audioTarget: 0, audio: 0,
            burst: 0, rx: 0,
        };
        window.__signal = {
            morph(ch){ if (ch) state.morphTarget = Math.max(1, Math.min(10, +ch)); },
            audio(level){ state.audioTarget = Math.max(0, Math.min(1, level)); },
            tx(){ state.audioTarget = Math.max(state.audioTarget, 0.25); },
            burst(){ state.burst = 1; state.audioTarget = 0; },
            rx(){ state.rx = 1; },
            idle(){ state.audioTarget = 0; },
        };
        // keep old event hooks working
        addEventListener('talkie:tx', () => window.__signal.tx());
        addEventListener('talkie:rx', () => window.__signal.rx());

        // ---- pointer parallax -------------------------------------------
        let px = 0, py = 0, tx = 0, ty = 0;
        addEventListener('pointermove', e => {
            tx = (e.clientX / innerWidth  - 0.5);
            ty = (e.clientY / innerHeight - 0.5);
        }, { passive: true });

        // ---- render loop ------------------------------------------------
        function frame(){
            requestAnimationFrame(frame);
            const t = clock.getElapsedTime();
            const dt = Math.min(clock.getDelta ? 0.016 : 0.016, 0.05);

            // eased state
            state.morph += (state.morphTarget - state.morph) * 0.06;
            state.audio += (state.audioTarget - state.audio) * 0.14;
            state.burst *= 0.90;
            state.rx    *= 0.92;

            particleMat.uniforms.uTime.value  = t;
            particleMat.uniforms.uMorph.value = state.morph;
            particleMat.uniforms.uAudio.value = state.audio;
            particleMat.uniforms.uBurst.value = state.burst;
            particleMat.uniforms.uRx.value    = state.rx;

            compMat.uniforms.uMorph.value = state.morph;
            compMat.uniforms.uAudio.value = state.audio;
            compMat.uniforms.uTime.value  = t;

            // motion
            px += (tx - px) * 0.05; py += (ty - py) * 0.05;
            orbit.rotation.y = t * 0.05 + px * 0.6;
            orbit.rotation.x = Math.sin(t * 0.13) * 0.12 - py * 0.4;
            camera.position.x += (px * 1.6 - camera.position.x) * 0.05;
            camera.position.y += (-py * 1.1 - camera.position.y) * 0.05;
            camera.lookAt(0, 0, 0);

            // 1. organism -> rtScene
            renderer.setRenderTarget(rtScene);
            renderer.clear();
            renderer.render(scene, camera);

            // 2. bright pass -> rtA (half res)
            brightMat.uniforms.tDiffuse.value = rtScene.texture;
            renderer.setRenderTarget(rtA);
            renderer.clear();
            renderer.render(passBright.scene, quadCam);

            // 3. separable blur (2 iterations for a soft, wide bloom)
            let src = rtA, dst = rtB;
            for (let i = 0; i < 2; i++) {
                blurMat.uniforms.tDiffuse.value = src.texture;
                blurMat.uniforms.uDir.value.set((1.6 + i) / HW, 0);
                renderer.setRenderTarget(dst); renderer.clear();
                renderer.render(passBlur.scene, quadCam);
                let tmp = src; src = dst; dst = tmp;

                blurMat.uniforms.tDiffuse.value = src.texture;
                blurMat.uniforms.uDir.value.set(0, (1.6 + i) / HH);
                renderer.setRenderTarget(dst); renderer.clear();
                renderer.render(passBlur.scene, quadCam);
                tmp = src; src = dst; dst = tmp;
            }

            // 4. composite -> screen
            compMat.uniforms.tScene.value = rtScene.texture;
            compMat.uniforms.tBloom.value = src.texture;
            renderer.setRenderTarget(null);
            renderer.clear();
            renderer.render(passComp.scene, quadCam);
        }
        frame();

        // ---- resize -----------------------------------------------------
        addEventListener('resize', () => {
            renderer.setSize(innerWidth, innerHeight);
            camera.aspect = innerWidth / innerHeight;
            camera.updateProjectionMatrix();
            compMat.uniforms.uAspect.value = innerWidth / innerHeight;

            W = Math.floor(innerWidth * DPR); H = Math.floor(innerHeight * DPR);
            HW = Math.max(1, Math.floor(W/2)); HH = Math.max(1, Math.floor(H/2));
            rtScene.setSize(W, H);
            rtA.setSize(HW, HH);
            rtB.setSize(HW, HH);
        });
    })();
    </script>
</body>
</html>
