WEBGL LIQUID GLASS — WHAT CHANGED
=====================================

This replaces the CSS-only glass card (blur + tint) with a real WebGL
shader that actually bends the pixels behind the card, plus chromatic
aberration (red/green/blue channels refracting by slightly different
amounts, like a real lens) and a soft specular highlight.

FILES
  glass-shader.js   — the whole shader implementation, drop into project root
  hero-glass.css    — rewritten for this: now only handles layout
                      (positioning the canvas + text), NOT the glass look
                      itself — the shader owns all of that now
  dental.php, surgery.php, vaccination.php, wellness.php, grooming.php,
  parasite.php      — each now has a <canvas> + a small init script

HOW IT WORKS, BRIEFLY
  1. A fragment shader generates a "bumpy noise" height-map (fbm/value
     noise, computed live on the GPU, not a static image).
  2. It reads the slope of that noise at each pixel and uses it to shift
     ("displace") where it samples the actual photo from — that's the
     real refraction, an actual different pixel gets pulled in, not a
     blur average.
  3. It does that displacement THREE separate times per pixel — once
     for red, once for green, once for blue, each with a slightly
     different strength — which recombines into the color-fringing
     you see at high-contrast edges in real lenses (chromatic
     aberration).
  4. A rounded-rectangle shape (computed mathematically, not an image)
     masks all of this to just the card's exact area, matching wherever
     your actual HTML text box is positioned — so it stays correct
     even if you resize the window.

WHY THE CARD ITSELF HAS NO CSS BACKGROUND ANYMORE
  Previously the "glass" was CSS: backdrop-filter blur + a translucent
  background. Now the canvas sitting just behind the text renders 100%
  of the visual glass effect (color, blur, refraction, highlight) —
  the text div is just an empty positioning box floating on top of it.
  This is why hero-glass.css got much shorter.

FALLBACK FOR OLDER BROWSERS
  If a browser doesn't support WebGL at all (very rare today, but
  possible on some very old devices), glass-shader.js detects this and
  falls back to the plain CSS blur-glass version automatically — nobody
  ever sees a broken, glass-less card.

HOW TO INSTALL
  Copy glass-shader.js, hero-glass.css, and the 6 updated .php files
  into your project, overwriting the old ones. No database changes,
  no other files affected.

HONEST PERFORMANCE NOTE
  This runs on the GPU per-frame it's drawn (once on load + once on
  resize, not continuously animating, so it's cheap — but it is doing
  real shader math, unlike a CSS blur which is heavily hardware-
  optimized in the browser itself). On any laptop/desktop from the
  last decade this is completely unnoticeable. It's not something to
  worry about at your scale.
