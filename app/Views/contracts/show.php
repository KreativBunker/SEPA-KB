<?php
use App\Support\App;
$status = (string)($item['status'] ?? 'draft');
$statusClass = 'warn';
if ($status === 'signed') { $statusClass = 'ok'; }
elseif ($status === 'revoked') { $statusClass = 'err'; }
?>
<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
    <h1 style="margin:0;"><?php echo htmlspecialchars((string)($item['title'] ?? 'Vertrag')); ?></h1>
    <a class="btn secondary" href="<?php echo App::url('/contracts'); ?>">Zur Übersicht</a>
  </div>

  <p style="margin-top:8px;">
    Status: <span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
    <?php if ((int)($item['include_sepa'] ?? 0)): ?>
      <span class="pill ok" style="margin-left:6px;">SEPA</span>
    <?php endif; ?>
  </p>

  <table style="margin-top: 12px;">
    <tr><th style="width:220px;">Kontakt</th><td><?php echo htmlspecialchars((string)($item['contact_name'] ?? '')); ?><?php if ($item['sevdesk_contact_id'] ?? null): ?> (sevdesk ID <?php echo (int)$item['sevdesk_contact_id']; ?>)<?php endif; ?></td></tr>
    <?php if (!empty($item['contact_email'])): ?>
      <tr><th>E-Mail</th><td><?php echo htmlspecialchars((string)$item['contact_email']); ?></td></tr>
    <?php endif; ?>
    <tr><th>Signierlink</th><td><a href="<?php echo htmlspecialchars((string)$publicUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars((string)$publicUrl); ?></a></td></tr>
    <?php if (!empty($item['mandate_reference'])): ?>
      <tr><th>Mandatsreferenz</th><td><?php echo htmlspecialchars((string)$item['mandate_reference']); ?></td></tr>
    <?php endif; ?>
    <tr><th>Erstellt</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($item['created_at'] ?? ''))); ?></td></tr>
    <?php if ($status === 'signed'): ?>
      <tr><th>Unterschrieben von</th><td><?php echo htmlspecialchars((string)($item['signer_name'] ?? '')); ?></td></tr>
      <tr><th>Unterschrieben am</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($item['signed_at'] ?? ''))); ?></td></tr>
      <?php if (!empty($item['debtor_iban'])): ?>
        <tr><th>IBAN</th><td class="mono"><?php echo htmlspecialchars((string)$item['debtor_iban']); ?></td></tr>
      <?php endif; ?>
    <?php endif; ?>
  </table>

  <?php if ($status === 'open'): ?>
    <details style="margin-top:14px;">
      <summary style="cursor:pointer; font-weight:600;">Vertragstext anzeigen</summary>
      <div style="margin-top:8px; padding:12px; background:#f9fafb; border-radius:8px; font-size:14px; line-height:1.6;"><?php echo strip_tags((string)($item['body'] ?? ''), '<b><i><u><strong><em><h1><h2><h3><p><br><ul><ol><li><a>'); ?></div>
    </details>
  <?php endif; ?>

  <div class="actions" style="margin-top: 14px;">
    <?php if ($status === 'signed' && !empty($item['signature_path'])): ?>
      <a class="btn" href="<?php echo App::url('/contracts/' . (int)$item['id'] . '/pdf'); ?>">Vertrag (PDF)</a>
      <?php if ((int)($item['include_sepa'] ?? 0)): ?>
        <a class="btn" href="<?php echo App::url('/contracts/' . (int)$item['id'] . '/sepa-pdf'); ?>">SEPA-Mandat (PDF)</a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($status === 'open' || $status === 'draft'): ?>
      <a class="btn" href="<?php echo App::url('/contracts/' . (int)$item['id'] . '/edit'); ?>">Bearbeiten</a>
    <?php endif; ?>

    <?php if ($status === 'open'): ?>
      <form method="post" action="<?php echo App::url('/contracts/' . (int)$item['id'] . '/revoke'); ?>" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
        <button class="btn danger" type="submit" onclick="return confirm('Vertrag wirklich widerrufen?');">Widerrufen</button>
      </form>
    <?php endif; ?>

    <?php if ($status !== 'signed'): ?>
      <form method="post" action="<?php echo App::url('/contracts/' . (int)$item['id'] . '/delete'); ?>" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
        <button class="btn danger" type="submit" onclick="return confirm('Vertrag endgültig löschen?');">Löschen</button>
      </form>
    <?php endif; ?>
  </div>
</div>
