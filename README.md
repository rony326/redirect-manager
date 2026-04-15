# Redirect Manager

A lightweight, self-hosted PHP web interface to manage Apache `.htaccess` redirects — built for shared hosting environments like Infomaniak.

Authentication is handled via **Authentik** (OpenID Connect), so no passwords are stored in the app itself. Individual redirect entries can be protected with a separate **lock password**.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Hosting](https://img.shields.io/badge/hosting-Infomaniak-4ade80)

---

## Features

- **Read & write** all redirect rules directly from/to `.htaccess`
- Supports **three Apache redirect syntaxes**: `Redirect`, `RedirectMatch`, `RewriteRule [R=301/302]`
- **301 / 302** toggle per entry
- **Inline editing** with comment field
- **Lock protection** per entry — requires a separate lock password to edit or delete
- **Authentik OIDC** login — no local password stored, session-based
- **Automatic backup** (`.htaccess.bak`) before every save
- All other `.htaccess` content (DEFLATE, RewriteEngine, Git protection, etc.) is preserved
- Built-in **diagnostic tool** (`check.php`) controlled by a config flag
- Folder hardened via `.htaccess` security rules + HTTP security headers

---

## File Structure

```
redirect-manager/
├── config.php       ← Only file you need to edit
├── index.php        ← Entry point & POST action handler
├── oidc.php         ← Authentik OIDC auth flow (cURL-based)
├── htaccess.php     ← .htaccess parser & writer
├── view.php         ← HTML / CSS / JS frontend
├── callback.php     ← OIDC callback endpoint
├── check.php        ← Diagnostic tool (enable in config.php)
└── .htaccess        ← Folder security rules
```

---

## Requirements

- PHP **8.0+**
- **cURL** extension (no `allow_url_fopen` needed)
- **OpenSSL** extension
- Apache with `mod_rewrite` and `mod_headers` enabled
- A running **Authentik** instance

---

## Installation

### 1. Create an Authentik Application

In the Authentik admin panel (`https://auth.yourdomain.com/if/admin`):

1. Go to **Applications → Create with Wizard**
2. Set a name (e.g. `Redirect Manager`) and slug (e.g. `redirect-manager`)
3. Provider type: **OAuth2/OpenID Connect**
4. Client type: **Confidential**
5. Add redirect URI:
   ```
   https://yourdomain.com/redirect-manager/callback.php
   ```
6. Set **Launch URL**:
   ```
   https://yourdomain.com/redirect-manager/
   ```
7. Save and note the **Client ID** and **Client Secret** from the provider

Verify your OIDC discovery endpoint is reachable:
```
https://auth.yourdomain.com/application/o/redirect-manager/.well-known/openid-configuration
```

### 2. Configure the App

Edit `config.php` and fill in your values:

```php
define('OIDC_CLIENT_ID',     'your-client-id');
define('OIDC_CLIENT_SECRET', 'your-client-secret');
define('OIDC_ISSUER',        'https://auth.yourdomain.com/application/o/redirect-manager');
define('APP_URL',            'https://yourdomain.com/redirect-manager');  // no trailing slash
define('LOCK_PASSWORD',      'your-lock-password');
define('HTACCESS',           '../.htaccess');
define('HTACCESS_BACKUP',    '../.htaccess.bak');
define('ENABLE_CHECK',       false);  // set to true only during setup
```

### 3. Upload via FTP

Upload the entire `redirect-manager/` folder to your web root:

```
/sites/yourdomain.com/
    redirect-manager/
        .htaccess
        callback.php
        check.php
        config.php
        htaccess.php
        index.php
        oidc.php
        view.php
    .htaccess          ← your existing site .htaccess (managed by this tool)
```

### 4. Run the Diagnostic Tool

Temporarily enable `check.php` in `config.php`:

```php
define('ENABLE_CHECK', true);
```

Open in browser:
```
https://yourdomain.com/redirect-manager/check.php
```

All checks should be green. Then **disable it again**:

```php
define('ENABLE_CHECK', false);
```

### 5. Open the App

```
https://yourdomain.com/redirect-manager/
```

You will be redirected to Authentik to log in. After authentication you land in the manager.

---

## File Permissions

| File | Permission | Notes |
|---|---|---|
| `config.php` | `640` | Contains secrets — restrict group read |
| All other `.php` | `644` | Read-only for PHP process |
| `.htaccess` (this folder) | `644` | Read by Apache |
| `../.htaccess` (site root) | `644` | PHP needs write access — use `664` if saving fails |
| Folder itself | `755` | |

---

## How It Works

### Authentication Flow

```
User visits /redirect-manager/
  → index.php: no valid session
  → Generate state + nonce, store in session
  → Redirect to Authentik authorization endpoint

User logs in at Authentik
  → Authentik redirects to /redirect-manager/callback.php?code=...

callback.php → index.php:
  → Validate state (CSRF protection)
  → Exchange code for tokens via cURL POST
  → Validate nonce in id_token
  → Fetch user info from userinfo endpoint
  → Store user data in PHP session
  → Redirect to /redirect-manager/

Logout:
  → Destroy PHP session
  → Redirect to Authentik end_session endpoint
```

### .htaccess Handling

The parser reads all three common redirect syntaxes:

```apache
Redirect 301 /from https://to                        # type: redirect
RedirectMatch 301 ^/pattern$ https://to              # type: redirectmatch
RewriteRule ^path/?$ https://to [R=301,L]            # type: rewrite
```

Comments immediately preceding a rule are read as the entry's **label**. A `# [locked]` comment marks an entry as lock-protected.

On save, the tool:
1. Creates a backup at `.htaccess.bak`
2. Strips all managed redirect lines (and their preceding comments)
3. Inserts the updated block after `RewriteEngine On`
4. Preserves all other content unchanged

---

## Lock Protection

Individual entries can be locked with a separate **lock password** (defined in `config.php` as `LOCK_PASSWORD`, independent from the Authentik login).

- The 🔓/🔒 icon on each row opens a modal to toggle the lock state
- Locked entries require the lock password to edit or delete
- The lock flag is stored as `# [locked]` in `.htaccess` directly above the rule

---

## Troubleshooting

| Error | Likely cause | Fix |
|---|---|---|
| Blank page / no redirect to Authentik | `config.php` not loaded | Check file path and PHP errors |
| `Authentik not reachable` | cURL can't reach OIDC issuer | Verify `OIDC_ISSUER` URL, run `check.php` |
| `State mismatch` | Session cookie issue | Check `session_save_path` in `check.php` |
| `Token exchange failed` | Wrong client secret or redirect URI mismatch | Compare `APP_URL/callback.php` with Authentik provider settings exactly |
| Redirect loop after login | Wrong `APP_URL` or missing Launch URL in Authentik | Set Launch URL in Authentik application to `APP_URL/` |
| 403 on the folder | `.htaccess` FilesMatch issue | Ensure `mod_headers` is enabled on your host |
| `.htaccess` not saved | PHP can't write to site root | Set `../.htaccess` to `664` |

---

## Security Notes

- Client Secret and Lock Password are stored only in `config.php` — set permissions to `640`
- `check.php` is blocked by default (`ENABLE_CHECK = false`) — no need to delete it
- The folder `.htaccess` blocks direct access to all files except `index.php` and `callback.php` from the browser; `config.php`, `oidc.php`, `htaccess.php` and `view.php` are PHP includes only
- Sessions expire when the Authentik token expires (`exp` claim in id_token)
- CSRF is mitigated via the OIDC `state` parameter + nonce validation

---

## License

MIT — use freely, modify as needed.
