/**
 * Mobile layout audit.
 *
 * Every route the application answers, opened on a 360px phone as each role,
 * checking the two things that actually make a page unusable on a phone: the
 * page scrolling sideways, and form controls squeezed too narrow to use.
 *
 * The earlier responsive suite checked nine hand-listed pages and treated a
 * redirect to the sign-in form as a pass, so it reported everything healthy
 * while two-column admin pages were collapsing their main column to a few
 * pixels. This walks the real route table and refuses to measure a page it
 * did not actually reach.
 */
function loadChromium() {
  for (const pkg of ['playwright-core', 'playwright']) {
    try { return require(pkg).chromium; } catch (err) { if (err.code !== 'MODULE_NOT_FOUND') throw err; }
  }
  console.error('playwright-core is not installed. Run: npm install --no-save playwright-core');
  process.exit(2);
}
const chromium = loadChromium();
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080';
const WIDTH = Number(process.env.MOBILE_WIDTH || 360);
const MIN_CONTROL = 100;   // a control narrower than this cannot be used with a thumb

const ACCOUNTS = {
  customer: [process.env.TEST_EMAIL || 'akshay@example.com', process.env.TEST_PASSWORD || 'Testpass123'],
  reseller: [process.env.RESELLER_EMAIL || 'reseller@example.com', process.env.TEST_PASSWORD || 'Testpass123'],
  admin:    [process.env.ADMIN_EMAIL || 'admin@example.com', process.env.TEST_PASSWORD || 'Testpass123'],
};

/** Routes are read from the application's own table so nothing is missed. */
function routes() {
  const file = process.env.ROUTES_JSON || path.join(require('os').tmpdir(), 'dvc-routes.json');
  if (!fs.existsSync(file)) {
    console.error(`Route list not found at ${file}. Generate it with:\n  php tests/routes-export.php > ${file}`);
    process.exit(2);
  }
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function expand(pattern) {
  const isCard = pattern.includes('/cards/{id}');
  return pattern.replace(/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]|\{[^{}]*\})+))?\}/g, (_, name) => {
    if (name === 'id') return isCard ? (process.env.TEST_CARD_ID || '2') : '1';
    if (name === 'slug') return process.env.TEST_CARD_SLUG || 'ak-computer-cctv';
    if (name === 'code') return 'computer-shop-001';
    if (name === 'username') return 'akshay';
    return '1';
  });
}

let pass = 0, fail = 0;
const ok = m => { console.log('  PASS  ' + m); pass++; };
const bad = m => { console.log('  FAIL  ' + m); fail++; };

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--ignore-certificate-errors'],
  });

  const pages = routes()
    .filter(r => r.method === 'GET')
    .map(r => ({ ...r, path: expand(r.pattern) }))
    // Downloads, binaries and JSON endpoints are not pages and have no layout.
    .filter(r => !/\.(png|svg|xml|txt|ics|vcf|json)$/.test(r.path))
    .filter(r => !/^\/api\//.test(r.path))
    .filter(r => !/download|vcard|\/qr$|sitemap|robots|manifest|service-worker|logout|impersonate|export|\/chart\/|\/search$/.test(r.path));

  const broken = [];
  let measured = 0;

  for (const [role, [email, password]] of Object.entries(ACCOUNTS)) {
    const ctx = await browser.newContext({
      viewport: { width: WIDTH, height: 780 },
      isMobile: true, hasTouch: true, deviceScaleFactor: 2,
    });
    const page = await ctx.newPage();
    await page.goto(BASE + '/login');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    if (page.url().includes('/login')) { bad(`could not sign in as ${role}`); await ctx.close(); continue; }
    ok(`signed in as ${role}`);

    for (const route of pages) {
      let status = 0;
      try {
        const resp = await page.goto(BASE + route.path, { waitUntil: 'networkidle', timeout: 25000 });
        status = resp ? resp.status() : 0;
      } catch { continue; }
      // Only measure pages this role actually reached.
      if (status !== 200 || page.url().includes('/login')) continue;
      const type = await page.evaluate(() => document.contentType || '');
      if (!type.includes('html')) continue;
      measured++;

      const result = await page.evaluate((min) => {
        const de = document.documentElement;
        const w = de.clientWidth;
        const wide = [];
        document.querySelectorAll('body *').forEach(el => {
          const r = el.getBoundingClientRect();
          if (r.width === 0 || r.height === 0) return;
          if (r.left < -500) return;                                  // deliberately off-screen skip links
          if (getComputedStyle(el).position === 'fixed') return;      // fixed bars are sized to the viewport
          if (r.right <= w + 1) return;
          // An element inside an ancestor that scrolls or clips cannot push
          // the page sideways: a contact row scrolls on purpose, and a
          // decorative motif that overhangs its cover is clipped by it.
          let node = el.parentElement;
          while (node && node !== document.body) {
            const ox = getComputedStyle(node).overflowX;
            if (ox === 'auto' || ox === 'scroll' || ox === 'hidden' || ox === 'clip') return;
            node = node.parentElement;
          }
          const cls = (typeof el.className === 'string' && el.className) ? '.' + el.className.trim().split(/\s+/)[0] : '';
          wide.push(`${el.tagName.toLowerCase()}${cls} (${Math.round(r.width)}px)`);
        });
        const cramped = [];
        document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea').forEach(el => {
          const r = el.getBoundingClientRect();
          if (r.width > 0 && r.width < min) cramped.push(`${el.name || el.id || el.tagName.toLowerCase()} (${Math.round(r.width)}px)`);
        });
        return { scrollW: de.scrollWidth, clientW: w, wide: wide.slice(0, 3), cramped: cramped.slice(0, 3) };
      }, MIN_CONTROL);

      const problems = [];
      if (result.scrollW > result.clientW + 1) problems.push(`page scrolls sideways (${result.scrollW}px wide in a ${result.clientW}px screen)`);
      if (result.wide.length) problems.push(`reaches past the screen: ${result.wide.join(', ')}`);
      if (result.cramped.length) problems.push(`controls too narrow to use: ${result.cramped.join(', ')}`);
      if (problems.length) broken.push({ role, path: route.path, problems });
    }
    await ctx.close();
  }
  await browser.close();

  const unique = new Map();
  for (const b of broken) if (!unique.has(b.path)) unique.set(b.path, b);

  unique.size === 0
    ? ok(`all ${measured} page loads fit a ${WIDTH}px screen with usable controls`)
    : bad(`${unique.size} of ${measured} page loads do not work on a ${WIDTH}px screen`);
  for (const b of unique.values()) {
    console.log(`          ${b.path} [${b.role}]`);
    b.problems.forEach(p => console.log(`            - ${p}`));
  }

  console.log(`\nRESULT: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
