<?php
use App\Support\App;
?>
<div class="card">
  <div class="topbar">
    <h1>Verträge</h1>
    <a href="<?php echo App::url('/contracts/create'); ?>" class="btn">Neuer Vertrag</a>
  </div>

  <?php if (empty($items)): ?>
    <p class="muted">Noch keine Verträge vorhanden.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Titel</th>
            <th>Kontakt</th>
            <th>SEPA</th>
            <th>Status</th>
            <th>Erstellt</th>
            <th>Unterschrieben</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $c): ?>
          <?php
            $status = (string)($c['status'] ?? 'draft');
            $statusClass = 'warn';
            $statusLabel = $status;
            if ($status === 'signed') { $statusClass = 'ok'; $statusLabel = 'Unterschrieben'; }
            elseif ($status === 'open') { $statusClass = 'warn'; $statusLabel = 'Offen'; }
            elseif ($status === 'revoked') { $statusClass = 'err'; $statusLabel = 'Widerrufen'; }
            elseif ($status === 'cancelled') { $statusClass = 'err'; $statusLabel = 'Gekündigt'; }
            elseif ($status === 'draft') { $statusClass = ''; $statusLabel = 'Entwurf'; }
          ?>
          <tr>
            <td><a href="<?php echo App::url('/contracts/' . (int)$c['id']); ?>"><?php echo htmlspecialchars((string)($c['title'] ?? '')); ?></a></td>
            <td><?php echo htmlspecialchars((string)($c['contact_name'] ?? '')); ?></td>
            <td>
              <?php if ((int)($c['include_sepa'] ?? 0)): ?>
                <span class="pill ok">Ja</span>
              <?php else: ?>
                <span class="pill" style="background:#e5e7eb;color:#374151;">Nein</span>
              <?php endif; ?>
            </td>
            <td><span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
            <td class="muted"><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($c['created_at'] ?? ''))); ?></td>
            <td class="muted"><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($c['signed_at'] ?? ''))); ?></td>
            <td>
              <?php if ($status === 'signed' || $status === 'cancelled'): ?>
                <a href="<?php echo App::url('/contracts/' . (int)$c['id'] . '/pdf'); ?>" class="btn inline">Vertrag-PDF</a>
                <?php if ((int)($c['include_sepa'] ?? 0)): ?>
                  <a href="<?php echo App::url('/contracts/' . (int)$c['id'] . '/sepa-pdf'); ?>" class="btn inline">SEPA-PDF</a>
                <?php endif; ?>
                <?php if ($status === 'cancelled'): ?>
                  <a href="<?php echo App::url('/contracts/' . (int)$c['id'] . '/cancellation-pdf'); ?>" class="btn inline">Kündigung-PDF</a>
                <?php endif; ?>
              <?php else: ?>
                <a href="<?php echo App::url('/contracts/' . (int)$c['id'] . '/edit'); ?>" class="btn inline secondary">Bearbeiten</a>
                <form method="post" action="<?php echo App::url('/contracts/' . (int)$c['id'] . '/delete'); ?>" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)($csrf ?? '')); ?>">
                  <button class="btn inline danger" type="submit" onclick="return confirm('Vertrag endgültig löschen?');">Löschen</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
