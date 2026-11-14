# Pislinfra Headless WordPress CMS — Local & Hostinger Shared Hosting Deployment Guide

## Architecture
```
React Frontend (pislinfra-react/)
        ↓  HTTPS / REST JSON (no secret keys in client bundle)
WordPress Headless REST API (/wp-json/pislinfra/v1/*)
        ↓
WordPress MySQL / MariaDB Database (table prefix: pisl_)
        ↓
WordPress Media Library (wp-content/uploads/)
```

- **Zero Neon DB / Render / External DB / Docker dependencies.**
- All CMS content (35 Blogs, 16 Ongoing Projects, 27 Completed Projects, 8 Case Studies, 45 Awards, 28 Clients, 10 Client Appreciations, 43 Coverage Markers, 27 Job Openings, 5 Solution Pages, 3 Policy Documents, 1 Press Release, 19 Single-Type Pages/Settings) and all 429 Media Library attachments (images + PDFs) live directly inside `wp-cms/`.

---

## 1. Local Development Startup (macOS / Native MariaDB + PHP)

1. **Start MariaDB (local datadir `wp-cms/.local-db/data`, port `3306`, socket `/tmp/pisl_mariadb.sock`):**
   ```bash
   /Applications/XAMPP/xamppfiles/sbin/mysqld --no-defaults \
     --basedir=/Applications/XAMPP/xamppfiles \
     --datadir="$(pwd)/wp-cms/.local-db/data" \
     --socket=/tmp/pisl_mariadb.sock --port=3306 --bind-address=127.0.0.1
   ```
2. **Start WordPress PHP Server (multi-worker prevents loopback cron stalls):**
   ```bash
   PHP_CLI_SERVER_WORKERS=6 php -c wp-cms/config/php-uploads.ini \
     -S 127.0.0.1:8080 -t wp-cms wp-cms/router.php
   ```
3. **Start React Frontend (`pislinfra-react/.env` -> `VITE_WP_URL=http://127.0.0.1:8080`):**
   ```bash
   cd pislinfra-react && npm run dev
   ```
4. **Access URLs:**
   - React Website: `http://localhost:5173`
   - WordPress Admin Dashboard: `http://127.0.0.1:8080/wp-admin/`
   - WordPress Health Check: `http://127.0.0.1:8080/wp-json/pislinfra/v1/health`

---

## 2. Hostinger Shared Hosting Production Deployment

### Step A: Provision MySQL Database in Hostinger hPanel
1. Log into Hostinger **hPanel → Databases → Management**.
2. Create a new MySQL database (e.g. `u123456_pislwp`), username, and strong password.
3. Open **phpMyAdmin** for that database and import `wp-cms/exports/pislinfra_wp.sql` (6.6 MB).

### Step B: Upload WordPress CMS (`wp-cms/`)
Deploy WordPress cleanly either to a subdomain (recommended: `https://cms.pislinfra.com` pointing to `public_html/cms/`) or subdirectory (`https://pislinfra.com/wp-cms` pointing to `public_html/wp-cms/`).
1. Upload the contents of `wp-cms/` **excluding** `.local-db/`, `.bin/`, `.env`, `migration/`, and `exports/` to the target directory via hPanel File Manager or SFTP.
2. Edit `wp-config.php` on the server:
   - Set Hostinger `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_HOST` (`localhost` or `127.0.0.1`).
   - Keep `$table_prefix = 'pisl_';`.
   - Generate fresh production WordPress salts (`https://api.wordpress.org/secret-key/1.1/salt/`).
   - Change `define('WP_ENVIRONMENT_TYPE', 'production');`.
   - Set `define('PISL_CORS_ORIGINS', 'https://pislinfra.com,https://www.pislinfra.com');`.
   - Keep `define('DISALLOW_FILE_EDIT', true);`.
3. Verify `.htaccess` is uploaded cleanly so `/wp-json/*` pretty permalinks route through `index.php` and security headers are applied.
4. **Replace Localhost Media/Site URLs in the Production Database:**
   Run in Hostinger SSH (`wp search-replace`) or SQL before going live:
   ```bash
   wp search-replace 'http://127.0.0.1:8080' 'https://cms.pislinfra.com' --all-tables
   wp option update home 'https://cms.pislinfra.com'
   wp option update siteurl 'https://cms.pislinfra.com'
   wp rewrite flush
   ```

### Step C: WordPress Security Hardening & Passwords
1. Rotate initial development passwords for WordPress users:
   - `pislinfra_admin` (Administrator)
   - `pislinfra_editor` (Editor)
   - `pislinfra_author` (Author)
2. Ensure HTTPS (Let's Encrypt SSL in Hostinger hPanel) is forced on `https://cms.pislinfra.com` and `https://pislinfra.com`.
3. Restrict file permissions: directories `755`, files `644`, `wp-config.php` `600`.
4. Never store WordPress passwords or Application Passwords in React source files or `VITE_*` variables.

### Step D: Build & Deploy React Frontend (`pislinfra-react/`)
1. Update `pislinfra-react/.env.production`:
   ```env
   VITE_WP_URL=https://cms.pislinfra.com
   VITE_WP_API_URL=https://cms.pislinfra.com/wp-json/pislinfra/v1
   ```
   *(If WordPress is hosted under `https://pislinfra.com/wp-cms`, set `VITE_WP_URL=https://pislinfra.com/wp-cms` and `VITE_WP_API_URL=https://pislinfra.com/wp-cms/wp-json/pislinfra/v1`.)*
2. Build production bundle cleanly with local + live WordPress sync:
   ```bash
   cd pislinfra-react
   npm run build
   ```
3. Upload the contents of `pislinfra-react/dist/` to `public_html/`.

---

## 3. Custom Headless Plugin Architecture (`pislinfra-headless-cms`)
- **Custom Post Types & Blogs:** `post` (Blogs), `ongoing_project`, `completed_project`, `case_study`, `award`, `client`, `client_appreciation`, `coverage_marker`, `job_opening`, `solution_page`, `policy_document`, `milestone`, `press_release`.
- **Taxonomies:** `category`, `post_tag`, `project_category`, `project_state`, `award_category`, `job_department`.
- **Admin Features:** Structured metaboxes for all custom fields, galleries, repeaters, PDF/file pickers (`class-admin-metaboxes.php`), SEO meta boxes (`_pisl_seo`), Single-Type Pages Hub (`Pislinfra Pages`), Ongoing→Completed promotion button/workflow (`class-workflows.php`), Coverage Marker auto-sync, and EHS/Governance validation.
- **Caching:** WordPress Transient API cache automatically bumped via `pisl_cache_version` on post save, delete, trash, media upload, and single-type option update.

---

## 4. Rollback Plan
Your original untouched project backup remains separate from this workspace, and `cms-pisl/` inside the duplicate workspace is completely disconnected from runtime and can be deleted once production approval is confirmed.
