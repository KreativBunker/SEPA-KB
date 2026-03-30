<?php
use App\Support\App;
?>
<div class="card">
  <div class="topbar">
    <h1>Vertragsvorlagen</h1>
    <a href="<?php echo App::url('/contract-templates/create'); ?>" class="btn">Neue Vorlage</a>
  </div>

  <?php if (empty($items)): ?>
    <p class="muted">Noch keine Vorlagen vorhanden.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Titel</th>
            <th>SEPA</th>
            <th>Status</th>
            <th>Erstellt</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $t): ?>
          <tr>
            <td><?php echo htmlspecialchars((string)($t['title'] ?? '')); ?></td>
            <td>
              <?php if ((int)($t['include_sepa'] ?? 0)): ?>
                <span class="pill ok">Ja</span>
              <?php else: ?>
                <span class="pill" style="background:#e5e7eb;color:#374151;">Nein</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int)($t['is_active'] ?? 0)): ?>
                <span class="pill ok">Aktiv</span>
              <?php else: ?>
                <span class="pill warn">Inaktiv</span>
              <?php endif; ?>
            </td>
            <td class="muted"><?php echo htmlspecialchars((string)($t['created_at'] ?? '')); ?></td>
            <td>
              <div class="actions">
                <a href="<?php echo App::url('/contract-templates/' . (int)$t['id'] . '/edit'); ?>" class="btn inline secondary">Bearbeiten</a>
                <form method="post" action="<?php echo App::url('/contract-templates/' . (int)$t['id'] . '/delete'); ?>" style="display:inline;" onsubmit="return confirm('Vorlage wirklich loeschen?');">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
                  <button type="submit" class="btn inline danger">Loeschen</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
