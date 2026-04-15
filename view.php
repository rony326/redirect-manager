<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redirect Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --bg:#0e0f11;--surface:#16181c;--border:#2a2d35;
    --accent:#4ade80;--accent2:#22d3ee;--muted:#5a6070;
    --text:#e2e4e9;--danger:#f87171;--warn:#fbbf24;--lock:#a78bfa;--radius:6px;
}
body{background:var(--bg);color:var(--text);font-family:'IBM Plex Sans',sans-serif;font-weight:300;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:40px 16px 80px}
header{width:100%;max-width:960px;display:flex;align-items:center;justify-content:space-between;margin-bottom:36px;border-bottom:1px solid var(--border);padding-bottom:18px;gap:16px}
header h1{font-family:'IBM Plex Mono',monospace;font-size:1.05rem;font-weight:600;letter-spacing:.05em;color:var(--accent);flex-shrink:0}
header h1 span{color:var(--muted);font-weight:400}
.header-right{display:flex;align-items:center;gap:14px}
.user-pill{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);border-radius:999px;padding:5px 14px 5px 8px}
.user-avatar{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--accent2),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'IBM Plex Mono',monospace;font-size:.7rem;font-weight:600;color:#000;flex-shrink:0}
.user-name{font-family:'IBM Plex Mono',monospace;font-size:.72rem;color:var(--text)}
.user-email{font-family:'IBM Plex Mono',monospace;font-size:.62rem;color:var(--muted)}
.oidc-badge{display:flex;align-items:center;gap:5px;font-family:'IBM Plex Mono',monospace;font-size:.62rem;color:var(--accent2);background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.2);border-radius:999px;padding:2px 8px}
.logout-btn{background:none;border:1px solid var(--border);color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.73rem;padding:5px 12px;border-radius:var(--radius);cursor:pointer;transition:border-color .15s,color .15s;text-decoration:none;display:inline-block}
.logout-btn:hover{border-color:var(--danger);color:var(--danger)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);width:100%;max-width:960px;padding:22px 26px;margin-bottom:18px}
.card-title{font-family:'IBM Plex Mono',monospace;font-size:.66rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:16px}
label{display:block;font-family:'IBM Plex Mono',monospace;font-size:.66rem;letter-spacing:.1em;color:var(--muted);text-transform:uppercase;margin-bottom:5px}
input[type="text"],input[type="password"]{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);font-family:'IBM Plex Mono',monospace;font-size:.85rem;padding:8px 12px;border-radius:var(--radius);outline:none;transition:border-color .15s}
input:focus{border-color:var(--accent)}
.field{margin-bottom:13px}
.btn{font-family:'IBM Plex Mono',monospace;font-size:.78rem;font-weight:500;padding:8px 18px;border-radius:var(--radius);border:none;cursor:pointer;letter-spacing:.04em;transition:transform .1s}
.btn:active{transform:scale(.97)}
.btn-primary{background:var(--accent);color:#000}
.btn-danger{background:none;border:1px solid var(--border);color:var(--danger);padding:4px 10px;font-size:.71rem}
.btn-danger:hover{border-color:var(--danger)}
.add-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.add-row2{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;grid-column:1/-1}
.code-toggle{display:flex;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
.code-toggle input[type="radio"]{display:none}
.code-toggle label{padding:8px 20px;cursor:pointer;font-family:'IBM Plex Mono',monospace;font-size:.82rem;font-weight:500;color:var(--muted);background:var(--bg);letter-spacing:.05em;margin:0;border:none;transition:background .15s,color .15s;text-transform:none}
.code-toggle input[type="radio"]:checked+label{background:var(--accent);color:#000}
.code-toggle label:hover{color:var(--text)}
@media(max-width:640px){.add-grid{grid-template-columns:1fr}.add-row2{grid-template-columns:1fr}}
.hint{font-family:'IBM Plex Mono',monospace;font-size:.66rem;color:var(--muted);margin-top:4px}
table{width:100%;border-collapse:collapse;font-family:'IBM Plex Mono',monospace;font-size:.78rem}
thead th{text-align:left;padding:0 9px 9px;font-size:.61rem;letter-spacing:.13em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)}
tbody tr{border-bottom:1px solid var(--border);transition:background .1s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:rgba(255,255,255,.016)}
tbody tr.row-locked{background:rgba(167,139,250,.04)}
tbody tr.row-locked:hover{background:rgba(167,139,250,.07)}
td{padding:10px 9px;vertical-align:middle;word-break:break-all}
td.td-lock{width:28px;text-align:center}
td.td-code{width:90px;white-space:nowrap}
td.td-from{color:var(--accent2);width:16%}
td.td-arrow{color:var(--muted);width:18px;text-align:center}
td.td-to{color:var(--accent)}
td.td-comment{color:var(--muted);font-size:.7rem;width:14%}
td.td-actions{width:150px;text-align:right;white-space:nowrap}
.badge{display:inline-block;font-size:.61rem;padding:2px 6px;border-radius:999px;letter-spacing:.07em}
.badge-301{background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.2);color:var(--accent)}
.badge-302{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.2);color:var(--warn)}
.type-tag{display:inline-block;font-size:.56rem;padding:1px 5px;border-radius:3px;color:var(--muted);border:1px solid var(--border);margin-left:3px;letter-spacing:.04em}
.lock-icon{font-size:.9rem;cursor:pointer;opacity:.5;transition:opacity .15s}
.lock-icon:hover{opacity:1}
.lock-icon.locked{color:var(--lock);opacity:1}
tr.editing td.view-cell{display:none}
tr.editing td.edit-cell{display:table-cell}
td.edit-cell{display:none}
tr.editing{background:rgba(34,211,238,.03)!important}
.inline-input{background:var(--bg);border:1px solid var(--accent2);color:var(--text);font-family:'IBM Plex Mono',monospace;font-size:.76rem;padding:4px 8px;border-radius:var(--radius);outline:none;width:100%}
.inline-input:focus{border-color:var(--accent)}
.inline-input.lock-input{border-color:var(--lock)}
.inline-select{background:var(--bg);border:1px solid var(--accent2);color:var(--text);font-family:'IBM Plex Mono',monospace;font-size:.76rem;padding:4px 7px;border-radius:var(--radius);outline:none;width:100%}
.btn-edit{background:none;border:1px solid var(--border);color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.7rem;padding:4px 9px;border-radius:var(--radius);cursor:pointer;transition:border-color .15s,color .15s;white-space:nowrap}
.btn-edit:hover{border-color:var(--accent2);color:var(--accent2)}
.btn-save{background:var(--accent);border:none;color:#000;font-family:'IBM Plex Mono',monospace;font-size:.7rem;font-weight:600;padding:4px 10px;border-radius:var(--radius);cursor:pointer;white-space:nowrap}
.btn-cancel-edit{background:none;border:1px solid var(--border);color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.7rem;padding:4px 9px;border-radius:var(--radius);cursor:pointer;white-space:nowrap}
.btn-cancel-edit:hover{border-color:var(--danger);color:var(--danger)}
.row-actions{display:flex;gap:5px;justify-content:flex-end;align-items:center}
.lock-pw-row{display:flex;gap:8px;align-items:center;padding:8px 10px;background:rgba(167,139,250,.07);border:1px solid rgba(167,139,250,.25);border-radius:var(--radius);margin-bottom:8px}
.lock-pw-row span{font-family:'IBM Plex Mono',monospace;font-size:.72rem;color:var(--lock);flex-shrink:0}
.alert{width:100%;max-width:960px;padding:10px 15px;border-radius:var(--radius);font-family:'IBM Plex Mono',monospace;font-size:.76rem;margin-bottom:13px}
.alert-success{background:rgba(74,222,128,.07);border:1px solid rgba(74,222,128,.22);color:var(--accent)}
.alert-error{background:rgba(248,113,113,.07);border:1px solid rgba(248,113,113,.22);color:var(--danger)}
.empty{text-align:center;padding:34px 0;color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.78rem}
.footer-info{color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.65rem;text-align:center;margin-top:6px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:100;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:var(--surface);border:1px solid var(--lock);border-radius:10px;padding:28px 32px;width:100%;max-width:380px}
.modal h2{font-family:'IBM Plex Mono',monospace;font-size:.9rem;font-weight:600;color:var(--lock);margin-bottom:6px}
.modal p{font-family:'IBM Plex Mono',monospace;font-size:.72rem;color:var(--muted);margin-bottom:18px}
.modal-actions{display:flex;gap:8px;margin-top:14px}
.btn-lock-confirm{background:var(--lock);border:none;color:#000;font-family:'IBM Plex Mono',monospace;font-size:.78rem;font-weight:600;padding:8px 18px;border-radius:var(--radius);cursor:pointer}
.btn-modal-cancel{background:none;border:1px solid var(--border);color:var(--muted);font-family:'IBM Plex Mono',monospace;font-size:.78rem;padding:8px 14px;border-radius:var(--radius);cursor:pointer}
.btn-modal-cancel:hover{border-color:var(--danger);color:var(--danger)}
.modal input[type="password"]{border-color:var(--lock)}
.modal input[type="password"]:focus{border-color:var(--lock);box-shadow:0 0 0 2px rgba(167,139,250,.2)}
</style>
</head>
<body>

<header>
    <h1>redirect<span>/</span>manager</h1>
    <div class="header-right">
        <span class="oidc-badge">⬡ via Authentik</span>
        <div class="user-pill">
            <div class="user-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($userName, 0, 1))) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                <?php if ($userEmail): ?>
                    <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <a href="?logout=1" class="logout-btn">Ausloggen</a>
    </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Neue Weiterleitung -->
<div class="card">
    <div class="card-title">Neue Weiterleitung</div>
    <form method="POST">
        <div class="add-grid">
            <div>
                <label>Von (Pfad)</label>
                <input type="text" name="from" placeholder="/r/demo" required>
                <div class="hint">z.B. /r/demo</div>
            </div>
            <div>
                <label>Zu (Ziel-URL)</label>
                <input type="text" name="to" placeholder="https://example.com/ziel" required>
                <div class="hint">vollständige URL mit https://</div>
            </div>
            <div class="add-row2">
                <div>
                    <label>Kommentar <span style="font-size:.6rem;letter-spacing:0;text-transform:none">(optional)</span></label>
                    <input type="text" name="comment" placeholder="z.B. Osterbrunch Anmeldung">
                </div>
                <div>
                    <label>Typ</label>
                    <div class="code-toggle">
                        <input type="radio" name="code" id="c301" value="301" checked>
                        <label for="c301">301 Permanent</label>
                        <input type="radio" name="code" id="c302" value="302">
                        <label for="c302">302 Temporär</label>
                    </div>
                </div>
                <div>
                    <button class="btn btn-primary" name="add" value="1" style="white-space:nowrap">+ Hinzufügen</button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Weiterleitungs-Liste -->
<div class="card">
    <div class="card-title">
        Alle Weiterleitungen in .htaccess
        <?php if (!$htExists): ?>
            &nbsp;<span style="color:var(--danger)">(Datei nicht gefunden!)</span>
        <?php else: ?>
            &nbsp;· <?= count($redirects) ?> Einträge
            <?php if ($lockedCount > 0): ?>
                &nbsp;· <span style="color:var(--lock)">🔒 <?= $lockedCount ?> gesperrt</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (empty($redirects)): ?>
        <div class="empty">Keine Redirect-Regeln gefunden.</div>
    <?php else: ?>
    <table>
        <thead><tr>
            <th style="width:28px"></th>
            <th>Code / Typ</th>
            <th>Von</th>
            <th></th>
            <th>Zu</th>
            <th>Kommentar</th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($redirects as $i => $r):
            $locked = isLocked($r); ?>
        <tr id="row-<?= $i ?>" class="<?= $locked ? 'row-locked' : '' ?>">

            <!-- View -->
            <td class="td-lock view-cell">
                <span class="lock-icon <?= $locked ? 'locked' : '' ?>"
                      title="<?= $locked ? 'Entsperren' : 'Sperren' ?>"
                      onclick="openLockModal(<?= $i ?>, <?= $locked ? 'true' : 'false' ?>)"
                ><?= $locked ? '🔒' : '🔓' ?></span>
            </td>
            <td class="td-code view-cell">
                <span class="badge badge-<?= $r['code'] ?>"><?= $r['code'] ?></span>
                <span class="type-tag"><?= htmlspecialchars($r['type']) ?></span>
            </td>
            <td class="td-from view-cell"><?= htmlspecialchars($r['from']) ?></td>
            <td class="td-arrow view-cell">→</td>
            <td class="td-to view-cell"><?= htmlspecialchars($r['to']) ?></td>
            <td class="td-comment view-cell"><?= htmlspecialchars($r['comment']) ?></td>
            <td class="td-actions view-cell">
                <div class="row-actions">
                    <?php if (!$locked): ?>
                        <button class="btn-edit" onclick="startEdit(<?= $i ?>)">Bearbeiten</button>
                        <form method="POST" onsubmit="return confirm('Löschen?')" style="margin:0">
                            <input type="hidden" name="idx" value="<?= $i ?>">
                            <button class="btn btn-danger" name="delete" value="1">✕</button>
                        </form>
                    <?php else: ?>
                        <button class="btn-edit" onclick="startEdit(<?= $i ?>)"
                                style="border-color:rgba(167,139,250,.3);color:var(--lock)">🔒 Bearbeiten</button>
                        <button class="btn btn-danger" onclick="openDeleteModal(<?= $i ?>)">✕</button>
                    <?php endif; ?>
                </div>
            </td>

            <!-- Edit -->
            <td class="edit-cell" colspan="7">
                <form method="POST" style="padding:6px 0">
                    <input type="hidden" name="idx" value="<?= $i ?>">
                    <?php if ($locked): ?>
                    <div class="lock-pw-row">
                        <span>🔒 Lock-Passwort:</span>
                        <input class="inline-input lock-input" type="password" name="lock_pw"
                               placeholder="Passwort…" required style="flex:1;max-width:260px">
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                        <select class="inline-select" name="code" style="width:100px;flex-shrink:0">
                            <option value="301" <?= $r['code']==='301'?'selected':'' ?>>301</option>
                            <option value="302" <?= $r['code']==='302'?'selected':'' ?>>302</option>
                        </select>
                        <input class="inline-input" type="text" name="from"
                               value="<?= htmlspecialchars($r['from']) ?>" required style="flex:1">
                        <span style="color:var(--muted);flex-shrink:0">→</span>
                        <input class="inline-input" type="text" name="to"
                               value="<?= htmlspecialchars($r['to']) ?>" required style="flex:2">
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <input class="inline-input" type="text" name="comment"
                               value="<?= htmlspecialchars($r['comment']) ?>"
                               placeholder="Kommentar (optional)" style="flex:1">
                        <button class="btn-save" name="edit" value="1">✓ Speichern</button>
                        <button class="btn-cancel-edit" type="button" onclick="cancelEdit(<?= $i ?>)">Abbrechen</button>
                    </div>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="footer-info">
    .htaccess: <?= htmlspecialchars(realpath(HTACCESS) ?: '(nicht gefunden: ' . HTACCESS . ')') ?>
    <?php if (file_exists(HTACCESS_BACKUP)): ?>&nbsp;·&nbsp;Backup vorhanden ✓<?php endif; ?>
    &nbsp;·&nbsp;Angemeldet als <strong><?= htmlspecialchars($userName) ?></strong> via Authentik
</div>

<!-- Lock Modal -->
<div class="modal-overlay" id="lockModal">
    <div class="modal">
        <h2 id="lockModalTitle">Eintrag sperren 🔒</h2>
        <p id="lockModalDesc">Gesperrte Einträge können nur mit dem Lock-Passwort bearbeitet oder gelöscht werden.</p>
        <form method="POST" id="lockForm">
            <input type="hidden" name="idx" id="lockIdx">
            <input type="hidden" name="toggle_lock" value="1">
            <div class="field">
                <label style="color:var(--lock)">Lock-Passwort</label>
                <input type="password" name="lock_pw" id="lockPwInput" autocomplete="off" required>
            </div>
            <div class="modal-actions">
                <button class="btn-lock-confirm" type="submit" id="lockConfirmBtn">Sperren</button>
                <button class="btn-modal-cancel" type="button" onclick="closeLockModal()">Abbrechen</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete locked Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h2 style="color:var(--danger)">Gesperrten Eintrag löschen</h2>
        <p>Dieser Eintrag ist gesperrt 🔒. Gib das Lock-Passwort ein, um ihn zu löschen.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="idx" id="deleteIdx">
            <input type="hidden" name="delete" value="1">
            <div class="field">
                <label style="color:var(--lock)">Lock-Passwort</label>
                <input type="password" name="lock_pw" id="deletePwInput" autocomplete="off" required>
            </div>
            <div class="modal-actions">
                <button class="btn-lock-confirm" type="submit" style="background:var(--danger)">Löschen</button>
                <button class="btn-modal-cancel" type="button" onclick="closeDeleteModal()">Abbrechen</button>
            </div>
        </form>
    </div>
</div>

<script>
function startEdit(i) {
    document.getElementById('row-' + i).classList.add('editing');
    const f = document.querySelector('#row-' + i + ' .inline-input');
    if (f) f.focus();
}
function cancelEdit(i) {
    document.getElementById('row-' + i).classList.remove('editing');
}
function openLockModal(idx, isLocked) {
    document.getElementById('lockIdx').value = idx;
    document.getElementById('lockPwInput').value = '';
    const t = document.getElementById('lockModalTitle');
    const b = document.getElementById('lockConfirmBtn');
    if (isLocked) {
        t.textContent = 'Eintrag entsperren 🔓';
        b.textContent = 'Entsperren';
        b.style.background = 'var(--accent2)';
    } else {
        t.textContent = 'Eintrag sperren 🔒';
        b.textContent = 'Sperren';
        b.style.background = 'var(--lock)';
    }
    document.getElementById('lockModal').classList.add('active');
    setTimeout(() => document.getElementById('lockPwInput').focus(), 50);
}
function closeLockModal() { document.getElementById('lockModal').classList.remove('active'); }
function openDeleteModal(idx) {
    document.getElementById('deleteIdx').value = idx;
    document.getElementById('deletePwInput').value = '';
    document.getElementById('deleteModal').classList.add('active');
    setTimeout(() => document.getElementById('deletePwInput').focus(), 50);
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }
document.getElementById('lockModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeLockModal(); });
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDeleteModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeLockModal(); closeDeleteModal(); } });
</script>
</body>
</html>
