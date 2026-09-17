/**
 * Responsive + accessibility smoke test.
 * Loads real pages at phone, tablet and desktop widths and checks for
 * horizontal overflow, touch-target size and console errors.
 */
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
const DEVICES = [
  { name: 'iPhone SE',      width: 375,  height: 667,  mobile: true },
  { name: 'iPhone 14 Pro',  width: 393,  height: 852,  mobile: true },
  { name: 'Android (Pixel)',width: 412,  height: 915,  mobile: true },
  { name: 'iPad',           width: 768,  height: 1024, mobile: true },
  { name: 'Laptop',         width: 1366, height: 768,  mobile: false },
  { name: 'Desktop',        width: 1920, height: 1080, mobile: false },
];

let pass = 0, fail = 0;
const ok  = m => { console.log('  PASS  ' + m); pass++; };
const bad = m => { console.log('  FAIL  ' + m); fail++; };

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'],
  });

  // Sign in once so the panel pages can be tested too.
  const authCtx = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  const authPage = await authCtx.newPage();
  await authPage.goto(BASE + '/login');
  await authPage.fill('#email', process.env.TEST_EMAIL || 'akshay@example.com');
  await authPage.fill('#password', process.env.TEST_PASSWORD || 'Testpass123');
  await authPage.click('button[type=submit]');
  await authPage.waitForLoadState('networkidle');
  const storage = await authCtx.storageState();
  await authCtx.close();

  const PAGES = [
    { path: '/',            label: 'Landing page',   auth: false },
    { path: '/templates',   label: 'Design gallery', auth: false },
    { path: '/pricing',     label: 'Pricing',        auth: false },
    { path: '/login',       label: 'Sign in',        auth: false },
    { path: '/register',    label: 'Register',       auth: false },
    { path: '/card/ak-computer-cctv', label: 'Public card', auth: false },
    { path: '/dashboard',   label: 'Dashboard',      auth: true },
    { path: '/cards',       label: 'My cards',       auth: true },
    { path: '/billing',     label: 'Billing',        auth: true },
  ];

  for (const device of DEVICES) {
    console.log(`\n== ${device.name} (${device.width}x${device.height}) ==`);
    const context = await browser.newContext({
      viewport: { width: device.width, height: device.height },
      isMobile: device.mobile,
      hasTouch: device.mobile,
      deviceScaleFactor: device.mobile ? 2 : 1,
      storageState: storage,
    });

    for (const target of PAGES) {
      const page = await context.newPage();
      const consoleErrors = [];
      page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
      page.on('pageerror', e => consoleErrors.push(e.message));

      try {
        const response = await page.goto(BASE + target.path, { waitUntil: 'networkidle', timeout: 20000 });
        const status = response ? response.status() : 0;
        if (status !== 200) { bad(`${target.label} returned HTTP ${status}`); await page.close(); continue; }

        // An authenticated page that quietly bounced to the sign-in form
        // still returns 200, and every later assertion would then be
        // measuring the login screen instead of the panel.
        if (target.auth) {
          const landed = page.url();
          if (/\/login/.test(landed)) {
            bad(`${target.label}: redirected to the sign-in page -- session not applied`);
            await page.close();
            continue;
          }
          ok(`${target.label}: reached while signed in`);
        }

        const metrics = await page.evaluate(() => ({
          scrollWidth: document.documentElement.scrollWidth,
          clientWidth: document.documentElement.clientWidth,
          overflowing: Array.from(document.querySelectorAll('*'))
            .filter(el => el.getBoundingClientRect().right > document.documentElement.clientWidth + 2)
            .slice(0, 3)
            .map(el => el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ')[0] : '')),
        }));

        if (metrics.scrollWidth <= metrics.clientWidth + 2) {
          ok(`${target.label}: no horizontal overflow (${metrics.scrollWidth}px)`);
        } else {
          bad(`${target.label}: overflows ${metrics.scrollWidth}px > ${metrics.clientWidth}px via ${metrics.overflowing.join(', ')}`);
        }

        // Touch targets must be reachable on a phone.
        if (device.mobile) {
          const small = await page.evaluate(() => {
            const sel = 'a.btn, button.btn, .dvc-btn, .dvc-action, .mobile-nav a, .nav-item';
            return Array.from(document.querySelectorAll(sel))
              .filter(el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.height < 40; })
              .slice(0, 3)
              .map(el => (el.textContent || '').trim().slice(0, 24) + ' (' + Math.round(el.getBoundingClientRect().height) + 'px)');
          });
          if (small.length === 0) ok(`${target.label}: all primary tap targets ≥ 40px`);
          else bad(`${target.label}: small tap targets → ${small.join('; ')}`);
        }

        if (consoleErrors.length === 0) ok(`${target.label}: no JavaScript errors`);
        else bad(`${target.label}: console errors → ${consoleErrors.slice(0, 2).join(' | ')}`);
      } catch (e) {
        bad(`${target.label}: ${e.message.split('\n')[0]}`);
      }
      await page.close();
    }
    await context.close();
  }

  await browser.close();
  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail === 0 ? 0 : 1);
})();
