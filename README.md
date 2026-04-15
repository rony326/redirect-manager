# Redirect Manager

A self-hosted PHP web interface for managing Apache `.htaccess` redirects — built for shared hosting environments like Infomaniak.

Authentication is handled via **Authentik** (OpenID Connect). Individual entries can be organised with **categories**, protected with a **lock password**, and exported as **QR codes**.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Hosting](https://img.shields.io/badge/hosting-Infomaniak-4ade80)

---

## Features

- **Read & write** all redirect rules directly from/to `.htaccess`
- Supports **three Apache redirect syntaxes**: `Redirect`, `RedirectMatch`, `RewriteRule [R=301/302]`
- **301 / 302** toggle per entry
- **Inline editing** with comment and category fields
- **Categories** — tag-based grouping with colour badges and filter bar
- **Lock protection** — per-entry lock requiring a separate password to edit or delete
- **Bulk actions** — select multiple entries to lock/unlock, change code, change URL, or set category
- **Sortable columns** — click any header to sort
- **QR Code** per entry — generates and downloads a PNG QR code for the full redirect URL
- **Copy to clipboard** — copies the full redirect URL with one click
- **Authentik OIDC** login — no local password, session-based
- **Automatic backup** (`.htaccess.bak`) before every save
- All other `.htaccess` content preserved on save
- Built-in **diagnostic tool** (`check.php`) controlled by config flag
- Responsive layout — card view on mobile, progressive column hiding on tablet
- Folder hardened via `.htaccess` rules + HTTP security headers

---

## File Structure

```
redirect-manager/
├── config.php          ← Only file you need to edit
├── index.php           ← Entry point & POST handler
├── oidc.php            ← Authentik OIDC auth flow (cURL-based)
├── htaccess.php        ← .htaccess parser & writer
├── view.php            ← HTML template
├── style.css           ← Stylesheet (browser-cached separately)
├── callback.php        ← OIDC callback endpoint
├── check.php           ← Diagnostic tool (enable in config.php)
├── config.example.php  ← Template for config.php
└── .htaccess           ← Folder security rules
```

---

## Requirements

- PHP **8.0+**
- **cURL** extension (no `allow_url_fopen` needed)
- **OpenSSL** extension
- Apache with `mod_rewrite` and `mod_headers`
- A running **Authentik** instance

---

## Installation

### 1. Create an Authentik Application

In the Authentik admin panel:

1. **Applications → Create with Wizard**
2. Name: `Redirect Manager`, slug: `redirect-manager`
3. Provider type: **OAuth2/OpenID Connect**, client type: **Confidential**
4. Redirect URI: `https://yourdomain.com/redirect-manager/callback.php`
5. Launch URL: `https://yourdomain.com/redirect-manager/`
6. Note the **Client ID** and **Client Secret**

### 2. Configure `config.php`

Copy `config.example.php` to `config.php` and fill in your values:

```php
define('OIDC_CLIENT_ID',     'your-client-id');
define('OIDC_CLIENT_SECRET', 'your-client-secret');
define('OIDC_ISSUER',        'https://auth.yourdomain.com/application/o/redirect-manager');
define('APP_URL',            'https://yourdomain.com/redirect-manager'); // no trailing slash
define('LOCK_PASSWORD',      'your-lock-password');
define('SITE_BASE_URL',      'https://yourdomain.com'); // used for QR codes & clipboard
define('ENABLE_CHECK',       false);
```

### 3. Upload via FTP

Upload the entire `redirect-manager/` folder to your web root.

### 4. Run the Diagnostic Tool

Enable `check.php` temporarily:

```php
define('ENABLE_CHECK', true);
```

Open `https://yourdomain.com/redirect-manager/check.php` — all checks should be green. Then set `ENABLE_CHECK` back to `false`.

### 5. Open the App

```
https://yourdomain.com/redirect-manager/
```

You will be redirected to Authentik to log in.

---

## Configuration Reference

| Constant | Description |
|---|---|
| `OIDC_CLIENT_ID` | Client ID from Authentik provider |
| `OIDC_CLIENT_SECRET` | Client Secret from Authentik provider |
| `OIDC_ISSUER` | OIDC issuer URL (format: `https://auth.domain/application/o/slug`) |
| `APP_URL` | Public URL of the redirect-manager folder, no trailing slash |
| `LOCK_PASSWORD` | Password to lock/unlock individual entries |
| `SITE_BASE_URL` | Root domain for building full URLs in QR codes and clipboard |
| `HTACCESS` | Path to the `.htaccess` file to manage (default: `../htaccess`) |
| `HTACCESS_BACKUP` | Path for automatic backup (default: `../.htaccess.bak`) |
| `SESSION_NAME` | PHP session name (default: `rm_session`) |
| `ENABLE_CHECK` | `true` enables `check.php` diagnostic tool (disable in production) |
| `CATEGORIES` | Array of categories: `'slug' => ['label' => '...', 'color' => '#hex']` |

---

## File Permissions

| File | Permission |
|---|---|
| `config.php` | `640` — contains secrets |
| All other `.php` | `644` |
| `style.css` | `644` |
| `.htaccess` (this folder) | `644` |
| `../.htaccess` (site root) | `644` (use `664` if saving fails) |
| Folder | `755` |

---

## How It Works

### Authentication Flow

```
User visits /redirect-manager/
  → No valid session → generate state + nonce
  → Redirect to Authentik

User logs in at Authentik
  → Redirect to /callback.php?code=...
  → Validate state (CSRF), exchange code for tokens
  → Validate nonce in id_token
  → Store user info in session
  → Redirect to /

Logout:
  → Destroy session → redirect to Authentik end_session
```

### .htaccess Handling

Supported syntaxes:

```apache
Redirect 301 /from https://to           # type: redirect
RedirectMatch 301 ^/pattern$ https://to  # type: redirectmatch
RewriteRule ^path/?$ https://to [R=301,L] # type: rewrite
```

Comments above a rule are read as the entry label. `# [locked]` marks an entry as lock-protected. `# [cat:slug]` assigns a category. On save, only managed redirect lines are replaced — all other `.htaccess` content is preserved.

---

## Troubleshooting

| Error | Fix |
|---|---|
| `Authentik not reachable` | Verify `OIDC_ISSUER`, run `check.php` |
| `State mismatch` | Session issue — check `session_save_path` in `check.php` |
| `Token exchange failed` | Wrong client secret or redirect URI mismatch |
| Redirect loop after login | Set Launch URL in Authentik to `APP_URL/` |
| 403 on `index.php` | Check that `RM_BOOT` is defined before requiring `config.php` |
| `.htaccess` not saved | Set `../.htaccess` to `664` |
| QR code not generating | Check browser console — `qrcode.min.js` requires internet access |

---

## License

MIT — use freely, modify as needed.
