<?php
// Direct access guard
if (!defined('RM_BOOT')) { http_response_code(403); exit('403 Forbidden'); }
// ═══════════════════════════════════════════════════════════════════
//  config.example.php — Copy this to config.php and fill in values
// ═══════════════════════════════════════════════════════════════════

// ── Authentik OIDC ───────────────────────────────────────────────
// Found in: Authentik Admin → Providers → your provider → Edit
define('OIDC_CLIENT_ID',     'your-client-id-here');
define('OIDC_CLIENT_SECRET', 'your-client-secret-here');

// Format: https://auth.yourdomain.com/application/o/your-app-slug
// Verify: https://auth.yourdomain.com/application/o/your-app-slug/.well-known/openid-configuration
define('OIDC_ISSUER', 'https://auth.yourdomain.com/application/o/your-app-slug');

// ── App URL ──────────────────────────────────────────────────────
// Public URL of this folder — no trailing slash
// Must match the redirect URI registered in Authentik:
//   https://yourdomain.com/redirect-manager/callback.php
define('APP_URL', 'https://yourdomain.com/redirect-manager');

// ── Lock Password ────────────────────────────────────────────────
// Separate from Authentik login — used to lock/unlock individual entries
define('LOCK_PASSWORD', 'change-me-to-something-strong');

// ── File Paths ───────────────────────────────────────────────────
// Path to the .htaccess file to manage (relative to this file)
define('HTACCESS',        '../.htaccess');
define('HTACCESS_BACKUP', '../.htaccess.bak');

// ── Session ──────────────────────────────────────────────────────
define('SESSION_NAME', 'rm_session');

// ── Diagnostic Tool ──────────────────────────────────────────────
// true  = check.php is accessible (enable during setup only!)
// false = check.php returns 403 (default for production)
define('ENABLE_CHECK', false);
