# Changelog

All notable changes to Redirect Manager are documented here.

---

## [1.3.0] – 2025-04-15

### Added
- **QR Code generation** per redirect entry — opens a modal with a downloadable PNG
- **Copy to clipboard** button per entry — copies the full redirect URL (base domain + path)
- **`SITE_BASE_URL`** config option — defines the domain prefix used for QR codes and clipboard URLs
- **Categories** — tag-based organisation for redirect entries, defined in `config.php`
  - Filter bar above the table (only shows categories with entries)
  - Category badge per row with configurable colour
  - Category field in add form and inline edit
  - Bulk category assignment
- **Sortable columns** — click any column header to sort ascending/descending
- **Bulk actions** — select multiple entries and apply in one step:
  - Lock / unlock (requires lock password)
  - Change redirect code (301 / 302)
  - Change target URL
  - Set category
- **Inline editing** — edit any entry directly in the table row
- **Comment field** — optional label per entry stored as `#` comment in `.htaccess`
  - Full comment shown as tooltip on hover when truncated
- **Lock protection** — individual entries locked with a separate lock password
  - Lock flag stored as `# [locked]` in `.htaccess`
  - Locked entries require password to edit, delete, or change via bulk actions
- **Responsive layout** — table switches to card layout on mobile (< 680px), columns progressively hidden on smaller screens
- **Custom dropdown** — replaces native `<select>` for categories

### Changed
- CSS extracted to standalone `style.css` for browser caching
- `table-layout:fixed` with `colgroup` for reliable column widths
- Target URL column gets remaining space, truncated with ellipsis + hover tooltip
- Actions column widened to 190px to accommodate copy/QR/edit/delete buttons

---

## [1.2.0] – 2025-04-15

### Added
- **Authentik OIDC authentication** — replaces password login
  - Full OIDC flow via cURL (no `allow_url_fopen` required)
  - State + nonce validation (CSRF protection)
  - Token expiry check, OIDC logout via `end_session_endpoint`
  - User name and email displayed in header
- **Multi-file architecture**: `config.php`, `oidc.php`, `htaccess.php`, `index.php`, `view.php`
- **`check.php`** — diagnostic tool for cURL, OpenSSL, sessions, OIDC connectivity
  - Controlled by `ENABLE_CHECK` in `config.php`
- **`RM_BOOT` guard** on all include files — direct access returns 403

### Changed
- All HTTP requests use cURL (Infomaniak compatibility — `allow_url_fopen` disabled by default)
- Password login removed entirely

---

## [1.1.0] – 2025-04-15

### Added
- **Three `.htaccess` syntax formats** supported:
  - `Redirect 301 /path https://...`
  - `RedirectMatch 301 pattern https://...`
  - `RewriteRule ^path/?$ https://... [R=301,L]`
- **301 / 302** redirect code selector
- **Automatic `.htaccess.bak` backup** before every save
- All non-redirect content preserved on save (DEFLATE, RewriteEngine, etc.)

---

## [1.0.0] – 2025-04-15

### Initial release

- Single-file PHP interface for managing Apache `.htaccess` redirects
- Add, view, delete `Redirect 301` entries
- Session-based password login
- Designed for Infomaniak shared hosting
