<?php
if (!defined('RM_BOOT')) { http_response_code(403); exit('403 Forbidden'); }
// ═══════════════════════════════════════════════════════════════════
//  oidc.php – Authentik OIDC Authentifizierung
// ═══════════════════════════════════════════════════════════════════

// ── cURL Wrapper ─────────────────────────────────────────────────

function httpGet(string $url, array $headers = []): string {
    if (!function_exists('curl_init')) {
        die('cURL ist auf diesem Server nicht verfügbar.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'RedirectManager/1.0',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err)         throw new RuntimeException('cURL GET Fehler: ' . $err);
    if ($http >= 400) throw new RuntimeException('HTTP ' . $http . ' bei GET ' . $url);
    return (string)$body;
}

function httpPost(string $url, array $fields, array $headers = []): string {
    if (!function_exists('curl_init')) {
        die('cURL ist auf diesem Server nicht verfügbar.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => array_merge(
            ['Content-Type: application/x-www-form-urlencoded'],
            $headers
        ),
        CURLOPT_USERAGENT      => 'RedirectManager/1.0',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err)         throw new RuntimeException('cURL POST Fehler: ' . $err);
    if ($http >= 400) throw new RuntimeException('HTTP ' . $http . ' bei POST ' . $url . ' – ' . $body);
    return (string)$body;
}

// ── OIDC Funktionen ───────────────────────────────────────────────

function oidcDiscover(): array {
    static $cache = null;
    if ($cache) return $cache;
    $url = rtrim(OIDC_ISSUER, '/') . '/.well-known/openid-configuration';
    try {
        $json = httpGet($url);
    } catch (RuntimeException $e) {
        die('<b>Authentik nicht erreichbar</b><br>URL: <code>' . htmlspecialchars($url)
            . '</code><br>Fehler: ' . htmlspecialchars($e->getMessage()));
    }
    $cache = json_decode($json, true);
    if (!$cache) die('Ungültige OIDC-Discovery-Antwort von Authentik.');
    return $cache;
}

function oidcBuildAuthUrl(string $state, string $nonce): string {
    $cfg = oidcDiscover();
    return $cfg['authorization_endpoint'] . '?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => OIDC_CLIENT_ID,
        'redirect_uri'  => APP_URL . '/callback.php',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'nonce'         => $nonce,
    ]);
}

function oidcExchangeCode(string $code): array {
    $cfg = oidcDiscover();
    try {
        $resp = httpPost($cfg['token_endpoint'], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => APP_URL . '/callback.php',
            'client_id'     => OIDC_CLIENT_ID,
            'client_secret' => OIDC_CLIENT_SECRET,
        ]);
    } catch (RuntimeException $e) {
        die('Token-Austausch fehlgeschlagen: ' . htmlspecialchars($e->getMessage()));
    }
    return json_decode($resp, true) ?? [];
}

function oidcGetUserInfo(string $accessToken): array {
    $cfg = oidcDiscover();
    try {
        $resp = httpGet($cfg['userinfo_endpoint'], ['Authorization: Bearer ' . $accessToken]);
    } catch (RuntimeException $e) {
        return [];
    }
    return json_decode($resp, true) ?? [];
}

function oidcDecodeJwt(string $jwt): array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return [];
    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    return json_decode($payload, true) ?? [];
}

// ── Auth-Flow ─────────────────────────────────────────────────────

/**
 * Prüft Session, verarbeitet Callback, leitet zu Authentik weiter.
 * Gibt true zurück wenn der User eingeloggt ist und die App laden soll.
 */
function authHandle(): bool {
    session_name(SESSION_NAME);
    session_start();

    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $isCallback  = str_ends_with($requestPath, 'callback.php');

    // Logout
    if (isset($_GET['logout']) || isset($_POST['logout'])) {
        $idToken = $_SESSION['id_token'] ?? '';
        session_destroy();
        $cfg = oidcDiscover();
        if (!empty($cfg['end_session_endpoint']) && $idToken) {
            header('Location: ' . $cfg['end_session_endpoint'] . '?' . http_build_query([
                'id_token_hint'            => $idToken,
                'post_logout_redirect_uri' => APP_URL . '/',
            ]));
        } else {
            header('Location: ' . APP_URL . '/');
        }
        exit;
    }

    // OIDC Callback
    if ($isCallback) {
        $error = $_GET['error'] ?? '';
        if ($error) {
            die('Authentik Fehler: ' . htmlspecialchars($error . ' – ' . ($_GET['error_description'] ?? '')));
        }
        $state = $_GET['state'] ?? '';
        $code  = $_GET['code']  ?? '';
        if (!$state || !$code) die('Ungültiger Callback.');
        if ($state !== ($_SESSION['oidc_state'] ?? '')) die('State-Mismatch – möglicher CSRF-Angriff.');

        $tokens    = oidcExchangeCode($code);
        if (empty($tokens['access_token'])) {
            die('Token-Austausch fehlgeschlagen: ' . htmlspecialchars(json_encode($tokens)));
        }
        $idPayload = oidcDecodeJwt($tokens['id_token'] ?? '');
        if (($idPayload['nonce'] ?? '') !== ($_SESSION['oidc_nonce'] ?? '')) {
            die('Nonce-Mismatch – Sicherheitsfehler.');
        }
        $userInfo = oidcGetUserInfo($tokens['access_token']);

        $_SESSION['auth']       = true;
        $_SESSION['user_name']  = $userInfo['name'] ?? $userInfo['preferred_username'] ?? 'Unbekannt';
        $_SESSION['user_email'] = $userInfo['email'] ?? '';
        $_SESSION['user_sub']   = $idPayload['sub']  ?? '';
        $_SESSION['id_token']   = $tokens['id_token'] ?? '';
        $_SESSION['token_exp']  = $idPayload['exp']   ?? (time() + 3600);
        unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);

        header('Location: ' . APP_URL . '/');
        exit;
    }

    // Token abgelaufen
    if (!empty($_SESSION['auth']) && time() > ($_SESSION['token_exp'] ?? 0)) {
        session_destroy();
        session_name(SESSION_NAME);
        session_start();
    }

    // Nicht eingeloggt → zu Authentik
    if (empty($_SESSION['auth'])) {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_nonce'] = $nonce;
        header('Location: ' . oidcBuildAuthUrl($state, $nonce));
        exit;
    }

    return true;
}
