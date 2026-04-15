<?php
// ═══════════════════════════════════════════════════════════════════
//  index.php – Einstiegspunkt & App
// ═══════════════════════════════════════════════════════════════════

define('RM_BOOT', true);

require __DIR__ . '/config.php';
require __DIR__ . '/oidc.php';
require __DIR__ . '/htaccess.php';

// Auth prüfen – leitet ggf. zu Authentik weiter
authHandle();

// ── POST-Aktionen ─────────────────────────────────────────────────
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Neue Weiterleitung hinzufügen
    if (isset($_POST['add'])) {
        $from    = trim($_POST['from']    ?? '');
        $to      = trim($_POST['to']      ?? '');
        $code    = in_array($_POST['code'] ?? '', ['301','302']) ? $_POST['code'] : '301';
        $comment = trim($_POST['comment'] ?? '');
        $cat     = trim($_POST['cat']     ?? '');
        $cats    = defined('CATEGORIES') ? array_keys(CATEGORIES) : [];
        if ($cat && !in_array($cat, $cats)) $cat = '';
        if ($from && $to) {
            $redirects   = parseRedirects();
            $redirects[] = [
                'from' => $from, 'to' => $to, 'code' => $code,
                'comment' => $comment, 'cat' => $cat, 'locked' => false,
                'type' => 'rewrite', 'pattern' => '^' . ltrim($from, '/') . '/?$',
            ];
            saveRedirects($redirects);
            $success = 'Weiterleitung gespeichert.';
        } else {
            $error = 'Bitte beide Felder ausfüllen.';
        }
    }

    // Weiterleitung löschen
    if (isset($_POST['delete'])) {
        $idx       = (int)$_POST['idx'];
        $redirects = parseRedirects();
        if (isset($redirects[$idx])) {
            if (isLocked($redirects[$idx]) && !checkLockPw(trim($_POST['lock_pw'] ?? ''))) {
                $error = 'Falsches Lock-Passwort – Eintrag ist gesperrt.';
            } else {
                array_splice($redirects, $idx, 1);
                saveRedirects($redirects);
                $success = 'Weiterleitung gelöscht.';
            }
        }
    }

    // Weiterleitung bearbeiten
    if (isset($_POST['edit'])) {
        $idx     = (int)$_POST['idx'];
        $from    = trim($_POST['from']    ?? '');
        $to      = trim($_POST['to']      ?? '');
        $code    = in_array($_POST['code'] ?? '', ['301','302']) ? $_POST['code'] : '301';
        $comment = trim($_POST['comment'] ?? '');
        $cat     = trim($_POST['cat']     ?? '');
        $cats    = defined('CATEGORIES') ? array_keys(CATEGORIES) : [];
        if ($cat && !in_array($cat, $cats)) $cat = '';
        if ($from && $to) {
            $redirects = parseRedirects();
            if (isset($redirects[$idx])) {
                if (isLocked($redirects[$idx]) && !checkLockPw(trim($_POST['lock_pw'] ?? ''))) {
                    $error = 'Falsches Lock-Passwort – Eintrag ist gesperrt.';
                } else {
                    $old        = $redirects[$idx];
                    $newPattern = $old['pattern'] ?? ('^' . ltrim($old['from'], '/') . '/?$');
                    if ($from !== $old['from']) $newPattern = '^' . ltrim($from, '/') . '/?$';
                    $redirects[$idx] = array_merge($old, [
                        'from' => $from, 'to' => $to, 'code' => $code,
                        'comment' => $comment, 'cat' => $cat, 'pattern' => $newPattern,
                    ]);
                    saveRedirects($redirects);
                    $success = 'Weiterleitung aktualisiert.';
                }
            }
        } else {
            $error = 'Bitte beide Felder ausfüllen.';
        }
    }

    // Lock / Unlock (single)
    if (isset($_POST['toggle_lock'])) {
        $idx = (int)$_POST['idx'];
        if (!checkLockPw(trim($_POST['lock_pw'] ?? ''))) {
            $error = 'Falsches Lock-Passwort.';
        } else {
            $redirects = parseRedirects();
            if (isset($redirects[$idx])) {
                $redirects[$idx]['locked'] = !isLocked($redirects[$idx]);
                saveRedirects($redirects);
                $success = $redirects[$idx]['locked'] ? 'Eintrag gesperrt 🔒' : 'Eintrag entsperrt 🔓';
            }
        }
    }

    // Bulk actions: lock/unlock/code/to
    if (!empty($_POST['bulk_action'])) {
        $action   = $_POST['bulk_action'];
        $selected = array_map('intval', (array)($_POST['selected'] ?? []));
        $lockPw   = trim($_POST['lock_pw'] ?? '');
        $bulkCode = in_array($_POST['bulk_code'] ?? '', ['301','302','']) ? ($_POST['bulk_code'] ?? '') : '';
        $bulkTo   = trim($_POST['bulk_to'] ?? '');

        $validActions = ['lock', 'unlock', 'set_code', 'set_to', 'set_cat'];

        if (empty($selected)) {
            $error = 'Keine Einträge ausgewählt.';
        } elseif (!in_array($action, $validActions)) {
            $error = 'Ungültige Aktion.';
        } else {
            $redirects   = parseRedirects();
            $lockPwValid = checkLockPw($lockPw);
            $bulkCat     = trim($_POST['bulk_cat'] ?? '');
            $cats        = defined('CATEGORIES') ? array_keys(CATEGORIES) : [];
            if ($bulkCat && !in_array($bulkCat, $cats)) $bulkCat = '';

            if (in_array($action, ['lock', 'unlock']) && !$lockPwValid) {
                $error = 'Falsches Lock-Passwort.';
            } else {
                $count   = 0;
                $skipped = 0;
                foreach ($selected as $idx) {
                    if (!isset($redirects[$idx])) continue;
                    $entryLocked = isLocked($redirects[$idx]);
                    if ($entryLocked && !$lockPwValid) { $skipped++; continue; }

                    if ($action === 'lock')     $redirects[$idx]['locked'] = true;
                    if ($action === 'unlock')   $redirects[$idx]['locked'] = false;
                    if ($action === 'set_code' && $bulkCode) $redirects[$idx]['code'] = $bulkCode;
                    if ($action === 'set_to'   && $bulkTo)   $redirects[$idx]['to']   = $bulkTo;
                    if ($action === 'set_cat')               $redirects[$idx]['cat']  = $bulkCat;
                    $count++;
                }
                saveRedirects($redirects);
                $labels = [
                    'lock'     => 'gesperrt 🔒',
                    'unlock'   => 'entsperrt 🔓',
                    'set_code' => 'Code geändert',
                    'set_to'   => 'Ziel-URL geändert',
                    'set_cat'  => 'Kategorie gesetzt',
                ];
                $success = $count . ' Einträge ' . ($labels[$action] ?? 'aktualisiert');
                if ($skipped > 0) $success .= ' · ' . $skipped . ' gesperrte übersprungen';
            }
        }
    }
}

// ── View-Daten ────────────────────────────────────────────────────
$categories  = defined('CATEGORIES') ? CATEGORIES : [];
$activeCat   = trim($_GET['cat'] ?? '');
$allRedirects = parseRedirects();
// Filter by category if set
$redirects   = $activeCat
    ? array_values(array_filter($allRedirects, fn($r) => ($r['cat'] ?? '') === $activeCat))
    : $allRedirects;
$htExists    = file_exists(HTACCESS);
$lockedCount = count(array_filter($redirects, fn($r) => isLocked($r)));
$userName    = $_SESSION['user_name']  ?? '';
$userEmail   = $_SESSION['user_email'] ?? '';
$siteBaseUrl = defined('SITE_BASE_URL') ? rtrim(SITE_BASE_URL, '/') : '';

// ── HTML ausgeben ─────────────────────────────────────────────────
require __DIR__ . '/view.php';
