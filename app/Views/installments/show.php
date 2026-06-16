<div class="card">
  <div class="topbar">
    <h1>Ratenplan #<?php echo (int)$plan['id']; ?></h1>
    <div class="actions">
      <a class="btn inline secondary" href="<?php echo \App\Support\App::url('/installments'); ?>">Zurück</a>
      <?php if (($plan['status'] ?? '') === 'active'): ?>
        <form method="post" action="<?php echo \App\Support\App::url('/installments/' . (int)$plan['id'] . '/cancel'); ?>" onsubmit="return confirm('Ratenplan stornieren? Offene Raten werden storniert.');">
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
          <button class="btn inline secondary" type="submit">Stornieren</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <p class="muted">
    Quelle: <?php echo ($plan['source'] ?? '') === 'manual' ? 'Manuell' : 'sevdesk-Rechnung'; ?>,
    Rechnung: <?php echo htmlspecialchars((string)($plan['invoice_number'] ?? '–')); ?>,
    Kunde: <?php echo htmlspecialchars((string)($plan['debtor_name'] ?? '')); ?>,
    Mandat: <?php echo htmlspecialchars((string)($plan['mandate_reference'] ?? '')); ?>,
    Status: <?php echo htmlspecialchars((string)$plan['status']); ?>
  </p>
  <p class="muted">
    Gesamt: <strong><?php echo htmlspecialchars(number_format((float)$plan['total_amount'], 2, ',', '.')); ?> EUR</strong>,
    Raten: <?php echo (int)$plan['rate_count']; ?> × alle <?php echo (int)$plan['interval_months']; ?> Monat(e)
  </p>
</div>

<div class="card">
  <h2>Raten</h2>
  <table>
    <thead>
      <tr>
        <th>Nr.</th>
        <th>Betrag</th>
        <th>Fällig</th>
        <th>Sequenz</th>
        <th>Status</th>
        <th>Lauf</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rates as $r): ?>
        <tr>
          <td><?php echo (int)$r['rate_no']; ?></td>
          <td><?php echo htmlspecialchars(number_format((float)$r['amount'], 2, ',', '.')); ?> EUR</td>
          <td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$r['due_date'])); ?></td>
          <td><?php echo htmlspecialchars((string)$r['sequence_type']); ?></td>
          <td><?php echo htmlspecialchars((string)$r['status']); ?></td>
          <td>
            <?php if (!empty($r['export_run_id'])): ?>
              <a href="<?php echo \App\Support\App::url('/exports/' . (int)$r['export_run_id']); ?>">#<?php echo (int)$r['export_run_id']; ?></a>
            <?php else: ?>
              <span class="muted">–</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (($r['status'] ?? '') === 'queued' || ($r['status'] ?? '') === 'collected'): ?>
              <form method="post" action="<?php echo \App\Support\App::url('/installments/' . (int)$plan['id'] . '/rate/' . (int)$r['id'] . '/fail'); ?>" onsubmit="return confirm('Rate als fehlgeschlagen (Rücklastschrift) markieren?');">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <button class="btn inline secondary" type="submit">Fehlgeschlagen</button>
              </form>
            <?php elseif (($r['status'] ?? '') === 'failed'): ?>
              <form method="post" action="<?php echo \App\Support\App::url('/installments/' . (int)$plan['id'] . '/rate/' . (int)$r['id'] . '/reset'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <button class="btn inline secondary" type="submit">Erneut einplanen</button>
              </form>
            <?php else: ?>
              <span class="muted">–</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rates)): ?>
        <tr><td colspan="7" class="muted">Keine Raten.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
