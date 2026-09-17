# Test suites

Every suite is plain PHP, shell or Node — there is no test framework to install,
matching the rest of the application. Each one prints `PASS` / `FAIL` lines and a
`RESULT: n passed, m failed` summary, and exits non-zero if anything failed.

```bash
tests/run.sh                 # everything
php tests/qr.php             # one suite
```

## Before running

These suites exercise a **running installation** and write rows to the
configured database. Point them at a development install, never production.

| Variable | Default | Used by |
| --- | --- | --- |
| `BASE_URL` | `http://127.0.0.1:8080` | HTTP and browser suites |
| `CHROMIUM_PATH` | Playwright's bundled browser | browser suites |
| `TEST_EMAIL` / `TEST_PASSWORD` | `akshay@example.com` / `Testpass123` | browser suites |

The browser suites need Playwright, which is not vendored:

```bash
npm install --no-save playwright-core
```

`tests/qr.php` decodes the QR codes it generates using `zbarimg`
(`apt install zbar-tools`). Without it the suite still checks encoding and
falls back to structural assertions, and says so in its output.

## What each suite covers

| Suite | Covers |
| --- | --- |
| `host-urls.sh` | URL generation across hostnames: the configured host, its www/non-www counterpart, explicitly trusted aliases, and that an unrecognised `Host` header is never reflected into generated URLs |
| `security-headers.sh` | CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, CSRF rejection, that protected files are not served, and installer lockout |
| `payments.php` | Order creation, signature verification, amount and order-id tampering, cross-account orders, failed payments, replay idempotency, invoice numbering and tax split. A stub replaces only the two methods that reach the network, so the real checkout, subscription and invoice code runs |
| `webhooks.php` | Webhook signature verification and idempotent settlement, including replays |
| `qr.php` | Encoding at every error-correction level, a sweep across symbol versions 1–40, and decoding each result back to the exact payload with an external scanner |
| `browser/responsive.js` | Nine pages at six viewports: horizontal overflow, 40px minimum tap targets, console errors, and that authenticated pages really render instead of bouncing to the sign-in form |
| `browser/framing.js` | That design-gallery and live-editor previews load inside their iframes under the site's framing policy |

`php bin/console.php health` is the twelve-point runtime check (PHP version and
extensions, database, schema, migrations, core files, configuration, writable
paths, settings, admin account, design library, disk space) and `run.sh` includes
it.
