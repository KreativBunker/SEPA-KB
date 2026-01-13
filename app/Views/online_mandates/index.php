<?php
use App\Support\App;
?>
<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
    <h1 style="margin:0;">Online Mandate</h1>
    <a class="btn" href="<?php echo App::url('/online-mandates/create'); ?>">Neuen Link erstellen</a>
  </div>

  <p class="muted" style="margin-top:8px;">
    Hier erzeugst du Links, die Kunden ohne Login ausfüllen und unterschreiben können. Nach der Unterschrift wird ein PDF erzeugt und das Mandat wird im Mandate Bereich aktualisiert.
  </p>
</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Status</th>
        <th>Kunde</th>
        <th>Mandatsreferenz</th>
        <th>Link</th>
        <th>Erstellt</th>
        <th>Unterschrieben</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach (($items ?? []) as $it): ?>
      <?php
        $st = (string)($it['status'] ?? '');
        $pillClass = $st === 'signed' ? 'ok' : ($st === 'revoked' ? 'err' : 'warn');
        $publicUrl = App::url('/m/' . (string)$it['token']);
      ?>
      <tr>
        <td><span class="pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars($st); ?></span></td>
        <td><?php echo htmlspecialchars((string)$it['contact_name']); ?><br><span class="muted">ID <?php echo (int)$it['sevdesk_contact_id']; ?></span></td>
        <td><?php echo htmlspecialchars((string)$it['mandate_reference']); ?></td>
        <td style="max-width: 320px;">
          <a href="<?php echo htmlspecialchars($publicUrl); ?>" target="_blank" rel="noopener">öffnen</a><br>
          <span class="muted" style="word-break: break-all;"><?php echo htmlspecialchars($publicUrl); ?></span>
        </td>
        <td><?php echo htmlspecialchars((string)$it['created_at']); ?></td>
        <td><?php echo htmlspecialchars((string)($it['signed_at'] ?? '')); ?></td>
        <td>
          <a class="btn inline" href="<?php echo App::url('/online-mandates/' . (int)$it['id']); ?>">Details</a>
          <?php if (!empty($it['pdf_path']) && $st === 'signed'): ?>
            <a class="btn inline" href="<?php echo App::url('/online-mandates/' . (int)$it['id'] . '/pdf'); ?>">PDF</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
      <tr><td colspan="7" class="muted">Noch keine Links erstellt.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
