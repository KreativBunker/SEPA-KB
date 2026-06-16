<div class="card">
  <div class="topbar">
    <h1>Ratenzahlung</h1>
    <a class="btn inline" href="<?php echo \App\Support\App::url('/installments/create'); ?>">Neuer Ratenplan</a>
  </div>
  <p class="muted">Teilt einen Rechnungsbetrag in mehrere SEPA-Lastschriften auf. Fällige Raten werden über die Export-Maske eingezogen.</p>
</div>

<div class="card">
  <h2>Fällige Raten einziehen</h2>
  <p class="muted">Offene Raten bis zum Stichtag werden in einen SEPA-Export-Lauf übernommen. Aktuell fällig: <strong><?php echo (int)$dueCount; ?></strong></p>
  <form method="post" action="<?php echo \App\Support\App::url('/installments/queue-due'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <div class="row3">
      <div>
        <label>Stichtag (fällig bis)</label>
        <input type="date" name="cutoff_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
      </div>
      <div>
        <label>Ausführungstermin</label>
        <input type="date" name="collection_date" value="<?php echo htmlspecialchars($defaultCollectionDate); ?>">
      </div>
      <div style="align-self:end">
        <button class="btn" type="submit" <?php echo $dueCount === 0 ? 'disabled' : ''; ?>>Fällige Raten exportieren</button>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div class="topbar">
    <h2>Ratenpläne</h2>
  </div>
  <form method="get" action="<?php echo \App\Support\App::url('/installments'); ?>" class="row3" style="margin-bottom:12px">
    <div>
      <label>Suche</label>
      <input name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Kunde, Rechnung, Mandat">
    </div>
    <div>
      <label>Status</label>
      <select name="status">
        <option value="">Alle</option>
        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Aktiv</option>
        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Abgeschlossen</option>
        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Storniert</option>
      </select>
    </div>
    <div style="align-self:end">
      <button class="btn secondary" type="submit">Filtern</button>
    </div>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Quelle</th>
        <th>Rechnung</th>
        <th>Kunde</th>
        <th>Gesamt</th>
        <th>Raten</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($plans as $p): ?>
        <tr>
          <td><?php echo (int)$p['id']; ?></td>
          <td><?php echo ($p['source'] ?? '') === 'manual' ? 'Manuell' : 'Rechnung'; ?></td>
          <td><?php echo htmlspecialchars((string)($p['invoice_number'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars((string)($p['debtor_name'] ?? '')); ?></td>
          <td><?php echo htmlspecialchars(number_format((float)$p['total_amount'], 2, ',', '.')); ?> EUR</td>
          <td><?php echo (int)$p['rate_count']; ?>× / <?php echo (int)$p['interval_months']; ?> Mon.</td>
          <td><?php echo htmlspecialchars((string)$p['status']); ?></td>
          <td><a class="btn inline secondary" href="<?php echo \App\Support\App::url('/installments/' . (int)$p['id']); ?>">Öffnen</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($plans)): ?>
        <tr><td colspan="8" class="muted">Noch keine Ratenpläne.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
