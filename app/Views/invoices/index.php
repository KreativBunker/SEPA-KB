<div class="card">
  <div class="topbar">
    <h1>Rechnungen</h1>
    <form method="post" action="<?php echo \App\Support\App::url('/invoices/load'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>">
    <input type="hidden" name="pm" value="<?php echo htmlspecialchars((string)($pm ?? '')); ?>">
      <button class="btn inline" type="submit">sevdesk laden</button>
    </form>
  </div>
  <p class="muted">Es werden offene und unbezahlte Rechnungen angezeigt, Status 200 und ohne payDate.</p>
</div>

<div class="card">
  <h2>Liste</h2>
  <form method="get" action="<?php echo \App\Support\App::url('/invoices'); ?>" style="margin-top:10px">
    <div class="row">
      <div>
        <label>Suche</label>
        <input name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>" placeholder="Rechnungsnummer, Name, ID, Betrag">
      </div>

<div>
  <label>Zahlungsart</label>
  <select name="pm">
    <option value="">Alle</option>
        <?php foreach (($paymentMethods ?? []) as $pid => $pname): ?>
      <option value="<?php echo (int)$pid; ?>" <?php echo ((string)($pm ?? '') === (string)$pid) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$pname); ?></option>
    <?php endforeach; ?>
  </select>
</div>

      <div style="display:flex; align-items:end; gap:10px">
        <button class="btn inline" type="submit">Suchen</button>
        <a class="btn inline secondary" href="<?php echo \App\Support\App::url('/invoices'); ?>">Zurücksetzen</a>
      </div>
    </div>
  </form>
  <form method="post" action="<?php echo \App\Support\App::url('/invoices/select'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>">
    <input type="hidden" name="pm" value="<?php echo htmlspecialchars((string)($pm ?? '')); ?>">

    <table>
      <thead>
        <tr>
          <th></th>
          <th>ID</th>
          <th>Nummer</th>
          <th>Kunde</th>
          <th>Zahlungsart</th>
          <th>Betrag</th>
          <th>Fällig</th>
          <th>Status</th>
          <th>Mandat</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td>
              <input type="checkbox" name="invoice_ids[]" value="<?php echo (int)$inv['id']; ?>" <?php echo (!empty($inv['mandate_ok']) && empty($inv['completed'])) ? '' : 'disabled'; ?>>
            </td>
            <td><?php echo (int)$inv['id']; ?></td>
            <td><?php echo htmlspecialchars($inv['invoiceNumber']); ?></td>
            <td><?php echo htmlspecialchars($inv['contact_name']); ?></td>
            <td><?php echo htmlspecialchars((string)($inv['payment_method'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars(number_format((float)$inv['sumGross'], 2, ',', '.')); ?> EUR</td>
            <td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$inv['dueDate'])); ?></td>
            <td>
              <?php if (!empty($inv['completed'])): ?>
                <span class="pill ok">abgeschlossen</span>
              <?php else: ?>
                <span class="pill">offen</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($inv['mandate_ok'])): ?>
                <span class="pill ok">ok</span>
              <?php else: ?>
                <span class="pill err">fehlt</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($invoices)): ?>
          <tr><td colspan="9" class="muted">Keine Daten geladen. Klicke oben auf sevdesk laden.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div style="margin-top: 12px" class="actions">
      <button class="btn" type="submit">Zum Export</button>
    </div>
  </form>
</div>
