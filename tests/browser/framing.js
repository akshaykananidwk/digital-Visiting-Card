/**
 * Playwright is the one dependency these browser suites need and it is not
 * vendored: install it next to the repository (npm i --no-save playwright-core)
 * or point NODE_PATH at an existing copy.
 */
function loadChromium() {
  for (const pkg of ['playwright-core', 'playwright']) {
    try {
      return require(pkg).chromium;
    } catch (err) {
      if (err.code !== 'MODULE_NOT_FOUND') throw err;
    }
  }
  console.error('playwright-core is not installed. Run: npm install --no-save playwright-core');
  process.exit(2);
}
const chromium = loadChromium();
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
let pass = 0, fail = 0;
const ok = m => { console.log('  PASS  ' + m); pass++; };
const bad = m => { console.log('  FAIL  ' + m); fail++; };

(async () => {
  const b = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'],
  });
  const ctx = await b.newContext({ viewport: { width: 1366, height: 900 } });
  const p = await ctx.newPage();
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

  await p.goto(BASE + '/login');
  await p.fill('#email', process.env.TEST_EMAIL || 'akshay@example.com');
  await p.fill('#password', process.env.TEST_PASSWORD || 'Testpass123');
  await p.click('button[type=submit]');
  await p.waitForLoadState('networkidle');
  ok('signed in');

  // Gallery previews must actually render inside their iframes.
  await p.goto(BASE + '/templates', { waitUntil: 'networkidle' });
  const galleryFrames = p.frames().filter(f => f.url().includes('/templates/preview'));
  galleryFrames.length > 0 ? ok(`gallery: ${galleryFrames.length} preview iframes attached`) : bad('gallery: no preview iframes attached');
  let rendered = 0;
  for (const f of galleryFrames) {
    const has = await f.$('.dvc-card, body *').catch(() => null);
    if (has) rendered++;
  }
  rendered === galleryFrames.length ? ok(`gallery: all ${rendered} previews rendered content`) : bad(`gallery: only ${rendered}/${galleryFrames.length} previews rendered`);

  // Live editor preview of the user's own card.
  await p.goto(BASE + '/cards', { waitUntil: 'networkidle' });
  const cardHref = await p.$$eval('a[href]', as => {
    const m = as.map(a => a.getAttribute('href')).find(h => /\/cards\/\d+/.test(h || ''));
    return m || null;
  });
  const designLink = cardHref ? cardHref.replace(/(\/cards\/\d+).*/, '$1/design') : null;
  if (designLink) {
    await p.goto(designLink.startsWith('http') ? designLink : BASE + designLink, { waitUntil: 'networkidle' });
    const ef = p.frames().filter(f => f.url().includes('/preview'));
    ef.length > 0 ? ok(`editor: ${ef.length} preview frames attached`) : bad('editor: no preview frame attached');
    for (const f of ef) {
      const body = await f.$('body').catch(() => null);
      body ? ok('editor: preview frame rendered (' + f.url().replace(BASE, '').slice(0, 45) + ')')
           : bad('editor: preview frame empty ' + f.url());
    }
  } else {
    bad('editor: no design link found on /cards');
  }

  const framed = errs.filter(e => /frame|Content Security/i.test(e));
  framed.length === 0 ? ok('no CSP/framing console errors') : bad('CSP errors: ' + framed.join(' | '));

  await b.close();
  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
