<div class="card">
  <h1><?php echo $item ? 'Mandat bearbeiten' : 'Mandat neu'; ?></h1>

  <form method="post" action="<?php echo $item ? \App\Support\App::url('/mandates/' . (int)$item['id']) : \App\Support\App::url('/mandates'); ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <div class="row">
      <div>
        <label>sevdesk Contact ID</label>
        <input name="sevdesk_contact_id" value="<?php echo htmlspecialchars((string)($item['sevdesk_contact_id'] ?? '')); ?>" required>
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php $st = $item['status'] ?? 'active'; ?>
          <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>active</option>
          <option value="paused" <?php echo $st === 'paused' ? 'selected' : ''; ?>>paused</option>
          <option value="revoked" <?php echo $st === 'revoked' ? 'selected' : ''; ?>>revoked</option>
        </select>
      </div>
    </div>

    <label>Name</label>
    <input name="debtor_name" value="<?php echo htmlspecialchars((string)($item['debtor_name'] ?? '')); ?>" required>

    <div class="row">
      <div>
        <label>IBAN</label>
        <input name="debtor_iban" value="<?php echo htmlspecialchars((string)($item['debtor_iban'] ?? '')); ?>" required>
      </div>
      <div>
        <label>BIC optional</label>
        <input name="debtor_bic" value="<?php echo htmlspecialchars((string)($item['debtor_bic'] ?? '')); ?>">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Mandatsreferenz</label>
        <input name="mandate_reference" value="<?php echo htmlspecialchars((string)($item['mandate_reference'] ?? '')); ?>" required>
      </div>
      <div>
        <label>Mandatsdatum</label>
        <input name="mandate_date" type="date" value="<?php echo htmlspecialchars((string)($item['mandate_date'] ?? '')); ?>" required>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Scheme</label>
        <?php $scheme = $item['scheme'] ?? 'CORE'; ?>
        <select name="scheme">
          <option value="CORE" <?php echo $scheme === 'CORE' ? 'selected' : ''; ?>>CORE</option>
          <option value="B2B" <?php echo $scheme === 'B2B' ? 'selected' : ''; ?>>B2B</option>
        </select>
      </div>
      <div>
        <label>Sequenzmodus</label>
        <?php $seq = $item['sequence_mode'] ?? 'auto'; ?>
        <select name="sequence_mode">
          <option value="auto" <?php echo $seq === 'auto' ? 'selected' : ''; ?>>auto</option>
          <option value="manual" <?php echo $seq === 'manual' ? 'selected' : ''; ?>>manual</option>
        </select>
      </div>
    </div>

    <label>Notizen</label>
    <textarea name="notes"><?php echo htmlspecialchars((string)($item['notes'] ?? '')); ?></textarea>

    <label>Mandat PDF optional</label>
    <input type="file" name="mandate_pdf" accept="application/pdf">

    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit">Speichern</button>
      <a class="btn secondary" href="<?php echo \App\Support\App::url('/mandates'); ?>">Zurück</a>
    </div>
  </form>

  <?php if ($item): ?>
    <form method="post" action="<?php echo \App\Support\App::url('/mandates/' . (int)$item['id'] . '/revoke'); ?>" style="margin-top: 12px">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn danger" type="submit" onclick="return confirm('Mandat wirklich widerrufen?');">Widerrufen</button>
    </form>

    <form method="post" action="<?php echo \App\Support\App::url('/mandates/' . (int)$item['id'] . '/delete'); ?>" style="margin-top: 10px">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn danger" type="submit" onclick="return confirm('Mandat wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">Löschen</button>
    </form>
  <?php endif; ?>
</div>
