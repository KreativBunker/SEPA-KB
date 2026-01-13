<?php
use App\Support\App;
?>
<div class="card">
  <div class="topbar">
    <h1 style="margin:0;">Mandate</h1>
    <div class="actions">
      <a class="btn inline" href="<?php echo App::url('/mandates/create'); ?>">Mandat hinzufügen</a>
      <a class="btn inline" href="<?php echo App::url('/online-mandates/create'); ?>">Online Link erstellen</a>
      <a class="btn inline secondary" href="#import">Import</a>
    </div>
  </div>

  <form method="get" action="<?php echo App::url('/mandates'); ?>" style="margin-top: 12px;">
    <div class="row">
      <div style="flex: 1;">
        <label>Suche</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>" placeholder="Name, IBAN, Mandatsreferenz">
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
  <div class="table-wrap"><table>
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
      <?php foreach (($items ?? []) as $it): ?>
        <?php
          $status = (string)($it['status'] ?? '');
          $statusClass = $status === 'active' ? 'ok' : ($status === 'paused' ? 'warn' : 'err');
          $srcLabel = (string)($it['source_label'] ?? '');
          $srcClass = ((string)($it['source'] ?? '') === 'online') ? 'ok' : 'secondary';
          $hasPdf = !empty($it['attachment_path']);
        ?>
        <tr>
          <td><span class="pill <?php echo $srcClass; ?>"><?php echo htmlspecialchars($srcLabel); ?></span></td>
          <td>
            <?php echo htmlspecialchars((string)($it['debtor_name'] ?? '')); ?><br>
            <span class="muted">Kontakt ID <?php echo (int)($it['sevdesk_contact_id'] ?? 0); ?></span>
          </td>
          <td class="mono iban"><?php echo htmlspecialchars((string)($it['debtor_iban'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars((string)($it['debtor_bic'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars((string)($it['mandate_reference'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars((string)($it['mandate_date'] ?? '')); ?></td>
          <td><span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
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
      <?php if (empty($items)): ?>
        <tr><td colspan="9" class="muted">Keine Daten</td></tr>
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
            <td><?php echo htmlspecialchars((string)($ol['created_at'] ?? '')); ?></td>
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
