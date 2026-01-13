<div class="card">
  <h1>Dashboard</h1>
  <?php if ($latest): ?>
    <p>Letzter Export: <strong><?php echo htmlspecialchars($latest['title']); ?></strong></p>
    <p class="muted">Status: <?php echo htmlspecialchars($latest['status']); ?>, Summe: <?php echo htmlspecialchars((string)$latest['total_sum']); ?> EUR, Anzahl: <?php echo htmlspecialchars((string)$latest['total_count']); ?></p>
    <div class="actions">
      <a class="btn inline" href="<?php echo \App\Support\App::url('/exports/' . (int)$latest['id']); ?>">Öffnen</a>
    </div>
  <?php else: ?>
    <p class="muted">Noch kein Export vorhanden.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Schnellstart</h2>
  <ol>
    <li>sevdesk Token eintragen</li>
    <li>Mandate pflegen</li>
    <li>Rechnungen laden und auswählen</li>
    <li>Export Lauf anlegen, validieren, XML erzeugen</li>
  </ol>
</div>
