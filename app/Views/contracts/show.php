<?php
use App\Support\App;
$status = (string)($item['status'] ?? 'draft');
$statusClass = 'warn';
$statusLabel = ucfirst($status);
if ($status === 'signed') { $statusClass = 'ok'; $statusLabel = 'Unterschrieben'; }
elseif ($status === 'revoked') { $statusClass = 'err'; $statusLabel = 'Widerrufen'; }
elseif ($status === 'cancelled') { $statusClass = 'err'; $statusLabel = 'Gekündigt'; }
elseif ($status === 'open') { $statusLabel = 'Offen'; }
elseif ($status === 'draft') { $statusClass = ''; $statusLabel = 'Entwurf'; }
?>
<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
    <h1 style="margin:0;"><?php echo htmlspecialchars((string)($item['title'] ?? 'Vertrag')); ?></h1>
    <a class="btn secondary" href="<?php echo App::url('/contracts'); ?>">Zur Übersicht</a>
  </div>

  <p style="margin-top:8px;">
    Status: <span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
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
    <?php if ($status === 'signed' || $status === 'cancelled'): ?>
      <tr><th>Unterschrieben von</th><td><?php echo htmlspecialchars((string)($item['signer_name'] ?? '')); ?></td></tr>
      <tr><th>Unterschrieben am</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($item['signed_at'] ?? ''))); ?></td></tr>
      <?php if (!empty($item['debtor_iban'])): ?>
        <tr><th>IBAN</th><td class="mono"><?php echo htmlspecialchars((string)$item['debtor_iban']); ?></td></tr>
      <?php endif; ?>
    <?php endif; ?>
    <?php if ($status === 'cancelled'): ?>
      <tr><th>Gekündigt zum</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($item['cancellation_date'] ?? ''))); ?></td></tr>
      <tr><th>Kündigung erfasst</th><td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($item['cancelled_at'] ?? ''))); ?></td></tr>
      <?php if (!empty($item['cancellation_reason'])): ?>
        <tr><th>Begründung</th><td><?php echo nl2br(htmlspecialchars((string)$item['cancellation_reason'])); ?></td></tr>
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
    <?php if (($status === 'signed' || $status === 'cancelled') && !empty($item['signature_path'])): ?>
      <a class="btn" href="<?php echo App::url('/contracts/' . (int)$item['id'] . '/pdf'); ?>">Vertrag (PDF)</a>
      <?php if ((int)($item['include_sepa'] ?? 0)): ?>
        <a class="btn" href="<?php echo App::url('/contracts/' . (int)$item['id'] . '/sepa-pdf'); ?>">SEPA-Mandat (PDF)</a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($status === 'cancelled'): ?>
      <a class="btn" href="<?php echo App::url('/contracts/' . (int)$item['id'] . '/cancellation-pdf'); ?>">Kündigung (PDF)</a>
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

    <?php if ($status !== 'signed' && $status !== 'cancelled'): ?>
      <form method="post" action="<?php echo App::url('/contracts/' . (int)$item['id'] . '/delete'); ?>" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
        <button class="btn danger" type="submit" onclick="return confirm('Vertrag endgültig löschen?');">Löschen</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($status === 'signed'): ?>
    <details style="margin-top:18px; border-top:1px solid #e5e7eb; padding-top:14px;">
      <summary style="cursor:pointer; font-weight:600; color:#b91c1c;">Vertrag kündigen</summary>
      <form method="post" action="<?php echo App::url('/contracts/' . (int)$item['id'] . '/cancel'); ?>" style="margin-top:10px; max-width:520px;">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
        <div style="margin-bottom:10px;">
          <label for="cancellation_date" style="display:block; font-size:13px; margin-bottom:4px;">Kündigung wirksam zum</label>
          <input type="date" id="cancellation_date" name="cancellation_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
        </div>
        <div style="margin-bottom:10px;">
          <label for="cancellation_reason" style="display:block; font-size:13px; margin-bottom:4px;">Begründung (optional)</label>
          <textarea id="cancellation_reason" name="cancellation_reason" rows="3" style="width:100%;"></textarea>
        </div>
        <button class="btn danger" type="submit" onclick="return confirm('Vertrag jetzt kündigen? Eine Kündigungs-PDF wird erstellt.');">Kündigung erfassen</button>
      </form>
    </details>
  <?php endif; ?>
</div>
