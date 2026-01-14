<div class="card">
  <div class="topbar">
    <h1>Exporte</h1>
    <a class="btn inline" href="<?php echo \App\Support\App::url('/exports/create'); ?>">Neuer Export</a>
  </div>
</div>

<div class="card">
  <h2>Historie</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Titel</th>
        <th>Datum</th>
        <th>Status</th>
        <th>Anzahl</th>
        <th>Summe</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($runs as $r): ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo htmlspecialchars($r['title']); ?></td>
          <td><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)$r['collection_date'])); ?></td>
          <td><?php echo htmlspecialchars($r['status']); ?></td>
          <td><?php echo htmlspecialchars((string)$r['total_count']); ?></td>
          <td><?php echo htmlspecialchars(number_format((float)$r['total_sum'], 2, ',', '.')); ?> EUR</td>
          <td><a class="btn inline secondary" href="<?php echo \App\Support\App::url('/exports/' . (int)$r['id']); ?>">Öffnen</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($runs)): ?>
        <tr><td colspan="7" class="muted">Noch keine Exporte.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
