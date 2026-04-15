<?php
// ═══════════════════════════════════════════════════════════════════
//  check.php – Diagnose-Tool
//  Nur erreichbar wenn in config.php: define('ENABLE_CHECK', true);
// ═══════════════════════════════════════════════════════════════════

define('RM_BOOT', true);
require __DIR__ . '/config.php';

if (!defined('ENABLE_CHECK') || ENABLE_CHECK !== true) {
    http_response_code(403);
    die('403 – Diagnose-Tool ist deaktiviert. In config.php ENABLE_CHECK auf true setzen.');
}

// ── Checks ────────────────────────────────────────────────────────
$checks = [];

// 1. cURL
$checks[] = [
    'label' => 'cURL Extension',
    'ok'    => function_exists('curl_init'),
    'info'  => function_exists('curl_init')
        ? 'verfügbar (' . curl_version()['version'] . ')'
        : 'NICHT verfügbar – Infomaniak Support kontaktieren',
];

// 2. allow_url_fopen (nur Info)
$checks[] = [
    'label' => 'allow_url_fopen',
    'ok'    => true,
    'info'  => ini_get('allow_url_fopen')
        ? 'aktiviert (wird nicht benötigt, cURL wird verwendet)'
        : 'deaktiviert (kein Problem, cURL wird verwendet)',
];

// 3. OpenSSL
$checks[] = [
    'label' => 'OpenSSL',
    'ok'    => extension_loaded('openssl'),
    'info'  => extension_loaded('openssl') ? 'geladen' : 'NICHT geladen',
];

// 4. PHP Sessions
session_name(SESSION_NAME);
session_start();
$_SESSION['check_test'] = 'ok';
$checks[] = [
    'label' => 'PHP Sessions',
    'ok'    => ($_SESSION['check_test'] ?? '') === 'ok',
    'info'  => 'session_save_path: ' . (session_save_path() ?: '(Standard)'),
];
unset($_SESSION['check_test']);

// 5. .htaccess lesbar
$checks[] = [
    'label' => '.htaccess lesbar',
    'ok'    => file_exists(HTACCESS) && is_readable(HTACCESS),
    'info'  => file_exists(HTACCESS)
        ? 'gefunden: ' . realpath(HTACCESS)
        : 'nicht gefunden unter: ' . HTACCESS . ' (wird beim ersten Speichern erstellt)',
];

// 6. .htaccess schreibbar
$htWritable = file_exists(HTACCESS) ? is_writable(HTACCESS) : is_writable(dirname(HTACCESS));
$checks[] = [
    'label' => '.htaccess schreibbar',
    'ok'    => $htWritable,
    'info'  => $htWritable ? 'Schreibzugriff vorhanden' : 'KEIN Schreibzugriff – Dateiberechtigungen prüfen',
];

// 7. OIDC Discovery via cURL
$discoveryUrl = rtrim(OIDC_ISSUER, '/') . '/.well-known/openid-configuration';
$discoveryOk  = false;
$discoveryInfo = 'cURL nicht verfügbar – Test übersprungen';
$endpoints = [];

if (function_exists('curl_init')) {
    $ch = curl_init($discoveryUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'RedirectManager-Check/1.0',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        $discoveryInfo = 'cURL Fehler: ' . $err;
    } elseif ($http !== 200) {
        $discoveryInfo = 'HTTP ' . $http . ' – OIDC_ISSUER in config.php prüfen';
    } else {
        $data = json_decode($body, true);
        if (isset($data['authorization_endpoint'])) {
            $discoveryOk   = true;
            $discoveryInfo = 'Verbindung erfolgreich';
            $endpoints = [
                'authorization_endpoint' => $data['authorization_endpoint'],
                'token_endpoint'         => $data['token_endpoint'],
                'userinfo_endpoint'      => $data['userinfo_endpoint'],
                'end_session_endpoint'   => $data['end_session_endpoint'] ?? '(nicht vorhanden)',
            ];
        } else {
            $discoveryInfo = 'Ungültige Antwort – kein authorization_endpoint gefunden';
        }
    }
}
$checks[] = [
    'label' => 'Authentik OIDC Discovery',
    'ok'    => $discoveryOk,
    'info'  => $discoveryInfo,
];

// 8. Callback URL konfiguriert
$callbackUrl = APP_URL . '/callback.php';
$checks[] = [
    'label' => 'Callback URL (muss in Authentik eingetragen sein)',
    'ok'    => str_starts_with(APP_URL, 'https://'),
    'info'  => $callbackUrl,
];

$allOk = array_reduce($checks, fn($c, $i) => $c && $i['ok'], true);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redirect Manager – Diagnose</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0e0f11;--surface:#16181c;--border:#2a2d35;--accent:#4ade80;--accent2:#22d3ee;--muted:#5a6070;--text:#e2e4e9;--danger:#f87171;--warn:#fbbf24;--lock:#a78bfa;--radius:6px}
body{background:var(--bg);color:var(--text);font-family:'Courier New',monospace;padding:40px 16px;max-width:720px;margin:0 auto}
h1{color:var(--accent);font-size:1.05rem;margin-bottom:6px}
.subtitle{color:var(--muted);font-size:.75rem;margin-bottom:32px;line-height:1.6}
.subtitle code{color:var(--accent2);background:rgba(34,211,238,.08);padding:1px 6px;border-radius:3px}
.check{display:flex;align-items:flex-start;gap:14px;padding:12px 16px;border-radius:var(--radius);margin-bottom:8px;background:var(--surface);border:1px solid var(--border)}
.check.ok  {border-color:rgba(74,222,128,.25);background:rgba(74,222,128,.03)}
.check.fail{border-color:rgba(248,113,113,.25);background:rgba(248,113,113,.03)}
.check.info-only{border-color:rgba(251,191,36,.2);background:rgba(251,191,36,.03)}
.icon{font-size:1rem;flex-shrink:0;margin-top:2px;font-style:normal}
.label{font-size:.8rem;font-weight:600;margin-bottom:3px}
.check.ok   .label{color:var(--accent)}
.check.fail .label{color:var(--danger)}
.check.info-only .label{color:var(--warn)}
.info{font-size:.72rem;color:var(--muted);word-break:break-all;line-height:1.5}
.endpoints{margin-top:10px;padding:10px 12px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--border)}
.endpoints div{font-size:.68rem;color:var(--muted);margin-bottom:4px}
.endpoints div:last-child{margin-bottom:0}
.endpoints span{color:var(--accent2)}
.summary{margin-top:22px;padding:13px 18px;border-radius:var(--radius);font-size:.82rem;font-weight:600}
.summary.ok  {background:rgba(74,222,128,.07);border:1px solid rgba(74,222,128,.3);color:var(--accent)}
.summary.fail{background:rgba(248,113,113,.07);border:1px solid rgba(248,113,113,.3);color:var(--danger)}
.warn-box{margin-top:18px;padding:11px 15px;border-radius:var(--radius);font-size:.73rem;color:var(--warn);background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.2);line-height:1.5}
.warn-box code{background:rgba(251,191,36,.1);padding:1px 5px;border-radius:3px}
.section{font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted);margin:24px 0 10px}
</style>
</head>
<body>

<h1>redirect/manager · Diagnose</h1>
<div class="subtitle">
    OIDC Issuer: <code><?= htmlspecialchars(OIDC_ISSUER) ?></code><br>
    App URL: <code><?= htmlspecialchars(APP_URL) ?></code>
</div>

<div class="section">System</div>
<?php foreach (array_slice($checks, 0, 4) as $c): ?>
<div class="check <?= $c['ok'] ? ($c['label']==='allow_url_fopen'?'info-only':'ok') : 'fail' ?>">
    <i class="icon"><?= $c['ok'] ? '✓' : '✗' ?></i>
    <div>
        <div class="label"><?= htmlspecialchars($c['label']) ?></div>
        <div class="info"><?= htmlspecialchars($c['info']) ?></div>
    </div>
</div>
<?php endforeach; ?>

<div class="section">Dateisystem</div>
<?php foreach (array_slice($checks, 4, 2) as $c): ?>
<div class="check <?= $c['ok'] ? 'ok' : 'fail' ?>">
    <i class="icon"><?= $c['ok'] ? '✓' : '✗' ?></i>
    <div>
        <div class="label"><?= htmlspecialchars($c['label']) ?></div>
        <div class="info"><?= htmlspecialchars($c['info']) ?></div>
    </div>
</div>
<?php endforeach; ?>

<div class="section">Authentik OIDC</div>
<?php foreach (array_slice($checks, 6) as $c): ?>
<div class="check <?= $c['ok'] ? 'ok' : 'fail' ?>">
    <i class="icon"><?= $c['ok'] ? '✓' : '✗' ?></i>
    <div>
        <div class="label"><?= htmlspecialchars($c['label']) ?></div>
        <div class="info"><?= htmlspecialchars($c['info']) ?></div>
        <?php if (!empty($endpoints) && $c['label'] === 'Authentik OIDC Discovery'): ?>
        <div class="endpoints">
            <?php foreach ($endpoints as $key => $val): ?>
            <div><span><?= htmlspecialchars($key) ?>:</span> <?= htmlspecialchars($val) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<div class="summary <?= $allOk ? 'ok' : 'fail' ?>">
    <?= $allOk
        ? '✓ Alle Checks bestanden – OIDC sollte funktionieren.'
        : '✗ Es gibt Probleme – siehe Details oben.' ?>
</div>

<div class="warn-box">
    ⚠ Nach dem Setup in <code>config.php</code> auf <code>define('ENABLE_CHECK', false)</code> setzen.<br>
    Die Seite ist dann automatisch gesperrt – ohne diese Datei zu löschen.
</div>

</body>
</html>
