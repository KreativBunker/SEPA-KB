<?php
use App\Support\App;
?>
<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
    <h1 style="margin:0;">Online Mandat</h1>
    <a class="btn" href="<?php echo App::url('/online-mandates'); ?>">Zur Übersicht</a>
  </div>

  <p class="muted" style="margin-top:8px;">
    Status: <strong><?php echo htmlspecialchars((string)($item['status'] ?? '')); ?></strong>
  </p>

  <table style="margin-top: 12px;">
    <tr><th style="width:220px;">Kunde</th><td><?php echo htmlspecialchars((string)$item['contact_name']); ?> (ID <?php echo (int)$item['sevdesk_contact_id']; ?>)</td></tr>
    <tr><th>Mandatsreferenz</th><td><?php echo htmlspecialchars((string)$item['mandate_reference']); ?></td></tr>
    <tr><th>Link</th><td><a href="<?php echo htmlspecialchars((string)$publicUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars((string)$publicUrl); ?></a></td></tr>
    <tr><th>Erstellt</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$item['created_at'])); ?></td></tr>
    <tr><th>Unterschrieben</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($item['signed_at'] ?? ''))); ?></td></tr>
  </table>

  <?php if (!empty($item['pdf_path']) && (string)$item['status'] === 'signed'): ?>
    <div class="actions" style="margin-top: 12px;">
      <a class="btn" href="<?php echo App::url('/online-mandates/' . (int)$item['id'] . '/pdf'); ?>">PDF herunterladen</a>
      <a class="btn" style="background:#6b7280;" href="<?php echo App::url('/mandates'); ?>">Mandate öffnen</a>
    </div>
  <?php endif; ?>

  <?php if ((string)$item['status'] === 'open'): ?>
    <form method="post" action="<?php echo App::url('/online-mandates/' . (int)$item['id'] . '/revoke'); ?>" style="margin-top: 14px;">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
      <button class="btn danger" type="submit" onclick="return confirm('Link wirklich deaktivieren?');">Link deaktivieren</button>
    </form>
  <?php endif; ?>
</div>
