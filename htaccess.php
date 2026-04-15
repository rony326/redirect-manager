<?php
if (!defined('RM_BOOT')) { http_response_code(403); exit('403 Forbidden'); }
// ═══════════════════════════════════════════════════════════════════
//  htaccess.php – .htaccess lesen und schreiben
// ═══════════════════════════════════════════════════════════════════

function isLocked(array $r): bool {
    return !empty($r['locked']);
}

function checkLockPw(string $pw): bool {
    return $pw === LOCK_PASSWORD;
}

/**
 * Liest alle Redirect-Regeln aus der .htaccess.
 * Unterstützt: Redirect, RedirectMatch, RewriteRule mit R=301/302
 */
function parseRedirects(): array {
    if (!file_exists(HTACCESS)) return [];

    $lines          = file(HTACCESS, FILE_IGNORE_NEW_LINES);
    $redirects      = [];
    $pendingComment = '';
    $pendingLocked  = false;

    foreach ($lines as $li => $line) {
        $t = trim($line);

        // Kommentare parsen
        if (preg_match('/^#\s*(.+)$/', $t, $cm)) {
            $c = trim($cm[1]);
            if ($c === '[locked]') {
                $pendingLocked = true;
            } elseif (
                !str_starts_with($c, 'SECTION') &&
                !str_starts_with($c, '---') &&
                !str_starts_with($c, 'Weiterleitungen')
            ) {
                $pendingComment = $c;
            } else {
                $pendingComment = '';
            }
            continue;
        }

        $base = [
            'comment'  => $pendingComment,
            'locked'   => $pendingLocked,
            'line_idx' => $li,
            'original' => $t,
        ];
        $pendingComment = '';
        $pendingLocked  = false;

        // Redirect 301/302 /pfad https://...
        if (preg_match('/^Redirect\s+(301|302)\s+(\S+)\s+(\S+)$/i', $t, $m)) {
            $redirects[] = array_merge($base, [
                'from' => $m[2], 'to' => $m[3], 'code' => $m[1], 'type' => 'redirect',
            ]);
            continue;
        }

        // RedirectMatch 301/302 pattern https://...
        if (preg_match('/^RedirectMatch\s+(301|302)\s+(\S+)\s+(\S+)$/i', $t, $m)) {
            $redirects[] = array_merge($base, [
                'from' => $m[2], 'to' => $m[3], 'code' => $m[1], 'type' => 'redirectmatch',
            ]);
            continue;
        }

        // RewriteRule ^pfad/?$ https://... [R=301,L]
        if (preg_match('/^RewriteRule\s+(\S+)\s+(\S+)\s+\[([^\]]*R=(301|302)[^\]]*)\]/i', $t, $m)) {
            $pattern   = $m[1];
            $cleanFrom = '/' . preg_replace(
                ['/^\^/', '/\/?\$$/', '/\(\?:[^)]+\)/', '/\(\.\*\)/', '/\\/'],
                ['', '', '*', '*', '/'],
                $pattern
            );
            $redirects[] = array_merge($base, [
                'from' => $cleanFrom, 'to' => $m[2], 'code' => $m[4],
                'type' => 'rewrite', 'pattern' => $pattern,
            ]);
            continue;
        }

        if ($t !== '') {
            $pendingComment = '';
            $pendingLocked  = false;
        }
    }

    return $redirects;
}

/**
 * Schreibt alle Redirects zurück in die .htaccess.
 * Alle anderen Zeilen (DEFLATE, RewriteEngine etc.) bleiben erhalten.
 */
function saveRedirects(array $redirects): void {
    if (file_exists(HTACCESS)) {
        copy(HTACCESS, HTACCESS_BACKUP);
        $lines = file(HTACCESS, FILE_IGNORE_NEW_LINES);
    } else {
        $lines = ['RewriteEngine On'];
    }

    // Zeilen ermitteln die entfernt werden (alte Redirects + ihre Kommentare)
    $remove = [];
    foreach ($lines as $li => $line) {
        $t = trim($line);
        $isRedirect =
            preg_match('/^Redirect\s+(301|302)\s+/i', $t) ||
            preg_match('/^RedirectMatch\s+(301|302)\s+/i', $t) ||
            preg_match('/^RewriteRule\s+\S+\s+\S+\s+\[.*R=(301|302)/i', $t);

        if ($isRedirect) {
            $remove[] = $li;
            // Bis zu 2 vorherige Kommentarzeilen ebenfalls entfernen
            for ($b = 1; $b <= 2; $b++) {
                $pi = $li - $b;
                if ($pi >= 0
                    && preg_match('/^#/', trim($lines[$pi] ?? ''))
                    && !str_contains($lines[$pi], 'SECTION')
                    && !str_contains($lines[$pi], '---')
                ) {
                    $remove[] = $pi;
                } else {
                    break;
                }
            }
        }

        if (preg_match('/^#\s*---\s*Weiterleitungen/i', $t)) {
            $remove[] = $li;
        }
    }
    $remove = array_unique($remove);

    // Bereinigte Zeilen
    $clean = [];
    foreach ($lines as $i => $line) {
        if (!in_array($i, $remove)) $clean[] = $line;
    }

    // Einfügeposition: nach dem letzten RewriteEngine On
    $insertAfter = -1;
    foreach ($clean as $i => $line) {
        if (preg_match('/^RewriteEngine\s+On/i', trim($line))) $insertAfter = $i;
    }

    // Neuen Redirect-Block bauen
    $newLines = [];
    if (!empty($redirects)) {
        $newLines[] = '# --- Weiterleitungen (verwaltet via Redirect Manager) ---';
        foreach ($redirects as $r) {
            if (!empty($r['locked']))  $newLines[] = '# [locked]';
            if (!empty($r['comment'])) $newLines[] = '# ' . $r['comment'];
            $type = $r['type'] ?? 'rewrite';
            if ($type === 'redirect') {
                $newLines[] = 'Redirect '      . $r['code'] . ' ' . $r['from'] . ' ' . $r['to'];
            } elseif ($type === 'redirectmatch') {
                $newLines[] = 'RedirectMatch ' . $r['code'] . ' ' . $r['from'] . ' ' . $r['to'];
            } else {
                $p = $r['pattern'] ?? ('^' . ltrim($r['from'], '/') . '/?$');
                $newLines[] = 'RewriteRule ' . $p . ' ' . $r['to'] . ' [R=' . $r['code'] . ',L]';
            }
        }
    }

    // Block einfügen
    if ($insertAfter >= 0) {
        array_splice($clean, $insertAfter + 1, 0, array_merge([''], $newLines));
    } else {
        $clean = array_merge($clean, [''], $newLines);
    }

    // Mehrfache Leerzeilen zusammenfassen
    $out    = [];
    $blanks = 0;
    foreach ($clean as $line) {
        if (trim($line) === '') {
            $blanks++;
            if ($blanks <= 2) $out[] = $line;
        } else {
            $blanks = 0;
            $out[]  = $line;
        }
    }

    file_put_contents(HTACCESS, implode("
", $out) . "
");
}
