# BSC Report Builder

A quarterly Balanced Scorecard (BSC) web application that generates individual performance reports for co-founders alongside a consolidated company scorecard and executive decisions with Amharic-first and English reporting support.

---

## Tech Stack
- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Inertia.js, Vue 3 (`<script setup>`), Tailwind CSS, vue-i18n
- **Database**: SQLite (zero-config, high performance)
- **Scoring Engine**: Pure PHP ScoreCalculator + TypeScript mirror (`bsc.ts`)
- **Document Export**: PDF ([spatie/laravel-pdf](https://github.com/spatie/laravel-pdf) with headless Chromium) & DOCX ([PHPOffice/PHPWord](https://github.com/PHPOffice/PHPWord))
- **Charts**: Pure SVG responsive trend charts (zero heavy runtime dependencies)
- **Code Style & CI**: Laravel Pint, GitHub Actions CI workflow

---

## Features

1. **Reports Management**: Create, edit, duplicate for next quarter with targets preserved and actuals reset, finalize/unfinalize.
2. **Data-Entry Wizard**: 5-step wizard with live autosave, live achievement % and BSC score calculation, and drag/keyboard reordering.
3. **Template Library**: 21 pre-seeded KPI templates across Sales, Marketing, and Tech roles. Quick insertion modal inside wizard and saving custom activities as templates.
4. **Historical Performance Trends**: Quarter-over-quarter comparison of company and co-founders performance across the last 4 quarters using pure SVG line charts and comparison table.
5. **JSON Backup & Migration**: Export complete report raw data as formatted JSON and import JSON backups to instantly generate new drafts.
6. **System Settings**: Configurable company name, logo upload, scoring cap (default 120%), rating bands (Outstanding, Exceeds, Meets, Needs Improvement), and default language.
7. **Bilingual Amharic-First**: Amharic/English toggle with embedded Noto Sans Ethiopic fonts to prevent character corruption.

---

## Getting Started (Local Development)

```bash
# Clone the repository
git clone https://github.com/your-org/bsc.git
cd bsc

# Install dependencies
composer install
npm install

# Build frontend assets
npm run build

# Setup environment & database
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link

# Start local servers
php artisan serve
```

---

## Running Tests & Quality Checks

```bash
# Run PHP Pest tests
php artisan test

# Run JavaScript Vitest tests
npx vitest run

# Check Laravel Pint code style
./vendor/bin/pint --test

# Auto-fix code style
./vendor/bin/pint
```

---

## Production Deployment Guide

### Option 1: VPS Deployment (Ubuntu / Debian + Nginx + PHP 8.2+ FPM)

#### 1. System Requirements & Packages
```bash
sudo apt update
sudo apt install -y nginx php8.2 php8.2-fpm php8.2-sqlite3 php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-intl chromium-browser fonts-noto-core
```

#### 2. Nginx Server Configuration
Create `/etc/nginx/sites-available/bsc.conf`:

```nginx
server {
    listen 80;
    server_name bsc.example.com;
    root /var/www/bsc/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/bsc.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

#### 3. Permissions & Storage Symlink
```bash
cd /var/www/bsc
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R 775 storage bootstrap/cache database
sudo chmod 664 database/database.sqlite

php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 4. Scheduled Tasks (Cron)
Add the Laravel schedule runner to cron (`sudo crontab -u www-data -e`):
```cron
* * * * * cd /var/www/bsc && php artisan schedule:run >> /dev/null 2>&1
```

---

### Option 2: Shared Hosting (DirectAdmin / cPanel)

1. **Document Root**:
   - In DirectAdmin or cPanel, set your domain's document root to the `public/` directory (e.g. `/home/username/domains/example.com/public_html/public`).
   - If document root cannot be changed, move files into `/public_html` and ensure `.htaccess` points requests to `/public`.
2. **PHP Version**:
   - In DirectAdmin PHP Selector, select **PHP 8.2** or higher with extensions: `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo`, `gd`, `zip`, `intl`.
3. **SQLite File Permissions**:
   - Ensure the SQLite database file and parent directory are writable:
     ```bash
     chmod 775 /home/username/domains/example.com/database
     chmod 664 /home/username/domains/example.com/database/database.sqlite
     ```
4. **Storage Symlink**:
   - Run `php artisan storage:link` via SSH or DirectAdmin Terminal.
   - Alternatively, create a symlink via PHP script:
     ```php
     <?php symlink('/home/username/domains/example.com/storage/app/public', '/home/username/domains/example.com/public/storage'); ?>
     ```
5. **DirectAdmin Cron Job**:
   - Go to **Cron Jobs** in DirectAdmin and add:
     ```bash
     * * * * * cd /home/username/domains/example.com && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
     ```

---

## PDF & DOCX Export Requirements (Puppeteer / Chromium)

The app exports server-side PDF reports using [spatie/laravel-pdf](https://github.com/spatie/laravel-pdf) (powered by [spatie/browsershot](https://github.com/spatie/browsershot) and Puppeteer/Chromium) and Word documents via [PhpOffice/PhpWord](https://github.com/PHPOffice/PHPWord).

### 1. Chromium Binary Configuration
In `.env`:
```env
LARAVEL_PDF_DRIVER=browsershot
LARAVEL_PDF_CHROME_PATH=/usr/bin/chromium
LARAVEL_PDF_NODE_BINARY=/usr/bin/node
LARAVEL_PDF_NO_SANDBOX=true
```

### 2. Puppeteer Installation
```bash
npm install puppeteer --save-dev
```

### 3. Graceful Fallback
If Chromium is unavailable or restricted on shared hosting, the PDF button gracefully serves a browser-printable A4 preview page with print styling and auto-print triggers.
