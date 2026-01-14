<div class="card">
  <div class="topbar">
    <h1>Export Lauf #<?php echo (int)$run['id']; ?></h1>
    <div class="actions">
      <a class="btn inline secondary" href="<?php echo \App\Support\App::url('/exports'); ?>">Zurück</a>
      <?php if (!empty($run['file_path'])): ?>
        <a class="btn inline" href="<?php echo \App\Support\App::url('/exports/' . (int)$run['id'] . '/download'); ?>">XML Download</a>
      <?php endif; ?>
    </div>
  </div>

  <p class="muted">Titel: <?php echo htmlspecialchars($run['title']); ?>, Datum: <?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$run['collection_date'])); ?>, Status: <?php echo htmlspecialchars($run['status']); ?></p>

  <div class="actions" style="margin-top:10px">
    <form method="post" action="<?php echo \App\Support\App::url('/exports/' . (int)$run['id'] . '/validate'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn inline secondary" type="submit">Validieren</button>
    </form>

    <form method="post" action="<?php echo \App\Support\App::url('/exports/' . (int)$run['id'] . '/generate'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn inline" type="submit">XML erzeugen</button>
    </form>

    <form method="post" action="<?php echo \App\Support\App::url('/exports/' . (int)$run['id'] . '/finalize'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn inline secondary" type="submit" onclick="return confirm('Lauf abschließen? Danach wird Duplicate Schutz gesetzt.');">Abschließen</button>
    </form>
  </div>

  <?php if (!empty($run['file_hash'])): ?>
    <p class="muted" style="margin-top:10px">Datei Hash: <?php echo htmlspecialchars($run['file_hash']); ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Positionen</h2>
  <table>
    <thead>
      <tr>
        <th>Status</th>
        <th>Rechnung</th>
        <th>Kunde</th>
        <th>IBAN</th>
        <th>Betrag</th>
        <th>Hinweis</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td>
            <?php if ($it['status'] === 'ok'): ?>
              <span class="pill ok">ok</span>
            <?php elseif ($it['status'] === 'blocked_duplicate'): ?>
              <span class="pill warn">duplicate</span>
            <?php else: ?>
              <span class="pill err">error</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($it['invoice_number']); ?></td>
          <td><?php echo htmlspecialchars($it['debtor_name']); ?></td>
          <td><?php echo htmlspecialchars($it['debtor_iban']); ?></td>
          <td><?php echo htmlspecialchars(number_format((float)$it['amount'], 2, ',', '.')); ?> EUR</td>
          <td class="muted"><?php echo htmlspecialchars((string)($it['error_text'] ?? '')); ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" class="muted">Keine Positionen.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
