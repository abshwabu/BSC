# BSC Report Builder

A quarterly Balanced Scorecard (BSC) web application that generates individual performance reports for co-founders alongside a consolidated company report with Amharic reporting support.

## Stack
- **Framework**: Laravel 11
- **Frontend**: Inertia.js + Vue 3 (script setup) + Tailwind CSS
- **Database**: SQLite
- **Testing**: Pest PHP
- **Auth**: Laravel Breeze (Inertia Vue)

## Getting Started

```bash
composer install
npm install
npm run build
php artisan migrate --seed
php artisan serve
```

## Running Tests

```bash
php artisan test
npm test
```

## PDF & DOCX Export Requirements (Chrome / Browsershot)

The application exports server-side PDF reports using [spatie/laravel-pdf](https://github.com/spatie/laravel-pdf) (powered by [spatie/browsershot](https://github.com/spatie/browsershot) and Puppeteer/Chromium) and Word documents via [PhpOffice/PhpWord](https://github.com/PHPOffice/PHPWord).

### 1. Installing Chrome / Chromium & Puppeteer

Ensure Node.js and a headless Chrome or Chromium browser are available on the server or development machine:

```bash
# Ubuntu / Debian
sudo apt update && sudo apt install -y chromium-browser fonts-noto-core

# Arch Linux
sudo pacman -S chromium noto-fonts noto-fonts-extra

# macOS
brew install --cask google-chrome
```

Install Puppeteer in the project root:

```bash
npm install puppeteer --save-dev
```

### 2. Environment Configuration

If Chromium or Node are located at custom paths, configure them in your `.env` file:

```env
LARAVEL_PDF_DRIVER=browsershot
LARAVEL_PDF_CHROME_PATH=/usr/bin/chromium
LARAVEL_PDF_NODE_BINARY=/usr/bin/node
LARAVEL_PDF_NO_SANDBOX=true
```

### 3. Font Support (Amharic / Ethiopic)

To avoid tofu/missing character glyphs in PDF and Word exports:
- `Noto Sans Ethiopic` regular and bold TrueType fonts are bundled under `resources/fonts/`.
- Ensure `Noto Sans Ethiopic` is also installed in your system font path (e.g. `/usr/share/fonts/noto/`).

### 4. Graceful Fallback

If Chrome or headless Puppeteer is unavailable in a server environment, the PDF export automatically falls back to an A4 print-optimized HTML view with `@media print` rules, allowing the user to print or save as PDF directly from the browser print dialog.

