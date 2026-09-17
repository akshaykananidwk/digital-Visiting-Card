# Deployment guide

This guide covers a production deployment on shared hosting (cPanel /
Hostinger style) and on a VPS.

---

## 1. Shared hosting (cPanel, Hostinger, Bluehost…)

### Prepare the hosting account

1. **PHP version** — cPanel → *Select PHP Version* → choose **8.1 or newer**.
2. **Extensions** — in the same screen enable: `pdo_mysql`, `mbstring`,
   `openssl`, `fileinfo`, `curl`, `gd`, `zip`, `exif`, `opcache`.
3. **Database** — cPanel → *MySQL Databases*:
   - create a database, e.g. `account_digitalcard`
   - create a user with a strong password
   - add the user to the database with **All Privileges**
   - write down the database name, user and password

### Upload

Upload the contents of the project to `public_html` (or the document root of
the add-on domain). The structure at the web root must look like:

```
public_html/
  index.php
  .htaccess
  app/
  assets/
  config/
  database/
  install/
  storage/
  uploads/
  bin/
```

> Upload the ZIP through cPanel's File Manager and extract it there — it is
> far faster and safer than FTP for thousands of small files.

### Permissions

```
storage/            755   (and everything inside)
uploads/            755   (and everything inside)
.env                600   (see the warning below)
everything else     644 files / 755 directories
```

In File Manager: select `storage` → Permissions → 755 → *Recurse into
subdirectories*. Repeat for `uploads`.

> **`.env` at 600 only works if PHP runs as the file's owner.** On cPanel and
> most shared hosting that is the case, and 600 is right. Where PHP runs as a
> separate user — `www-data` or `apache` on a VPS, for instance — 600 locks
> PHP out of its own configuration. Use 640 with the file's group set to the
> one PHP runs as, or give PHP ownership:
>
> ```bash
> sudo chown www-data:www-data .env && sudo chmod 600 .env
> ```
>
> If this is wrong, the site answers 500 and the server error log names the
> owner, the permissions and the user PHP runs as, along with the command to
> fix it. It never continues with empty configuration.

### Install

Open `https://your-domain.com/install` and follow the wizard.

### Lock down

After the wizard finishes:

1. Set `.env` to **600**, keeping the ownership note above in mind.
2. Confirm `https://your-domain.com/.env` returns **403** or **404**.
3. Confirm `https://your-domain.com/app/bootstrap.php` returns **403**.
4. Install SSL (cPanel → *SSL/TLS Status* → *Run AutoSSL*) and make sure
   `APP_URL` in `.env` starts with `https://`.

### Hostnames

`APP_URL` is the site's canonical address: it is what emails, QR codes, vCards
and sitemap entries point at, so set it to the address you want customers to
share.

Visitors reaching the site on the www or non-www counterpart of that address are
served normally — links and assets follow the host they are on, so their session
survives. Any *other* hostname the site answers on (a staging alias, an IP used
for testing) must be listed in `APP_TRUSTED_HOSTS`:

```ini
APP_URL=https://example.com
APP_TRUSTED_HOSTS=staging.example.com,203.0.113.10
```

Hostnames that are neither `APP_URL` nor listed there fall back to generating
`APP_URL` links. That is deliberate: it stops a forged `Host` header from being
reflected into generated URLs. Reseller white-label domains do not belong in
this list — they are verified against the database and switched automatically.

---

## 2. VPS (Ubuntu + Apache)

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php8.2 php8.2-{mysql,mbstring,curl,gd,zip,xml,intl,opcache}
sudo a2enmod rewrite headers expires deflate
sudo systemctl restart apache2

sudo mysql -e "CREATE DATABASE digital_card CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'dvc'@'localhost' IDENTIFIED BY 'a-strong-password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON digital_card.* TO 'dvc'@'localhost'; FLUSH PRIVILEGES;"

sudo mkdir -p /var/www/digital-card
# …upload or git clone the project here…
sudo chown -R www-data:www-data /var/www/digital-card
sudo chmod -R 755 /var/www/digital-card/storage /var/www/digital-card/uploads
```

Apache virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/digital-card

    <Directory /var/www/digital-card>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/digital-card-error.log
    CustomLog ${APACHE_LOG_DIR}/digital-card-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite digital-card && sudo systemctl reload apache2
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d example.com -d www.example.com
```

Then either open `/install` or run the CLI installer:

```bash
cd /var/www/digital-card
sudo -u www-data php bin/console.php install \
  --db-name=digital_card --db-user=dvc --db-pass='a-strong-password' \
  --url=https://example.com \
  --admin-email=you@example.com --admin-password='ChangeMe123'
```

### nginx

Use `nginx.conf.example` from the project root. It reproduces every
protection the `.htaccess` provides.

---

## 3. Post-installation checklist

| Task | Where |
|---|---|
| SSL certificate installed and `APP_URL` uses `https://` | hosting panel + `.env` |
| `APP_DEBUG=false` | `.env` |
| `.env` permissions 600 | file manager |
| Razorpay keys + webhook | Admin → Settings → Payments |
| SMTP configured and tested | Admin → Settings → Email |
| GST details (India) | Admin → Settings → Payments |
| Site name, logo, favicon, support details | Admin → Settings → General |
| Terms and privacy text | Admin → Settings → Legal pages |
| Plans and pricing reviewed | Admin → Plans |
| Design library generated | Admin → Templates |
| Cron jobs installed | hosting panel |
| GitHub repository + token for updates | Admin → Updates |
| First backup taken | Admin → Backups |
| Health check green | Admin → Health check |

---

## 4. Cron jobs

cPanel → *Cron Jobs* → add:

```
0 1 * * *  /usr/local/bin/php /home/USER/public_html/bin/console.php subscriptions:expire
0 2 * * 0  /usr/local/bin/php /home/USER/public_html/bin/console.php cleanup
```

Adjust the PHP binary path — cPanel usually shows it in *Select PHP Version*.

Without the first job, expired subscriptions stay marked active until an
administrator runs the task manually from Admin → Health check.

---

## 5. Razorpay

1. Razorpay Dashboard → *Settings* → *API Keys* → generate keys.
2. Admin → Settings → Payments → paste the Key ID and Key Secret, tick
   *Enable Razorpay payments*.
3. Razorpay Dashboard → *Settings* → *Webhooks* → *Add New Webhook*:
   - URL: `https://your-domain.com/webhooks/razorpay`
   - Secret: generate one and paste the same value into Admin → Settings
   - Events: `payment.captured`, `payment.failed`, `refund.processed`
4. Press *Test connection* in Admin → Settings → Payments.

Payments are always verified against Razorpay before a plan activates: the
signature, the amount and the order id must all match. A webhook alone never
activates anything — its content is re-checked against the gateway.

---

## 6. GitHub auto-update

1. Push this project to a GitHub repository.
2. Create a fine-grained personal access token with **Contents: read** for
   that repository only.
3. Admin → Updates → enter `owner/repository`, the branch and the token,
   then *Save & test*.
4. Use *Check for update* whenever you want, and *Update now* to install.

The token is encrypted with `APP_KEY` before it is stored and is never shown
again in the interface.

**Before your first real update**, take a manual backup from Admin → Backups
and confirm it reports *verified*.

---

## 7. White-label reseller domains

For a reseller domain such as `cards.partner.com`:

1. The reseller enters the domain in Reseller → Branding.
2. They add the DNS records the panel shows:
   - `CNAME cards → your-platform-domain.com`
   - `TXT _dvc-verify → dvc-verify-…`
3. They press *Verify domain*.
4. On your server, add the domain as an alias of the same document root and
   issue an SSL certificate for it.

The platform then serves that hostname with the reseller's branding.

---

## 8. Backups and disaster recovery

- A verified backup is taken automatically before every update.
- Admin → Backups can create one on demand (files, database or both) and can
  restore one.
- Backups live in `storage/backups` and are pruned to the retention limit
  set in Admin → Updates.
- Download a copy off-server regularly — a backup on the same disk does not
  protect you from losing the disk.

To restore by hand:

```bash
# Database
mysql -u USER -p DATABASE < storage/backups/backup-YYYYMMDD-HHMMSS-database.sql

# Files
unzip -o storage/backups/backup-YYYYMMDD-HHMMSS-files.zip -d /path/to/app
```

`.env`, `uploads/` and `storage/` are never included in the file archive
unless you tick *Include the uploads folder*, so restoring never destroys
customer media or your configuration.

---

## 9. Troubleshooting

| Symptom | Cause and fix |
|---|---|
| 500 on every page | Check `storage/logs/app-YYYY-MM-DD.log`. Usually a database credential or a missing PHP extension. |
| Blank page | PHP fatal before the error handler loads — check the host's own error log and the PHP version. |
| "The page you are looking for could not be found" on every URL | `mod_rewrite` is off or `AllowOverride` is not `All`. |
| **Apache's own "Internal Server Error" page** (grey serif text, ending "Apache Server at ... Port 443") | Apache failed before PHP ran, nearly always over `.htaccess`. Read the server error log: cPanel → *Errors*, or `/var/log/apache2/error.log`. "Invalid command 'php_flag'" means PHP is not running as an Apache module — the shipped `.htaccess` already guards those directives, so make sure you uploaded the current one. "Invalid command 'Deny'" means `mod_access_compat` is missing, also guarded in the current files. To confirm `.htaccess` is the cause at all, rename it briefly: if the error changes, it is. |
| A styled "Configuration error" page saying `.env` cannot be read | `.env` exists but PHP is not allowed to open it — see the ownership note under *Permissions*. The server error log names the owner, the permissions, the user PHP runs as, and the command that fixes it. |
| "Database connection failed" straight after install | Check the credentials in `.env`. If the user shows as empty in the log, PHP could not read `.env` at all; see the row above. |
| Styles missing | `APP_URL` does not match the address you are browsing, and that address is not covered by `APP_TRUSTED_HOSTS`. |
| Card opens but images do not | `uploads/` permissions, or `APP_URL` uses the wrong scheme. |
| Payments never activate a plan | Check Admin → Logs → recent webhooks, and press *Test connection* in Settings → Payments. |
| Update fails at "backup" | `storage/backups` is not writable, or the disk is full. |
| Stuck in maintenance mode | Delete `storage/maintenance.flag`, then look at Admin → Updates → History. |
| Emails not arriving | Settings → Email → *Send a test email*, then read Admin → Logs → notification delivery. |
