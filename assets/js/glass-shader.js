// HealthWing — WebGL liquid glass shader
// Renders real refraction (UV displacement) + chromatic aberration + a
// specular highlight over a photo, confined to a rounded-rect region
// matching a text card positioned in normal HTML on top of the canvas.

function initGlassShader(canvasEl, imageUrl, cardEl) {
    // WebGL shader math (especially noise-based refraction) can behave very
    // differently across real mobile GPUs than in desktop browser testing —
    // this was only ever verified in a software-rendered test environment,
    // never on actual phone hardware. Rather than risk a broken, garbled
    // card on mobile, use the plain CSS glass there instead (the version
    // that was already confirmed to look good on phones before this
    // shader existed), and keep the real WebGL refraction for desktop.
    const MOBILE_BREAKPOINT = 768;
    function applyCssFallback() {
        cardEl.style.background = 'rgba(20, 34, 36, 0.55)';
        cardEl.style.backdropFilter = 'blur(20px) saturate(160%)';
        cardEl.style.webkitBackdropFilter = 'blur(20px) saturate(160%)';
        cardEl.style.border = '1px solid rgba(255,255,255,0.28)';
        cardEl.style.borderRadius = '24px';
        canvasEl.style.display = 'none';
    }

    if (window.innerWidth < MOBILE_BREAKPOINT) {
        applyCssFallback();
        return null;
    }

    const gl = canvasEl.getContext('webgl') || canvasEl.getContext('experimental-webgl');
    if (!gl) {
        console.warn('WebGL not supported — falling back to a plain CSS blur for the glass card.');
        applyCssFallback();
        return null;
    }

    const vertexSrc = `
        attribute vec2 a_position;
        varying vec2 v_uv;
        void main() {
            v_uv = a_position * 0.5 + 0.5;
            gl_Position = vec4(a_position, 0.0, 1.0);
        }
    `;

    const fragmentSrc = `
        precision mediump float;
        varying vec2 v_uv;
        uniform sampler2D u_texture;
        uniform vec2 u_canvasSize;
        uniform vec2 u_texSize;
        uniform vec4 u_cardRect;   // x, y, w, h in canvas pixels, y from TOP
        uniform float u_radius;

        float hash(vec2 p) {
            p = fract(p * vec2(123.34, 456.21));
            p += dot(p, p + 45.32);
            return fract(p.x * p.y);
        }

        float valueNoise(vec2 p) {
            vec2 i = floor(p);
            vec2 f = fract(p);
            float a = hash(i);
            float b = hash(i + vec2(1.0, 0.0));
            float c = hash(i + vec2(0.0, 1.0));
            float d = hash(i + vec2(1.0, 1.0));
            vec2 u = f * f * (3.0 - 2.0 * f);
            return mix(a, b, u.x) + (c - a) * u.y * (1.0 - u.x) + (d - b) * u.x * u.y;
        }

        float fbm(vec2 p) {
            float total = 0.0;
            float amp = 0.55;
            for (int i = 0; i < 3; i++) {
                total += valueNoise(p) * amp;
                p *= 2.05;
                amp *= 0.5;
            }
            return total;
        }

        vec2 coverUV(vec2 canvasPx) {
            float canvasAspect = u_canvasSize.x / u_canvasSize.y;
            float texAspect = u_texSize.x / u_texSize.y;
            vec2 scale;
            if (canvasAspect >texAspect) {
                scale = vec2(1.0, texAspect / canvasAspect);
            } else {
                scale = vec2(canvasAspect / texAspect, 1.0);
            }
            vec2 uv = canvasPx / u_canvasSize;
            uv = (uv - 0.5) * scale + 0.5;
            return uv;
        }

        void main() {
            vec2 fragPx = vec2(v_uv.x * u_canvasSize.x, (1.0 - v_uv.y) * u_canvasSize.y);

            vec2 rectCenter = u_cardRect.xy + u_cardRect.zw * 0.5;
            vec2 halfSize = u_cardRect.zw * 0.5;
            vec2 p = fragPx - rectCenter;

            vec2 d = abs(p) - halfSize + u_radius;
            float dist = length(max(d, 0.0)) - u_radius;

            if (dist >2.0) discard;

            vec2 noiseCoord = fragPx * 0.018;
            float eps = 1.0;
            float h  = fbm(noiseCoord);
            float hx = fbm(noiseCoord + vec2(eps * 0.02, 0.0));
            float hy = fbm(noiseCoord + vec2(0.0, eps * 0.02));
            vec2 gradient = vec2(hx - h, hy - h);

            float refractStrength = 60.0;
            vec2 offset = gradient * refractStrength;

            vec2 uvR = coverUV(fragPx + offset * 1.0);
            vec2 uvG = coverUV(fragPx + offset * 1.18);
            vec2 uvB = coverUV(fragPx + offset * 1.36);

            float r = texture2D(u_texture, uvR).r;
            float g = texture2D(u_texture, uvG).g;
            float b = texture2D(u_texture, uvB).b;
            vec3 color = vec3(r, g, b);

            // Frosted "milk glass"tint + gentle darkening for text contrast
            color = mix(color, vec3(1.0), 0.09);
            color *= 0.8;

            // Soft specular highlight, upper-left, like light catching curved glass
            vec2 highlightCenter = u_cardRect.xy + vec2(u_cardRect.z * 0.16, u_cardRect.w * 0.12);
            float distToHighlight = length(fragPx - highlightCenter) / (u_cardRect.z * 0.5);
            float highlight = smoothstep(1.0, 0.0, distToHighlight) * 0.32;
            color += highlight;

            // Rim light near the edge
            float edgeFactor = smoothstep(-16.0, -1.0, dist);
            color += edgeFactor * 0.14;

            float alpha = 1.0 - smoothstep(-2.0, 1.5, dist);

            gl_FragColor = vec4(color, alpha);
        }
    `;

    function compile(type, src) {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, src);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.error(gl.getShaderInfoLog(shader));
        }
        return shader;
    }

    const program = gl.createProgram();
    gl.attachShader(program, compile(gl.VERTEX_SHADER, vertexSrc));
    gl.attachShader(program, compile(gl.FRAGMENT_SHADER, fragmentSrc));
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        console.error(gl.getProgramInfoLog(program));
        return;
    }
    gl.useProgram(program);

    const quad = new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]);
    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, quad, gl.STATIC_DRAW);
    const posLoc = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(posLoc);
    gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 0, 0);

    const u_texture = gl.getUniformLocation(program, 'u_texture');
    const u_canvasSize = gl.getUniformLocation(program, 'u_canvasSize');
    const u_texSize = gl.getUniformLocation(program, 'u_texSize');
    const u_cardRect = gl.getUniformLocation(program, 'u_cardRect');
    const u_radius = gl.getUniformLocation(program, 'u_radius');

    const texture = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, texture);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);

    let texSize = [1, 1];

    function render() {
        const rect = canvasEl.parentElement.getBoundingClientRect();
        const cardRectBox = cardEl.getBoundingClientRect();

        const dpr = window.devicePixelRatio || 1;
        const w = Math.round(rect.width * dpr);
        const h = Math.round(rect.height * dpr);
        if (canvasEl.width !== w || canvasEl.height !== h) {
            canvasEl.width = w;
            canvasEl.height = h;
        }
        gl.viewport(0, 0, canvasEl.width, canvasEl.height);

        gl.clearColor(0, 0, 0, 0);
        gl.clear(gl.COLOR_BUFFER_BIT);

        gl.activeTexture(gl.TEXTURE0);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.uniform1i(u_texture, 0);
        gl.uniform2f(u_canvasSize, canvasEl.width, canvasEl.height);
        gl.uniform2f(u_texSize, texSize[0], texSize[1]);

        const cardX = (cardRectBox.left - rect.left) * dpr;
        const cardY = (cardRectBox.top - rect.top) * dpr;
        const cardW = cardRectBox.width * dpr;
        const cardH = cardRectBox.height * dpr;
        gl.uniform4f(u_cardRect, cardX, cardY, cardW, cardH);
        gl.uniform1f(u_radius, 24 * dpr);

        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    }

    const img = new Image();
    img.onload = function () {
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
        texSize = [img.naturalWidth, img.naturalHeight];
        render();
    };
    img.src = imageUrl;

    // The bug: window resize alone misses cases where the TEXT BOX itself
    // changes size/position without the window changing — a web font
    // finishing loading after first paint, a longer title wrapping onto a
    // 3rd line, or a phone rotating orientation. Any of these leaves the
    // glass card's rounded-rect drawn at a stale position that no longer
    // matches where the text actually landed. ResizeObserver watches the
    // card element itself and re-renders on ANY of those causes, not just
    // window resize.
    if (window.ResizeObserver) {
        const observer = new ResizeObserver(() => render());
        observer.observe(cardEl);
        observer.observe(canvasEl.parentElement);
    } else {
        window.addEventListener('resize', render);
    }

    // Also catch late web-font loading specifically, since that alone can
    // reflow text without necessarily firing a ResizeObserver callback in
    // every browser.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(render);
    }
    window.addEventListener('orientationchange', () => setTimeout(render, 200));
    window.addEventListener('resize', render);

    return { render };
}
