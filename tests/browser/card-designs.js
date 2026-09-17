/**
 * Card designs must render their content.
 *
 * A design that renders an empty page is worse than an ugly one, and it is
 * easy to ship: the entrance animation starts sections at opacity 0, so any
 * design using it went blank wherever the reveal never ran -- in the gallery
 * previews, which load no script at all, and on a real card whose script
 * failed. This opens a spread of designs with JavaScript both enabled and
 * disabled and checks the content is actually visible, plus that button
 * labels can be read against the colours the palette produced.
 */
function loadChromium() {
  for (const pkg of ['playwright-core', 'playwright']) {
    try { return require(pkg).chromium; } catch (e) { if (e.code !== 'MODULE_NOT_FOUND') throw e; }
  }
  console.error('playwright-core is not installed. Run: npm install --no-save playwright-core');
  process.exit(2);
}
const chromium = loadChromium();
const fs = require('fs');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
const CODES = process.env.DESIGN_CODES
  ? process.env.DESIGN_CODES.split(',')
  : JSON.parse(fs.readFileSync(process.env.DESIGN_SAMPLE || require('path').join(require('os').tmpdir(), 'dvc-designs.json'), 'utf8'));

let pass = 0, fail = 0;
const ok = m => { console.log('  PASS  ' + m); pass++; };
const bad = m => { console.log('  FAIL  ' + m); fail++; };

function luminance([r, g, b]) {
  const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}
function contrast(a, b) {
  const l1 = luminance(a), l2 = luminance(b);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}
/** Parse a colour, compositing any alpha over the colour behind it, because
 *  a translucent button over white is not the colour its rgba() says. */
const rgb = (s, behind) => {
  const m = s && s.match(/rgba?\(([^)]+)\)/);
  if (!m) return null;
  const v = m[1].split(',').map(Number);
  const a = v.length > 3 ? v[3] : 1;
  if (a >= 1 || !behind) return [v[0], v[1], v[2]];
  return [0, 1, 2].map(i => Math.round(v[i] * a + behind[i] * (1 - a)));
};

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'],
  });

  for (const scripting of [true, false]) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, javaScriptEnabled: scripting });
    const page = await ctx.newPage();
    const blank = [];
    let checked = 0;

    for (const code of CODES) {
      let resp;
      try { resp = await page.goto(`${BASE}/templates/preview/${code}`, { waitUntil: 'networkidle', timeout: 20000 }); }
      catch { continue; }
      if (!resp || resp.status() !== 200) continue;
      await page.waitForTimeout(250);
      checked++;

      const r = await page.evaluate(() => {
        const sections = Array.from(document.querySelectorAll('.dvc-section'));
        const invisible = sections.filter(s => parseFloat(getComputedStyle(s).opacity) < 0.05).length;
        const name = document.querySelector('.dvc-name');
        const rect = name ? name.getBoundingClientRect() : null;
        return {
          sections: sections.length,
          invisible,
          hasName: !!name && (name.textContent || '').trim().length > 0,
          namePainted: !!rect && rect.width > 0 && rect.height > 0,
        };
      });

      if (r.invisible > 0 || !r.hasName || !r.namePainted) {
        blank.push(`${code} (${r.invisible}/${r.sections} sections invisible${r.hasName ? '' : ', no name'})`);
      }
    }

    blank.length === 0
      ? ok(`all ${checked} designs render their content with JavaScript ${scripting ? 'enabled' : 'disabled'}`)
      : bad(`${blank.length} of ${checked} designs render blank with JavaScript ${scripting ? 'enabled' : 'disabled'}: ${blank.slice(0, 5).join('; ')}`);
    await ctx.close();
  }

  // Text and button labels must be readable against whatever the palette
  // produced.
  //
  // Getting this measurement right took three attempts, and both wrong ones
  // were wrong in an instructive way. Walking the DOM for a background colour
  // cannot see gradients, cover images or alpha, and scored white-on-green as
  // white-on-white. Reading the text colour out of the rendered pixels
  // instead ran into anti-aliasing: small labels have few full-strength
  // pixels, so a 6.4:1 button measured 2.4:1.
  //
  // So: the text colour comes from the computed style, which is what WCAG is
  // defined against, and the background comes from the painted pixels, which
  // is the only way to see a gradient or a cover image.
  //
  // Finding the background took its own two attempts. The commonest colour
  // inside the text's box is wrong: over a gradient every background pixel
  // differs slightly while the glyph interiors are identical, so the
  // commonest colour is the text and everything scores 1.00:1. A band outside
  // the box is wrong too: outside a button is the page, not the button's own
  // fill, so a white label on a blue button scored against white.
  //
  // What works for both is to look inside the element and set aside the
  // pixels that are the text, then take the median of what is left. That is
  // the surface the text sits on whether it came from a fill, a gradient or a
  // photograph.
  const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, isMobile: true, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  const unreadable = [];
  let measured = 0;

  const luminance = ([r, g, b]) => {
    const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const contrast = (a, b) => {
    const l1 = luminance(a), l2 = luminance(b);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  };
  /**
   * A computed colour, with any alpha composited over what is behind it.
   *
   * Chrome resolves color-mix() to color(srgb r g b), not rgb(), and the card
   * stylesheet uses color-mix() in several places. Handling only rgb() meant
   * those elements parsed as null and were skipped in silence, which is how a
   * deliberately invisible heading came back as passing.
   */
  const parseColour = (value, behind) => {
    if (!value) return null;
    let channels = null;
    let alpha = 1;

    const rgbMatch = value.match(/rgba?\(([^)]+)\)/);
    const srgbMatch = value.match(/color\(\s*srgb\s+([^)]+)\)/);

    if (rgbMatch) {
      const parts = rgbMatch[1].split(/[,\s/]+/).filter(Boolean).map(Number);
      channels = parts.slice(0, 3);
      if (parts.length > 3) alpha = parts[3];
    } else if (srgbMatch) {
      // color(srgb) channels are 0-1, and an alpha may follow a slash.
      const parts = srgbMatch[1].split(/[\s/]+/).filter(Boolean).map(Number);
      channels = parts.slice(0, 3).map(v => Math.round(v * 255));
      if (parts.length > 3) alpha = parts[3];
    } else {
      return null;
    }

    if (channels.some(Number.isNaN)) return null;
    if (alpha >= 1 || !behind) return channels;

    return [0, 1, 2].map(i => Math.round(channels[i] * alpha + behind[i] * (1 - alpha)));
  };

  for (const code of CODES) {
    let resp;
    try { resp = await page.goto(`${BASE}/templates/preview/${code}`, { waitUntil: 'networkidle', timeout: 20000 }); }
    catch { continue; }
    if (!resp || resp.status() !== 200) continue;
    await page.waitForTimeout(250);

    const boxes = await page.evaluate(() => {
      const out = [];
      const add = (el, label) => {
        const r = el.getBoundingClientRect();
        if (r.width < 6 || r.height < 6) return;
        // Only what is fully on screen can be measured. A contact row scrolls
        // horizontally by design, so its later buttons sit outside the
        // viewport; cropping those catches blank edge and scores a perfectly
        // readable button as invisible.
        if (r.top < 0 || r.bottom > innerHeight) return;
        if (r.left < 0 || r.right > innerWidth) return;
        const cs = getComputedStyle(el);
        out.push({
          label,
          color: cs.color,
          // Gradient-clipped text paints its glyphs from a background image
          // and leaves `color` transparent, so the declared colour says
          // nothing. Those have to be read from the pixels instead.
          clipped: (cs.webkitBackgroundClip === 'text' || cs.backgroundClip === 'text'),
          x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height),
        });
      };
      ['.dvc-name', '.dvc-designation', '.dvc-business', '.dvc-tagline', '.dvc-category'].forEach(sel => {
        const el = document.querySelector(sel);
        if (el && (el.textContent || '').trim()) add(el, sel.replace('.dvc-', ''));
      });
      document.querySelectorAll('.dvc-action, .dvc-btn').forEach(el => {
        // The WhatsApp button is the vendor's own brand colour, excluded
        // deliberately rather than passed silently.
        if (el.classList.contains('whatsapp')) return;
        const label = (el.textContent || '').trim().slice(0, 14);
        if (label) add(el, 'button "' + label + '"');
      });
      return out.slice(0, 12);
    });

    for (const box of boxes) {
      let png;
      try { png = await page.screenshot({ clip: { x: box.x, y: box.y, width: box.w, height: box.h } }); }
      catch { continue; }
      // Decode without a PNG library: draw the crop back into a canvas.
      const pixels = await page.evaluate(async (dataUrl) => {
        const img = new Image();
        await new Promise((res, rej) => { img.onload = res; img.onerror = rej; img.src = dataUrl; });
        const c = document.createElement('canvas');
        c.width = img.width; c.height = img.height;
        c.getContext('2d').drawImage(img, 0, 0);
        const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
        const out = [];
        for (let i = 0; i < d.length; i += 4) out.push([d[i], d[i + 1], d[i + 2]]);
        return out;
      }, 'data:image/png;base64,' + png.toString('base64'));
      if (!pixels.length) continue;

      // Set aside anything close to the text colour -- the glyphs and their
      // strongest anti-aliased edges -- and take the median of the rest.
      if (box.clipped) {
        // Glyphs painted from a gradient: take the background as the
        // commonest colour and the text as the most distant band from it.
        // Headings are large enough that anti-aliasing does not dominate.
        const tally = new Map();
        for (const px of pixels) {
          const k = px.join(',');
          tally.set(k, (tally.get(k) || 0) + 1);
        }
        let bgKey = null, bgCount = -1;
        for (const [k, n] of tally) if (n > bgCount) { bgCount = n; bgKey = k; }
        const bgPx = bgKey.split(',').map(Number);
        const bgl = luminance(bgPx);
        const ranked = pixels.slice().sort((a, c) => Math.abs(luminance(c) - bgl) - Math.abs(luminance(a) - bgl));
        const strongest = ranked.slice(0, Math.max(1, Math.floor(ranked.length / 200)));
        const fgPx = strongest[Math.floor(strongest.length / 2)];
        measured++;
        const r = contrast(fgPx, bgPx);
        if (r < 3) unreadable.push(`${code} ${box.label} ${r.toFixed(2)}:1 (gradient text)`);
        continue;
      }

      const declared = parseColour(box.color, [255, 255, 255]);
      if (!declared) {
        measured++;
        unreadable.push(`${code} ${box.label} colour could not be read (${box.color}) so it went unchecked`);
        continue;
      }
      const near = px => Math.abs(px[0] - declared[0]) + Math.abs(px[1] - declared[1]) + Math.abs(px[2] - declared[2]) < 90;
      const surface = pixels.filter(px => !near(px));
      if (surface.length < Math.max(8, pixels.length * 0.15)) {
        // Almost every pixel in the box is the text colour, which means the
        // background is indistinguishable from the text. That is the failure
        // itself, not a reason to skip: an earlier version of this check
        // skipped here and so reported a deliberately invisible heading as
        // passing.
        measured++;
        unreadable.push(`${code} ${box.label} is indistinguishable from its background`);
        continue;
      }
      const byLuminance = surface.sort((a, c) => luminance(a) - luminance(c));
      const bg = byLuminance[Math.floor(byLuminance.length / 2)];

      const fg = parseColour(box.color, bg);
      if (!fg) continue;   // already reported above

      measured++;
      const ratio = contrast(fg, bg);
      if (ratio < 3) unreadable.push(`${code} ${box.label} ${ratio.toFixed(2)}:1`);
    }
  }
  await ctx.close();
  await browser.close();

  unreadable.length === 0
    ? ok(`all ${measured} text and button labels reach 3:1 in the rendered pixels`)
    : bad(`${unreadable.length} of ${measured} are unreadable: ${unreadable.slice(0, 6).join('; ')}`);

  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
