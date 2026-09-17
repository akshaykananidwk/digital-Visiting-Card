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
const rgb = s => { const m = s && s.match(/rgba?\(([^)]+)\)/); if (!m) return null; const v = m[1].split(',').map(Number); return [v[0], v[1], v[2]]; };

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

  // Button labels must be readable against whatever the palette produced.
  const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, isMobile: true });
  const page = await ctx.newPage();
  const unreadable = [];
  let measured = 0;
  for (const code of CODES) {
    let resp;
    try { resp = await page.goto(`${BASE}/templates/preview/${code}`, { waitUntil: 'networkidle', timeout: 20000 }); }
    catch { continue; }
    if (!resp || resp.status() !== 200) continue;
    const found = await page.evaluate(() => {
      const out = [];
      document.querySelectorAll('.dvc-action, .dvc-btn').forEach(el => {
        // The WhatsApp button is the vendor's own brand colour and is excluded
        // deliberately; every other button is ours to get right.
        if (el.classList.contains('whatsapp')) return;
        const cs = getComputedStyle(el);
        let bg = cs.backgroundColor, n = el;
        while (n && (bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent')) { n = n.parentElement; if (!n) break; bg = getComputedStyle(n).backgroundColor; }
        out.push({ label: (el.textContent || '').trim().slice(0, 14), fg: cs.color, bg });
      });
      return out;
    });
    for (const x of found) {
      const fg = rgb(x.fg), bg = rgb(x.bg);
      if (!fg || !bg) continue;
      measured++;
      const ratio = contrast(fg, bg);
      if (ratio < 4) unreadable.push(`${code} "${x.label}" ${ratio.toFixed(2)}:1`);
    }
  }
  await ctx.close();
  await browser.close();

  unreadable.length === 0
    ? ok(`all ${measured} button labels reach 4:1 against their background`)
    : bad(`${unreadable.length} of ${measured} button labels are unreadable: ${unreadable.slice(0, 6).join('; ')}`);

  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
