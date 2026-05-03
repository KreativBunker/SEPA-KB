<?php
use App\Support\App;

$itemsList = $items ?? [];
$total = count($itemsList);
$countActive = $countPaused = $countRevoked = 0;
foreach ($itemsList as $row) {
    switch ((string)($row['status'] ?? '')) {
        case 'active':  $countActive++;  break;
        case 'paused':  $countPaused++;  break;
        case 'revoked': $countRevoked++; break;
    }
}

function mandateInitials(string $name): string {
    $name = trim($name);
    if ($name === '') { return '?'; }
    $parts = preg_split('/\s+/', $name) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return $first . $last;
}
?>
<div class="page-header">
  <div class="page-header-text">
    <h1>Mandate</h1>
    <p>Übersicht aller SEPA-Lastschriftmandate, Status und offenen Online-Links.</p>
  </div>
  <div class="actions">
    <a class="btn inline" href="<?php echo App::url('/mandates/create'); ?>">Mandat hinzufügen</a>
    <a class="btn inline" href="<?php echo App::url('/online-mandates/create'); ?>">Online Link erstellen</a>
    <a class="btn inline secondary" href="#import">Import</a>
  </div>
</div>

<div class="stat-row">
  <div class="stat-tile">
    <span class="stat-tile-label">Gesamt</span>
    <span class="stat-tile-value"><?php echo $total; ?></span>
  </div>
  <div class="stat-tile is-ok">
    <span class="stat-tile-label">Aktiv</span>
    <span class="stat-tile-value"><?php echo $countActive; ?></span>
  </div>
  <div class="stat-tile is-warn">
    <span class="stat-tile-label">Pausiert</span>
    <span class="stat-tile-value"><?php echo $countPaused; ?></span>
  </div>
  <div class="stat-tile is-err">
    <span class="stat-tile-label">Widerrufen</span>
    <span class="stat-tile-value"><?php echo $countRevoked; ?></span>
  </div>
</div>

<div class="card">
  <form method="get" action="<?php echo App::url('/mandates'); ?>" class="filter-bar">
    <div class="row">
      <div style="flex: 1;">
        <label>Suche</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>" placeholder="Name, IBAN, Mandatsreferenz">
      </div>
      <div style="min-width: 180px;">
        <label>Status</label>
        <?php $sf = (string)($statusFilter ?? ''); ?>
        <select name="status">
          <option value="" <?php echo $sf === '' ? 'selected' : ''; ?>>Alle</option>
          <option value="active" <?php echo $sf === 'active' ? 'selected' : ''; ?>>Aktiv</option>
          <option value="paused" <?php echo $sf === 'paused' ? 'selected' : ''; ?>>Pausiert</option>
          <option value="revoked" <?php echo $sf === 'revoked' ? 'selected' : ''; ?>>Widerrufen</option>
        </select>
      </div>
      <div style="display:flex; align-items:flex-end; gap: 8px;">
        <button class="btn" type="submit">Suchen</button>
        <a class="btn secondary" href="<?php echo App::url('/mandates'); ?>">Zurücksetzen</a>
      </div>
    </div>
  </form>

  <?php if ((int)($openLinksCount ?? 0) > 0): ?>
    <p class="muted" style="margin-top: 10px;">
      Es gibt <?php echo (int)$openLinksCount; ?> offene Online Links, die noch nicht unterschrieben wurden. <a href="#open-links">Anzeigen</a>
    </p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="table-wrap"><table class="mandates-table">
    <thead>
      <tr>
        <th>Quelle</th>
        <th>Kunde</th>
        <th>IBAN</th>
        <th>BIC</th>
        <th>Mandatsreferenz</th>
        <th>Mandatsdatum</th>
        <th>Status</th>
        <th>PDF</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($itemsList as $it): ?>
        <?php
          $status = (string)($it['status'] ?? '');
          $statusClass = $status === 'active' ? 'ok' : ($status === 'paused' ? 'warn' : 'err');
          $statusLabels = [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'revoked' => 'Widerrufen',
          ];
          $statusLabel = $statusLabels[$status] ?? $status;
          $srcLabel = (string)($it['source_label'] ?? '');
          $srcClass = ((string)($it['source'] ?? '') === 'online') ? 'primary' : 'secondary';
          $hasPdf = !empty($it['attachment_path']);
          $rowClass = $status === 'revoked' ? 'is-revoked' : '';
          $rowStyle = $status === 'revoked' ? 'background:#fff5f5; color:#7a1f1f;' : '';
          $rowTitle = '';
          if ($status === 'revoked') {
              $noteText = trim((string)($it['notes'] ?? ''));
              if ($noteText !== '') {
                  $rowTitle = $noteText;
              }
          }
          $debtor = (string)($it['debtor_name'] ?? '');
          $initials = mandateInitials($debtor);
        ?>
        <tr class="<?php echo $rowClass; ?>" style="<?php echo $rowStyle; ?>" <?php echo $rowTitle !== '' ? 'title="' . htmlspecialchars($rowTitle) . '"' : ''; ?>>
          <td><span class="pill <?php echo $srcClass; ?>"><?php echo htmlspecialchars($srcLabel); ?></span></td>
          <td>
            <div class="cust">
              <div class="cust-avatar"><?php echo htmlspecialchars($initials); ?></div>
              <div class="cust-body">
                <div class="cust-name"><?php echo htmlspecialchars($debtor); ?></div>
                <div class="cust-meta">Kontakt ID <?php echo (int)($it['sevdesk_contact_id'] ?? 0); ?></div>
              </div>
            </div>
          </td>
          <td class="mono iban"><?php echo htmlspecialchars((string)($it['debtor_iban'] ?? '')); ?></td>
          <td class="mono bic"><?php echo htmlspecialchars((string)($it['debtor_bic'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars((string)($it['mandate_reference'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($it['mandate_date'] ?? ''))); ?></td>
          <td><span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
          <td>
            <?php if ($hasPdf): ?>
              <a class="btn inline secondary" href="<?php echo App::url('/mandates/' . (int)$it['id'] . '/pdf'); ?>">PDF</a>
            <?php else: ?>
              <span class="muted">-</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="actions">
              <a class="btn inline secondary" href="<?php echo App::url('/mandates/' . (int)$it['id'] . '/edit'); ?>">Bearbeiten</a>
              <form method="post" action="<?php echo App::url('/mandates/' . (int)$it['id'] . '/delete'); ?>" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
                <button class="btn inline danger" type="submit" onclick="return confirm('Mandat wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">Löschen</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($itemsList)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <div class="empty-state-title">Keine Mandate gefunden</div>
            <div>Lege ein neues Mandat an oder passe die Suche an.</div>
          </div>
        </td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
</div>

<details class="card" id="open-links" <?php echo ((int)($openLinksCount ?? 0) > 0) ? '' : 'style="display:none;"'; ?>>
  <summary style="cursor:pointer;">
    Offene Online Links (<?php echo (int)($openLinksCount ?? 0); ?>)
  </summary>

  <div style="margin-top: 12px;">
    <table>
      <thead>
        <tr>
          <th>Kunde</th>
          <th>Mandatsreferenz</th>
          <th>Link</th>
          <th>Erstellt</th>
          <th>Aktion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($openLinks ?? []) as $ol): ?>
          <?php $publicUrl = App::url('/m/' . (string)$ol['token']); ?>
          <tr>
            <td><?php echo htmlspecialchars((string)($ol['contact_name'] ?? '')); ?><br><span class="muted">ID <?php echo (int)($ol['sevdesk_contact_id'] ?? 0); ?></span></td>
            <td><?php echo htmlspecialchars((string)($ol['mandate_reference'] ?? '')); ?></td>
            <td style="max-width: 340px;">
              <a href="<?php echo htmlspecialchars($publicUrl); ?>" target="_blank" rel="noopener">öffnen</a><br>
              <span class="muted" style="word-break: break-all;"><?php echo htmlspecialchars($publicUrl); ?></span>
            </td>
            <td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($ol['created_at'] ?? ''))); ?></td>
            <td>
              <form method="post" action="<?php echo App::url('/online-mandates/' . (int)$ol['id'] . '/revoke'); ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
                <button class="btn inline danger" type="submit" onclick="return confirm('Link wirklich deaktivieren?');">Deaktivieren</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($openLinks)): ?>
          <tr><td colspan="5" class="muted">Keine offenen Links</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</details>

<div class="card" id="import">
  <h2>Import</h2>

  <form method="post" action="<?php echo App::url('/mandates/import'); ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
    <div class="row">
      <div>
        <label>CSV Datei</label>
        <input type="file" name="csv" accept=".csv" required>
      </div>
      <div style="display:flex; align-items:flex-end; gap: 8px;">
        <button class="btn" type="submit">CSV importieren</button>
        <a class="btn secondary" href="<?php echo App::url('/mandates/export'); ?>">CSV exportieren</a>
      </div>
    </div>
  </form>

  <div style="height: 10px;"></div>

  <div class="topbar">
    <div>
      <h3 style="margin:0;">sevdesk Import</h3>
      <p class="muted" style="margin: 6px 0 0 0;">Lade Kontakte aus sevdesk, um Online Links mit Kontakt Auswahl zu nutzen.</p>
    </div>
    <div class="actions">
      <a class="btn inline" href="<?php echo App::url('/mandates/import-sevdesk'); ?>">sevdesk öffnen</a>
    </div>
  </div>
</div>
