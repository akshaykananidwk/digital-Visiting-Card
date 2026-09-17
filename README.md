# Digital Visiting Card — SaaS Platform

A production-ready, multi-tenant platform for selling and running digital
visiting cards. Every customer gets a mobile-first card at their own link
(`/card/ak-computer` or `/ak-computer`) with a QR code, WhatsApp button,
contact download and an enquiry form.

Built on plain **PHP 8.1+ / MySQL** with **no Composer dependency**, so it
deploys on ordinary shared hosting: upload the files, open `/install`, done.

---

## What is included

| Area | Highlights |
|---|---|
| **Public card** | Call, WhatsApp, save contact (vCard), directions, services, products, gallery, business hours with live open/closed state, UPI payment, QR, native share, enquiry form |
| **Design system** | 1,200 designs generated from design tokens across 50 industry categories — switching design never touches customer content |
| **Customer panel** | Dashboard, live editor with phone preview, design picker with per-card theme overrides, services, products, gallery, QR studio, analytics, lead inbox with CSV export |
| **Billing** | Razorpay checkout verified server-side, plans, subscriptions with grace periods, GST invoices, refunds |
| **Reseller** | Prepaid wallet, customer management, white-label branding, DNS-verified custom domains |
| **Admin** | Users, cards, templates, categories, plans, orders, payments, invoices, resellers, leads, settings, logs, audit trail, impersonation |
| **Operations** | One-click GitHub updater with automatic rollback, verified backups, health check, installer, CLI console |

---

## Requirements

**Required**

- PHP 8.1 or newer
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (nginx and IIS configs are included)
- PHP extensions: `pdo`, `pdo_mysql`, `json`, `mbstring`, `openssl`, `fileinfo`, `curl`

**Recommended**

- `gd` — image resizing, WebP conversion and PNG QR codes
- `zip` — the GitHub updater and file backups
- `exif` — automatic photo orientation
- `opcache` — performance

The installer checks all of this and tells you exactly what to fix.

---

## Installation

### Web installer (recommended)

1. Upload the files to your web root.
2. Create an empty MySQL database in your hosting control panel.
3. Open `https://your-domain.com/install`.
4. Follow the ten steps: requirements → database → tables → administrator →
   site → payments → designs → security → finish.

The wizard writes `.env`, creates every table, seeds roles, plans and
settings, generates the design library and then locks itself.

### Command line

```bash
php bin/console.php install \
  --db-name=digital_card --db-user=dbuser --db-pass=secret \
  --url=https://your-domain.com \
  --admin-email=you@example.com --admin-password='ChangeMe123' \
  --per-category=24
```

---

## After installing

1. **SSL** — install a certificate and make sure `APP_URL` starts with `https://`.
2. **File permissions** — `chmod 600 .env`, `chmod 755 storage uploads`.
3. **Razorpay** — Admin → Settings → Payments, then add the webhook
   `https://your-domain.com/webhooks/razorpay` for `payment.captured`,
   `payment.failed` and `refund.processed`.
4. **Email** — Admin → Settings → Email, and use "Send a test email".
5. **Cron** — see below.
6. **Updates** — Admin → Updates, enter your GitHub repository and token.

### Cron jobs

```cron
# Expire finished subscriptions and take expired cards offline (daily, 01:00)
0 1 * * * /usr/bin/php /path/to/app/bin/console.php subscriptions:expire

# Housekeeping: analytics retention and rate-limit rows (weekly)
0 2 * * 0 /usr/bin/php /path/to/app/bin/console.php cleanup
```

---

## Command line reference

```
php bin/console.php install              Install the application
php bin/console.php migrate              Run pending migrations
php bin/console.php migrate:status       Show migration state
php bin/console.php seed                 Seed roles, plans and settings
php bin/console.php templates:generate   Build the design library
php bin/console.php subscriptions:expire Expire finished subscriptions
php bin/console.php health               Run the health check
php bin/console.php backup               Create a full backup
php bin/console.php maintenance:on|off   Toggle maintenance mode
php bin/console.php cleanup              Purge old analytics and rate limits
php bin/console.php key:generate         Generate a new APP_KEY
```

---

## Project layout

```
index.php              Front controller — every request enters here
.htaccess              Apache rewrite + hardening rules
nginx.conf.example     nginx equivalent
web.config             IIS equivalent

app/
  bootstrap.php        Autoloader, environment, error handling
  version.php          APP_VERSION — the updater compares against this
  Core/                Framework: router, database, auth, view, QR, uploads…
  Models/              Repositories (one per table)
  Services/            Business logic: checkout, subscriptions, templates,
                       backups, updates, analytics…
  Controllers/         Site, Auth, Customer, Reseller, Admin, Api, Install
  Middleware/          CSRF, authentication, roles, throttling, maintenance
  Views/               Layouts, panels and the card renderer

config/
  app.php              Application configuration
  database.php         Database configuration
  routes.php           The complete route table

database/migrations/   Versioned schema (tracked in the `migrations` table)
assets/                CSS, JavaScript and icons
storage/               Logs, cache, backups, sessions   (not web-readable)
uploads/               Customer media                   (PHP execution blocked)
bin/console.php        CLI entry point
tests/                 Test suites (tests/run.sh runs them all)
docs/                  Deployment guide and test report
```

---

## How the design system works

A design is **data, not code**. Each row in `templates` holds a JSON token
set — palette, typography, layout name, corner radius, cover style, effects.
`TemplateRenderer` turns those tokens into CSS custom properties and body
classes; twelve shared layout partials and one set of section partials render
every design.

That is why:

- the library scales to 10,000+ designs without adding a file;
- switching design is a single column update that cannot lose content;
- a customer can override colours and fonts per card;
- the gallery preview is the real renderer fed with demo data, so what you
  preview is exactly what gets published.

`TemplateFactory` generates the catalogue deterministically, so the same
seed produces the same template codes on every installation.

---

## Security

- Every query uses prepared statements with bound parameters.
- Passwords hashed with `password_hash()` (bcrypt, cost 12); a password
  change invalidates every existing session.
- CSRF token on every state-changing request; constant-time comparison.
- Output escaped at the point of rendering (`e()`); a strict Content
  Security Policy is sent with every response.
- Role-based access control plus ownership checks on every card, lead,
  order and customer — a tenant can never read another tenant's data.
- Uploads: real MIME detection, extension whitelist, size limit, GD
  re-encoding (which destroys embedded payloads), random file names and
  PHP execution disabled inside `uploads/`.
- Rate limiting on login, registration, password reset, enquiries, uploads
  and the API; login throttling is per-account as well as per-IP.
- Secrets (payment keys, SMTP password, GitHub token) encrypted at rest
  with AES-256-GCM using `APP_KEY`.
- Payments verified server-side against the gateway — signature, amount and
  order id must all match before a plan activates. Webhooks are signature
  verified and idempotent.
- Audit trail for every sensitive action, including impersonation.

See `docs/TESTING.md` for the results of the security test suite.

---

## Updating

Admin → Updates → **Check for update** shows the latest commit, its message,
the changed files and the changelog. **Update now** then runs:

1. Maintenance mode on
2. Record the current version
3. Full backup (files + database)
4. Verify the backup — an unverifiable backup aborts the update
5. Download the package from GitHub
6. Validate the package (ZIP integrity, zip-slip guard, marker files)
7. Apply files, preserving every protected path
8. Run pending migrations
9. Clear caches and reload configuration
10. Health check
11. Maintenance mode off

If **any** step fails the platform restores the backup, re-runs the health
check and only then reopens the site. If the rollback itself cannot be
completed the site stays in maintenance mode with a clear message rather
than serving a broken installation.

Protected paths — never overwritten or deleted:

```
.env
config/local.php
uploads/
storage/
install/.installed
```

More can be added in Admin → Updates.

---

## Licence

Commercial. Copyright belongs to the platform owner.
