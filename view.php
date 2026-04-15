<?php if (!defined('RM_BOOT')) { http_response_code(403); exit('403 Forbidden'); } ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redirect Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
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
        <?php if ($userEmail): ?><div class="user-email"><?= htmlspecialchars($userEmail) ?></div><?php endif; ?>
      </div>
    </div>
    <a href="?logout=1" class="logout-btn">Ausloggen</a>
  </div>
</header>

<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── Add form ── -->
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
          <label>Kategorie</label>
          <div class="cd" id="cd-add">
            <input type="hidden" name="cat" id="cd-add-val" value="">
            <div class="cd-trigger" onclick="cdToggle('cd-add')">
              <span class="cd-val"><span class="cd-label">— keine —</span></span>
              <span class="cd-arrow">▾</span>
            </div>
            <div class="cd-menu">
              <div class="cd-option selected" onclick="cdSelect('cd-add','','','')">— keine —</div>
              <?php foreach ($categories as $slug => $cat): ?>
              <div class="cd-option" onclick="cdSelect('cd-add','<?= htmlspecialchars($slug) ?>','<?= htmlspecialchars($cat['label']) ?>','<?= htmlspecialchars($cat['color']) ?>')">
                <span class="cd-dot" style="background:<?= htmlspecialchars($cat['color']) ?>"></span>
                <?= htmlspecialchars($cat['label']) ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
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

<!-- ── Redirect list ── -->
<div class="card">
  <div class="card-title">
    Alle Weiterleitungen in .htaccess
    <?php if (!$htExists): ?>
      &nbsp;<span style="color:var(--danger)">(Datei nicht gefunden!)</span>
    <?php else: ?>
      &nbsp;· <?= count($redirects) ?> Einträge
      <?php if ($lockedCount > 0): ?>&nbsp;· <span style="color:var(--lock)">🔒 <?= $lockedCount ?> gesperrt</span><?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (empty($redirects)): ?>
    <div class="empty">Keine Redirect-Regeln gefunden<?= $activeCat ? ' in dieser Kategorie' : '' ?>.</div>
  <?php else: ?>

  <!-- Category filter -->
  <?php if (!empty($categories)): ?>
  <div class="cat-filter">
    <a href="?" class="cat-filter-btn <?= !$activeCat ? 'active' : '' ?>"
       style="<?= !$activeCat ? 'background:var(--muted);' : '' ?>">Alle</a>
    <?php foreach ($categories as $slug => $cat):
      $isActive = $activeCat === $slug;
      $cnt = count(array_filter($allRedirects, fn($r) => ($r['cat'] ?? '') === $slug));
      if ($cnt === 0) continue; ?>
    <a href="?cat=<?= urlencode($slug) ?>"
       class="cat-filter-btn <?= $isActive ? 'active' : '' ?>"
       style="<?= $isActive ? 'background:'.$cat['color'].';' : 'border-color:'.$cat['color'].';color:'.$cat['color'].';' ?>">
      <?= htmlspecialchars($cat['label']) ?> <span style="opacity:.6"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Bulk form -->
  <form method="POST" id="bulkForm">
    <input type="hidden" name="bulk_action" id="bulkAction" value="">
    <input type="hidden" name="bulk_code"   id="bulkCodeHidden" value="">
    <input type="hidden" name="bulk_to"     id="bulkToHidden" value="">
    <input type="hidden" name="bulk_cat"    id="bulkCatHidden" value="">
    <input type="hidden" name="lock_pw"     id="bulkLockPwHidden" value="">
    <div id="bulkSelectedContainer"></div>

    <div class="bulk-bar" id="bulkBar">
      <div class="bulk-bar-top">
        <span class="bulk-count" id="bulkCount">0 ausgewählt</span>
        <button class="bulk-deselect" type="button" onclick="deselectAll()">Auswahl aufheben</button>
      </div>
      <div class="bulk-bar-sections">
        <!-- Lock -->
        <div class="bulk-section">
          <div class="bulk-section-title">🔒 Sperren / Entsperren</div>
          <div class="bulk-section-actions">
            <input class="bulk-pw" type="password" id="bulkLockPw" placeholder="Lock-Passwort…" autocomplete="off">
            <button class="btn-bulk btn-bulk-lock" type="button" onclick="submitBulk('lock')">Sperren</button>
            <button class="btn-bulk btn-bulk-unlock" type="button" onclick="submitBulk('unlock')">Entsperren</button>
          </div>
        </div>
        <!-- Code -->
        <div class="bulk-section">
          <div class="bulk-section-title">⇢ Redirect-Code</div>
          <div class="bulk-section-actions">
            <div class="bulk-toggle">
              <input type="radio" name="bulk_code_ui" id="bc301" value="301">
              <label for="bc301">301</label>
              <input type="radio" name="bulk_code_ui" id="bc302" value="302">
              <label for="bc302">302</label>
            </div>
            <input class="bulk-pw" type="password" id="bulkLockPwCode" placeholder="Lock-Passwort…" autocomplete="off" style="display:none">
            <button class="btn-bulk btn-bulk-apply" type="button" onclick="submitBulk('set_code')">Anwenden</button>
          </div>
        </div>
        <!-- URL -->
        <div class="bulk-section">
          <div class="bulk-section-title">→ Ziel-URL</div>
          <div class="bulk-section-actions">
            <input class="bulk-input" type="text" id="bulkToInput" placeholder="https://neue-url.ch/…">
            <input class="bulk-pw" type="password" id="bulkLockPwTo" placeholder="Lock-Passwort…" autocomplete="off" style="display:none">
            <button class="btn-bulk btn-bulk-apply" type="button" onclick="submitBulk('set_to')">Anwenden</button>
          </div>
        </div>
        <!-- Category -->
        <?php if (!empty($categories)): ?>
        <div class="bulk-section">
          <div class="bulk-section-title">⊞ Kategorie</div>
          <div class="bulk-section-actions">
            <div class="cd" id="cd-bulk">
              <div class="cd-trigger" onclick="cdToggle('cd-bulk')">
                <span class="cd-val"><span class="cd-label">— keine —</span></span>
                <span class="cd-arrow">▾</span>
              </div>
              <div class="cd-menu">
                <div class="cd-option selected" onclick="cdBulkSelect('','')">— keine —</div>
                <?php foreach ($categories as $slug => $cat): ?>
                <div class="cd-option" onclick="cdBulkSelect('<?= htmlspecialchars($slug) ?>','<?= htmlspecialchars($cat['label']) ?>','<?= htmlspecialchars($cat['color']) ?>')">
                  <span class="cd-dot" style="background:<?= htmlspecialchars($cat['color']) ?>"></span>
                  <?= htmlspecialchars($cat['label']) ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <button class="btn-bulk btn-bulk-apply" type="button" onclick="submitBulk('set_cat')">Anwenden</button>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Table -->
  <?php
  $lockedIndices = [];
  foreach ($redirects as $i => $r) { if (isLocked($r)) $lockedIndices[] = $i; }
  ?>
  <script>const LOCKED_INDICES = <?= json_encode($lockedIndices) ?>;</script>

  <div class="table-wrap">
  <table id="redirectTable">
    <colgroup>
      <col style="width:34px">
      <col style="width:28px">
      <col style="width:88px">
      <col style="width:12%">
      <col style="width:18px">
      <col style="width:30%"><!-- td-to -->
      <col style="width:96px">
      <col style="width:11%">
      <col style="width:190px">
    </colgroup>
    <thead><tr>
      <th class="th-check"><input type="checkbox" id="selectAll" onchange="toggleAll(this)"></th>
      <th></th>
      <th class="sortable" data-col="code" onclick="sortTable('code')">Code / Typ<span class="sort-icon"></span></th>
      <th class="sortable" data-col="from" onclick="sortTable('from')">Von<span class="sort-icon"></span></th>
      <th class="th-arrow"></th>
      <th class="sortable" data-col="to" onclick="sortTable('to')">Zu<span class="sort-icon"></span></th>
      <th class="sortable th-cat" data-col="cat" onclick="sortTable('cat')">Kategorie<span class="sort-icon"></span></th>
      <th class="sortable th-comment" data-col="comment" onclick="sortTable('comment')">Kommentar<span class="sort-icon"></span></th>
      <th></th>
    </tr></thead>
    <tbody id="redirectTbody">
    <?php foreach ($redirects as $i => $r):
      $locked  = isLocked($r);
      $catSlug = $r['cat'] ?? '';
      $catInfo = $categories[$catSlug] ?? null; ?>
    <tr id="row-<?= $i ?>" class="<?= $locked ? 'row-locked' : '' ?>"
        data-code="<?= htmlspecialchars($r['code']) ?>"
        data-from="<?= htmlspecialchars($r['from']) ?>"
        data-to="<?= htmlspecialchars($r['to']) ?>"
        data-cat="<?= htmlspecialchars($catSlug) ?>"
        data-comment="<?= htmlspecialchars($r['comment']) ?>">

      <!-- View cells -->
      <td class="td-check view-cell">
        <input type="checkbox" name="selected[]" value="<?= $i ?>" class="row-check" onchange="updateBulkBar()">
      </td>
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
      <td class="td-from view-cell" title="<?= htmlspecialchars($r['from']) ?>"><?= htmlspecialchars($r['from']) ?></td>
      <td class="td-arrow view-cell">→</td>
      <td class="td-to view-cell" title="<?= htmlspecialchars($r['to']) ?>"><?= htmlspecialchars($r['to']) ?></td>
      <td class="td-cat view-cell">
        <?php if ($catInfo): ?>
        <span class="cat-badge" style="background:<?= htmlspecialchars($catInfo['color']) ?>22;border:1px solid <?= htmlspecialchars($catInfo['color']) ?>55;color:<?= htmlspecialchars($catInfo['color']) ?>">
          <?= htmlspecialchars($catInfo['label']) ?>
        </span>
        <?php endif; ?>
      </td>
      <td class="td-comment view-cell" title="<?= htmlspecialchars($r['comment']) ?>">
        <?= htmlspecialchars($r['comment']) ?>
        <?php if ($r['comment']): ?>
        <span class="tooltip"><?= htmlspecialchars($r['comment']) ?></span>
        <?php endif; ?>
      </td>
      <td class="td-actions view-cell">
        <div class="row-actions">
          <?php
          $fullUrl = $siteBaseUrl . $r['from'];
          ?>
          <button class="btn-icon" title="URL kopieren" onclick="copyUrl(<?= htmlspecialchars(json_encode($fullUrl)) ?>)">⎘</button>
          <button class="btn-icon" title="QR Code" onclick="showQr(<?= htmlspecialchars(json_encode($fullUrl)) ?>, <?= htmlspecialchars(json_encode($r['from'])) ?>)">▦</button>
          <?php if (!$locked): ?>
            <button class="btn-edit" onclick="startEdit(<?= $i ?>)">Bearbeiten</button>
            <form method="POST" onsubmit="return confirm('Löschen?')" style="margin:0">
              <input type="hidden" name="idx" value="<?= $i ?>">
              <button class="btn btn-danger" name="delete" value="1">✕</button>
            </form>
          <?php else: ?>
            <button class="btn-edit" onclick="startEdit(<?= $i ?>)" style="border-color:rgba(167,139,250,.3);color:var(--lock)">🔒 Bearbeiten</button>
            <button class="btn btn-danger" onclick="openDeleteModal(<?= $i ?>)">✕</button>
          <?php endif; ?>
        </div>
      </td>

      <!-- Edit cell -->
      <td class="edit-cell" colspan="9">
        <form method="POST" style="padding:6px 0">
          <input type="hidden" name="idx" value="<?= $i ?>">
          <?php if ($locked): ?>
          <div class="lock-pw-row">
            <span>🔒 Lock-Passwort:</span>
            <input class="inline-input lock-input" type="password" name="lock_pw" placeholder="Passwort…" required style="flex:1;max-width:260px">
          </div>
          <?php endif; ?>
          <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
            <select class="inline-select" name="code" style="width:100px;flex-shrink:0">
              <option value="301" <?= $r['code']==='301'?'selected':'' ?>>301</option>
              <option value="302" <?= $r['code']==='302'?'selected':'' ?>>302</option>
            </select>
            <input class="inline-input" type="text" name="from" value="<?= htmlspecialchars($r['from']) ?>" required style="flex:1">
            <span style="color:var(--muted);flex-shrink:0">→</span>
            <input class="inline-input" type="text" name="to" value="<?= htmlspecialchars($r['to']) ?>" required style="flex:2">
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <?php
            $editCatSlug  = $r['cat'] ?? '';
            $editCatLabel = $categories[$editCatSlug]['label'] ?? '— keine —';
            $editCatColor = $categories[$editCatSlug]['color'] ?? '';
            ?>
            <div class="cd compact" id="cd-edit-<?= $i ?>" style="flex-shrink:0">
              <input type="hidden" name="cat" id="cd-edit-<?= $i ?>-val" value="<?= htmlspecialchars($editCatSlug) ?>">
              <div class="cd-trigger" onclick="cdToggle('cd-edit-<?= $i ?>')">
                <span class="cd-val">
                  <?php if ($editCatColor): ?><span class="cd-dot" style="background:<?= htmlspecialchars($editCatColor) ?>"></span><?php endif; ?>
                  <span class="cd-label"><?= htmlspecialchars($editCatLabel) ?></span>
                </span>
                <span class="cd-arrow">▾</span>
              </div>
              <div class="cd-menu">
                <div class="cd-option <?= !$editCatSlug?'selected':'' ?>" onclick="cdSelect('cd-edit-<?= $i ?>','','','')">— keine —</div>
                <?php foreach ($categories as $slug => $cat): ?>
                <div class="cd-option <?= $editCatSlug===$slug?'selected':'' ?>" onclick="cdSelect('cd-edit-<?= $i ?>','<?= htmlspecialchars($slug) ?>','<?= htmlspecialchars($cat['label']) ?>','<?= htmlspecialchars($cat['color']) ?>')">
                  <span class="cd-dot" style="background:<?= htmlspecialchars($cat['color']) ?>"></span>
                  <?= htmlspecialchars($cat['label']) ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <input class="inline-input" type="text" name="comment" value="<?= htmlspecialchars($r['comment']) ?>" placeholder="Kommentar (optional)" style="flex:1">
            <button class="btn-save" name="edit" value="1">✓ Speichern</button>
            <button class="btn-cancel-edit" type="button" onclick="cancelEdit(<?= $i ?>)">Abbrechen</button>
          </div>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <!-- Mobile cards -->
  <div class="mobile-rows" id="mobileRows">
  <?php foreach ($redirects as $i => $r):
    $locked  = isLocked($r);
    $catSlug = $r['cat'] ?? '';
    $catInfo = $categories[$catSlug] ?? null; ?>
  <div class="m-card <?= $locked ? 'row-locked' : '' ?>">
    <div class="m-card-top">
      <input type="checkbox" name="selected[]" value="<?= $i ?>" class="row-check" onchange="updateBulkBar()">
      <span class="m-card-from"><?= htmlspecialchars($r['from']) ?></span>
      <span class="badge badge-<?= $r['code'] ?>"><?= $r['code'] ?></span>
      <span class="lock-icon <?= $locked?'locked':'' ?>" onclick="openLockModal(<?= $i ?>,<?= $locked?'true':'false' ?>)"><?= $locked?'🔒':'🔓' ?></span>
    </div>
    <div class="m-card-to"><?= htmlspecialchars($r['to']) ?></div>
    <div class="m-card-meta">
      <?php if ($catInfo): ?>
      <span class="cat-badge" style="background:<?= htmlspecialchars($catInfo['color']) ?>22;border:1px solid <?= htmlspecialchars($catInfo['color']) ?>55;color:<?= htmlspecialchars($catInfo['color']) ?>">
        <?= htmlspecialchars($catInfo['label']) ?>
      </span>
      <?php endif; ?>
      <?php if ($r['comment']): ?><span style="font-family:'IBM Plex Mono',monospace;font-size:.7rem;color:var(--muted)"><?= htmlspecialchars($r['comment']) ?></span><?php endif; ?>
    </div>
    <div class="m-card-actions">
      <?php if (!$locked): ?>
        <button class="btn-edit" onclick="startEdit(<?= $i ?>)">Bearbeiten</button>
        <form method="POST" onsubmit="return confirm('Löschen?')" style="margin:0">
          <input type="hidden" name="idx" value="<?= $i ?>">
          <button class="btn btn-danger" name="delete" value="1">✕ Löschen</button>
        </form>
      <?php else: ?>
        <button class="btn-edit" onclick="startEdit(<?= $i ?>)" style="border-color:rgba(167,139,250,.3);color:var(--lock)">🔒 Bearbeiten</button>
        <button class="btn btn-danger" onclick="openDeleteModal(<?= $i ?>)">✕ Löschen</button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>

<div class="footer-info">
  .htaccess: <?= htmlspecialchars(realpath(HTACCESS) ?: '(nicht gefunden: '.HTACCESS.')') ?>
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
      <div class="field"><label style="color:var(--lock)">Lock-Passwort</label>
        <input type="password" name="lock_pw" id="lockPwInput" autocomplete="off" required></div>
      <div class="modal-actions">
        <button class="btn-lock-confirm" type="submit" id="lockConfirmBtn">Sperren</button>
        <button class="btn-modal-cancel" type="button" onclick="closeLockModal()">Abbrechen</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal">
    <h2 style="color:var(--danger)">Gesperrten Eintrag löschen</h2>
    <p>Dieser Eintrag ist gesperrt 🔒. Gib das Lock-Passwort ein, um ihn zu löschen.</p>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="idx" id="deleteIdx">
      <input type="hidden" name="delete" value="1">
      <div class="field"><label style="color:var(--lock)">Lock-Passwort</label>
        <input type="password" name="lock_pw" id="deletePwInput" autocomplete="off" required></div>
      <div class="modal-actions">
        <button class="btn-lock-confirm" type="submit" style="background:var(--danger)">Löschen</button>
        <button class="btn-modal-cancel" type="button" onclick="closeDeleteModal()">Abbrechen</button>
      </div>
    </form>
  </div>
</div>

<!-- QR Modal -->
<div class="modal-overlay" id="qrModal">
  <div class="modal" style="border-color:var(--accent2);max-width:340px;text-align:center">
    <h2 style="color:var(--accent2);margin-bottom:4px" id="qrTitle">QR Code</h2>
    <p id="qrUrl" style="color:var(--muted);margin-bottom:16px;word-break:break-all;font-size:.68rem"></p>
    <div id="qrCanvas" style="display:flex;justify-content:center;margin-bottom:16px"></div>
    <div style="display:flex;gap:8px;justify-content:center">
      <button class="btn-lock-confirm" style="background:var(--accent2)" onclick="downloadQr()">⬇ Download</button>
      <button class="btn-modal-cancel" onclick="closeQrModal()">Schliessen</button>
    </div>
  </div>
</div>

<!-- Copy toast -->
<div id="copyToast" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--surface);border:1px solid var(--accent);border-radius:var(--radius);padding:8px 18px;font-family:'IBM Plex Mono',monospace;font-size:.75rem;color:var(--accent);z-index:200;box-shadow:0 4px 16px rgba(0,0,0,.4);white-space:nowrap;pointer-events:none">✓ URL kopiert</div>

<!-- QR library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
// ── Edit ──────────────────────────────────────────────
function startEdit(i) {
  document.getElementById('row-'+i).classList.add('editing');
  const f = document.querySelector('#row-'+i+' .inline-input');
  if (f) f.focus();
}
function cancelEdit(i) { document.getElementById('row-'+i).classList.remove('editing'); }

// ── Lock modal ────────────────────────────────────────
function openLockModal(idx, isLocked) {
  document.getElementById('lockIdx').value = idx;
  document.getElementById('lockPwInput').value = '';
  const t = document.getElementById('lockModalTitle');
  const b = document.getElementById('lockConfirmBtn');
  if (isLocked) { t.textContent='Eintrag entsperren 🔓'; b.textContent='Entsperren'; b.style.background='var(--accent2)'; }
  else          { t.textContent='Eintrag sperren 🔒';    b.textContent='Sperren';    b.style.background='var(--lock)'; }
  document.getElementById('lockModal').classList.add('active');
  setTimeout(()=>document.getElementById('lockPwInput').focus(), 50);
}
function closeLockModal() { document.getElementById('lockModal').classList.remove('active'); }

// ── Delete modal ──────────────────────────────────────
function openDeleteModal(idx) {
  document.getElementById('deleteIdx').value = idx;
  document.getElementById('deletePwInput').value = '';
  document.getElementById('deleteModal').classList.add('active');
  setTimeout(()=>document.getElementById('deletePwInput').focus(), 50);
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }

// ── Bulk ──────────────────────────────────────────────
function getSelectedIndices() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(cb=>parseInt(cb.value));
}
function selectedHasLocked() {
  return getSelectedIndices().some(i=>LOCKED_INDICES.includes(i));
}
function toggleAll(master) {
  document.querySelectorAll('.row-check').forEach(cb=>cb.checked=master.checked);
  updateBulkBar();
}
function deselectAll() {
  document.querySelectorAll('.row-check').forEach(cb=>cb.checked=false);
  const sa=document.getElementById('selectAll'); if(sa) sa.checked=false;
  updateBulkBar();
}
function updateBulkBar() {
  const checked=document.querySelectorAll('.row-check:checked');
  const total=document.querySelectorAll('.row-check').length;
  const hasLocked=selectedHasLocked();
  const bar=document.getElementById('bulkBar');
  const master=document.getElementById('selectAll');
  document.getElementById('bulkCount').textContent=checked.length+' ausgewählt'+(hasLocked?' (davon gesperrte 🔒)':'');
  bar.classList.toggle('active', checked.length>0);
  if (master) { master.indeterminate=checked.length>0&&checked.length<total; master.checked=checked.length===total&&total>0; }
  document.getElementById('bulkLockPwCode').style.display=hasLocked?'block':'none';
  document.getElementById('bulkLockPwTo').style.display=hasLocked?'block':'none';
}
function submitBulk(action) {
  const checked=document.querySelectorAll('.row-check:checked');
  if (!checked.length) { alert('Keine Einträge ausgewählt.'); return; }
  const hasLocked=selectedHasLocked();
  if (action==='lock'||action==='unlock') {
    const pw=document.getElementById('bulkLockPw').value;
    if (!pw) { alert('Bitte Lock-Passwort eingeben.'); document.getElementById('bulkLockPw').focus(); return; }
    document.getElementById('bulkLockPwHidden').value=pw;
    document.getElementById('bulkCodeHidden').value='';
    document.getElementById('bulkToHidden').value='';
  }
  if (action==='set_code') {
    const sel=document.querySelector('input[name="bulk_code_ui"]:checked');
    if (!sel) { alert('Bitte 301 oder 302 auswählen.'); return; }
    if (hasLocked) { const pw=document.getElementById('bulkLockPwCode').value; if(!pw){alert('Lock-Passwort für gesperrte Einträge eingeben.');document.getElementById('bulkLockPwCode').focus();return;} document.getElementById('bulkLockPwHidden').value=pw; }
    else document.getElementById('bulkLockPwHidden').value='';
    document.getElementById('bulkCodeHidden').value=sel.value;
    document.getElementById('bulkToHidden').value='';
  }
  if (action==='set_to') {
    const to=document.getElementById('bulkToInput').value.trim();
    if (!to) { alert('Bitte Ziel-URL eingeben.'); document.getElementById('bulkToInput').focus(); return; }
    if (hasLocked) { const pw=document.getElementById('bulkLockPwTo').value; if(!pw){alert('Lock-Passwort für gesperrte Einträge eingeben.');document.getElementById('bulkLockPwTo').focus();return;} document.getElementById('bulkLockPwHidden').value=pw; }
    else document.getElementById('bulkLockPwHidden').value='';
    document.getElementById('bulkToHidden').value=to;
    document.getElementById('bulkCodeHidden').value='';
  }
  if (action==='set_cat') {
    document.getElementById('bulkCodeHidden').value='';
    document.getElementById('bulkToHidden').value='';
    document.getElementById('bulkLockPwHidden').value='';
  }
  const container=document.getElementById('bulkSelectedContainer');
  container.innerHTML='';
  checked.forEach(cb=>{ const inp=document.createElement('input'); inp.type='hidden'; inp.name='selected[]'; inp.value=cb.value; container.appendChild(inp); });
  document.getElementById('bulkAction').value=action;
  document.getElementById('bulkForm').submit();
}

// ── Custom Dropdown ───────────────────────────────────
let _cdOpen=null;
function cdToggle(id) {
  const el=document.getElementById(id);
  if(_cdOpen&&_cdOpen!==el) _cdOpen.classList.remove('open');
  el.classList.toggle('open');
  _cdOpen=el.classList.contains('open')?el:null;
}
function cdSelect(id,slug,label,color) {
  const el=document.getElementById(id);
  const inp=document.getElementById(id+'-val');
  if(inp) inp.value=slug;
  const val=el.querySelector('.cd-val');
  val.innerHTML=slug?`<span class="cd-dot" style="background:${color}"></span><span class="cd-label">${label}</span>`:`<span class="cd-label">— keine —</span>`;
  el.querySelectorAll('.cd-option').forEach(o=>o.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  el.classList.remove('open'); _cdOpen=null;
}
function cdBulkSelect(slug,label,color) {
  document.getElementById('bulkCatHidden').value=slug;
  const el=document.getElementById('cd-bulk');
  const val=el.querySelector('.cd-val');
  val.innerHTML=slug?`<span class="cd-dot" style="background:${color}"></span><span class="cd-label">${label}</span>`:`<span class="cd-label">— keine —</span>`;
  el.querySelectorAll('.cd-option').forEach(o=>o.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  el.classList.remove('open'); _cdOpen=null;
}
document.addEventListener('click', e=>{ if(_cdOpen&&!_cdOpen.contains(e.target)){_cdOpen.classList.remove('open');_cdOpen=null;} });

// ── Sort ──────────────────────────────────────────────
let sortCol=null, sortDir=1;
function sortTable(col) {
  const tbody=document.getElementById('redirectTbody');
  const rows=Array.from(tbody.querySelectorAll('tr'));
  if(sortCol===col) sortDir*=-1; else { sortCol=col; sortDir=1; }
  rows.sort((a,b)=>{ const av=(a.dataset[col]||'').toLowerCase(); const bv=(b.dataset[col]||'').toLowerCase(); return av<bv?-sortDir:av>bv?sortDir:0; });
  rows.forEach(r=>tbody.appendChild(r));
  document.querySelectorAll('thead th.sortable').forEach(th=>{ th.classList.remove('asc','desc'); if(th.dataset.col===col) th.classList.add(sortDir===1?'asc':'desc'); });
}

// ── Modals close ──────────────────────────────────────
// ── Copy URL ──────────────────────────────────────────
function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => showToast()).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = url; ta.style.position='fixed'; ta.style.opacity='0';
    document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    showToast();
  });
}
function showToast() {
  const t = document.getElementById('copyToast');
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2000);
}

// ── QR Code ───────────────────────────────────────────
function showQr(url, path) {
  document.getElementById('qrTitle').textContent = path;
  document.getElementById('qrUrl').textContent   = url;
  const canvas = document.getElementById('qrCanvas');
  canvas.innerHTML = '';
  new QRCode(canvas, {
    text: url, width: 220, height: 220,
    colorDark: '#e2e4e9', colorLight: '#16181c',
    correctLevel: QRCode.CorrectLevel.H,
  });
  document.getElementById('qrModal').classList.add('active');
}
function closeQrModal() { document.getElementById('qrModal').classList.remove('active'); }
function downloadQr() {
  const canvas = document.querySelector('#qrCanvas canvas');
  if (!canvas) return;
  const path = document.getElementById('qrTitle').textContent.replace(/[^a-z0-9]/gi,'-');
  const a = document.createElement('a');
  a.href = canvas.toDataURL('image/png'); a.download = 'qr-' + path + '.png'; a.click();
}
document.getElementById('qrModal').addEventListener('click', e=>{ if(e.target===e.currentTarget) closeQrModal(); });

document.getElementById('lockModal').addEventListener('click', e=>{ if(e.target===e.currentTarget) closeLockModal(); });
document.getElementById('deleteModal').addEventListener('click', e=>{ if(e.target===e.currentTarget) closeDeleteModal(); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape'){closeLockModal();closeDeleteModal();closeQrModal();} });
</script>
</body>
</html>
