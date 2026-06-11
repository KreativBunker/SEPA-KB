<div class="card">
  <div class="topbar">
    <h1>Mahnwesen</h1>
    <form method="post" action="<?php echo \App\Support\App::url('/inkasso/load'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn inline" type="submit">sevdesk laden</button>
    </form>
  </div>
  <p class="muted">Überfällige offene Rechnungen (Status 200, ohne payDate, Fälligkeit überschritten) mit Mahnstufe aus sevdesk. Per Klick kannst du Rechnung und alle Mahnungen als PDF an dein Inkassobüro übergeben.</p>
  <?php if (empty($mailReady)): ?>
    <p><span class="pill warn">Hinweis</span> SMTP / Inkassobüro E-Mail sind noch nicht vollständig konfiguriert. <a href="<?php echo \App\Support\App::url('/settings'); ?>">Zu den Einstellungen</a></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Liste</h2>
  <form method="get" action="<?php echo \App\Support\App::url('/inkasso'); ?>" style="margin-top:10px">
    <div class="row">
      <div>
        <label>Suche</label>
        <input name="q" value="<?php echo htmlspecialchars((string)($q ?? '')); ?>" placeholder="Rechnungsnummer, Name, ID, Betrag">
      </div>
      <div style="display:flex; align-items:end; gap:10px">
        <button class="btn inline" type="submit">Suchen</button>
        <a class="btn inline secondary" href="<?php echo \App\Support\App::url('/inkasso'); ?>">Zurücksetzen</a>
      </div>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nummer</th>
          <th>Kunde</th>
          <th>Fällig</th>
          <th>Mahnstufe</th>
          <th>Letzte Mahnung</th>
          <th>Betrag</th>
          <th>Gesamtforderung</th>
          <th>Inkasso</th>
          <th>Aktion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td class="nowrap"><?php echo htmlspecialchars((string)$inv['invoiceNumber']); ?></td>
            <td><?php echo htmlspecialchars((string)$inv['contact_name']); ?></td>
            <td class="nowrap">
              <?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$inv['dueDate'])); ?>
              <?php if ((int)($inv['days_overdue'] ?? 0) > 0): ?>
                <span class="pill warn"><?php echo (int)$inv['days_overdue']; ?> Tage überfällig</span>
              <?php endif; ?>
            </td>
            <td>
              <?php $level = (int)($inv['dunning_level'] ?? 0); ?>
              <?php if ($level >= 2): ?>
                <span class="pill err"><?php echo $level - 1; ?>. Mahnung</span>
              <?php elseif ($level === 1): ?>
                <span class="pill warn">Zahlungserinnerung</span>
              <?php else: ?>
                <span class="pill">keine</span>
              <?php endif; ?>
            </td>
            <td class="nowrap"><?php echo !empty($inv['last_dunning_date']) ? htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$inv['last_dunning_date'])) : '-'; ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo htmlspecialchars(number_format((float)$inv['sumGross'], 2, ',', '.')); ?> <?php echo htmlspecialchars((string)($inv['currency'] ?? 'EUR')); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo htmlspecialchars(number_format((float)$inv['total_claim'], 2, ',', '.')); ?> <?php echo htmlspecialchars((string)($inv['currency'] ?? 'EUR')); ?></td>
            <td>
              <?php if (!empty($inv['handed_over'])): ?>
                <span class="pill ok">übergeben am <?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay(substr((string)$inv['handed_over_at'], 0, 10))); ?></span>
              <?php else: ?>
                <span class="pill">offen</span>
              <?php endif; ?>
            </td>
            <td class="nowrap">
              <?php if (!empty($inv['handed_over'])): ?>
                <form method="post" action="<?php echo \App\Support\App::url('/inkasso/' . (int)$inv['id'] . '/handover'); ?>" onsubmit="return confirm('Rechnung <?php echo htmlspecialchars((string)$inv['invoiceNumber'], ENT_QUOTES); ?> wurde bereits übergeben. Wirklich ERNEUT an das Inkassobüro senden?');" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="resend" value="1">
                  <button class="btn inline secondary" type="submit" <?php echo empty($mailReady) ? 'disabled' : ''; ?>>Erneut senden</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?php echo \App\Support\App::url('/inkasso/' . (int)$inv['id'] . '/handover'); ?>" onsubmit="return confirm('Rechnung <?php echo htmlspecialchars((string)$inv['invoiceNumber'], ENT_QUOTES); ?> inkl. aller Mahnungen an das Inkassobüro<?php echo !empty($inkassoEmail) ? ' (' . htmlspecialchars($inkassoEmail, ENT_QUOTES) . ')' : ''; ?> senden?');" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <button class="btn inline" type="submit" <?php echo empty($mailReady) ? 'disabled' : ''; ?>>An Inkasso übergeben</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($invoices)): ?>
          <tr><td colspan="9" class="muted">Keine Daten geladen. Klicke oben auf sevdesk laden.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
