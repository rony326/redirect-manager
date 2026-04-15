<?php
// ═══════════════════════════════════════════════════════════════════
//  index.php – Einstiegspunkt & App
// ═══════════════════════════════════════════════════════════════════

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
        if ($from && $to) {
            $redirects   = parseRedirects();
            $redirects[] = [
                'from' => $from, 'to' => $to, 'code' => $code,
                'comment' => $comment, 'locked' => false,
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
                        'comment' => $comment, 'pattern' => $newPattern,
                    ]);
                    saveRedirects($redirects);
                    $success = 'Weiterleitung aktualisiert.';
                }
            }
        } else {
            $error = 'Bitte beide Felder ausfüllen.';
        }
    }

    // Lock / Unlock
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
}

// ── View-Daten ────────────────────────────────────────────────────
$redirects   = parseRedirects();
$htExists    = file_exists(HTACCESS);
$lockedCount = count(array_filter($redirects, fn($r) => isLocked($r)));
$userName    = $_SESSION['user_name']  ?? '';
$userEmail   = $_SESSION['user_email'] ?? '';

// ── HTML ausgeben ─────────────────────────────────────────────────
require __DIR__ . '/view.php';
