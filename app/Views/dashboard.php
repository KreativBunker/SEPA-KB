<?php
use App\Support\App;
use App\Support\DateFormatter;

$role = $role ?? '';
$canStaff = in_array($role, ['admin', 'staff'], true);
$hasSelection = (int)$selectedCount > 0;
$hasMandates = (int)($mandateStats['active'] ?? 0) > 0;
$hasInvoices = (int)$invoicesCount > 0;

// Status pro Schritt: 'done' | 'current' | 'pending'
$stepStatus = [
    1 => $hasMandates ? 'done' : 'current',
    2 => $hasInvoices ? 'done' : ($hasMandates ? 'current' : 'pending'),
    3 => $hasSelection ? 'done' : ($hasInvoices ? 'current' : 'pending'),
    4 => $hasSelection ? 'current' : 'pending',
];

$check = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="5 12 10 17 19 7"></polyline></svg>';
?>

<div class="card dash-hero">
  <div class="dash-hero-text">
    <h1>Dashboard</h1>
    <p class="muted">Überblick über Mandate, Rechnungen und Exporte.</p>
  </div>
</div>

<?php if (!empty($warnings)): ?>
<div class="card">
  <h2>Hinweise</h2>
  <ul class="dash-warnings">
    <?php foreach ($warnings as $w): ?>
      <li class="dash-warning <?php echo htmlspecialchars($w['type']); ?>">
        <span><?php echo htmlspecialchars($w['text']); ?></span>
        <?php if (!empty($w['href'])): ?>
          <a class="btn inline secondary" href="<?php echo App::url($w['href']); ?>">Öffnen</a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="dash-kpis">
  <div class="kpi">
    <div class="kpi-label">Aktive Mandate</div>
    <div class="kpi-value"><?php echo (int)($mandateStats['active'] ?? 0); ?></div>
    <div class="kpi-sub muted">
      <?php echo (int)($mandateStats['paused'] ?? 0); ?> pausiert ·
      <?php echo (int)($mandateStats['revoked'] ?? 0); ?> widerrufen
    </div>
  </div>

  <div class="kpi">
    <div class="kpi-label">Offene Online-Mandate</div>
    <div class="kpi-value"><?php echo (int)$openOnlineMandates; ?></div>
    <div class="kpi-sub muted">warten auf Unterschrift</div>
  </div>

  <div class="kpi">
    <div class="kpi-label">Geladene Rechnungen</div>
    <div class="kpi-value"><?php echo (int)$invoicesCount; ?></div>
    <div class="kpi-sub muted">
      Summe <?php echo number_format((float)$invoicesSum, 2, ',', '.'); ?> EUR
      <?php if ($hasSelection): ?>
        · <?php echo (int)$selectedCount; ?> ausgewählt
      <?php endif; ?>
    </div>
  </div>

  <div class="kpi">
    <div class="kpi-label">Letzter Export</div>
    <?php if ($latest): ?>
      <div class="kpi-value"><?php echo number_format((float)($latest['total_sum'] ?? 0), 2, ',', '.'); ?> €</div>
      <div class="kpi-sub muted">
        <?php echo (int)($latest['total_count'] ?? 0); ?> Posten ·
        <?php echo htmlspecialchars((string)($latest['status'] ?? '')); ?>
      </div>
    <?php else: ?>
      <div class="kpi-value">–</div>
      <div class="kpi-sub muted">kein Export</div>
    <?php endif; ?>
  </div>
</div>

<?php if ($canStaff): ?>
<div class="card">
  <div class="dash-workflow-head">
    <h2>SEPA-Lauf in 4 Schritten</h2>
    <p class="muted">Folge der Reihenfolge — der nächste offene Schritt ist hervorgehoben.</p>
  </div>

  <ol class="dash-steps">
    <li class="dash-step is-<?php echo $stepStatus[1]; ?>">
      <div class="dash-step-rail">
        <div class="dash-step-bubble">
          <?php if ($stepStatus[1] === 'done'): ?><?php echo $check; ?><?php else: ?>1<?php endif; ?>
        </div>
        <div class="dash-step-line"></div>
      </div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Kontakte &amp; Mandate</h3>
          <span class="dash-step-badge"><?php echo $stepStatus[1] === 'done' ? 'erledigt' : ($stepStatus[1] === 'current' ? 'als nächstes' : 'wartet'); ?></span>
        </div>
        <p class="dash-step-desc">Kontakte einmalig aus sevdesk laden und als SEPA-Mandate anlegen.</p>
        <div class="dash-step-meta muted">
          <?php echo (int)($mandateStats['active'] ?? 0); ?> aktive Mandate ·
          <?php echo (int)$contactsCount; ?> Kontakte im Cache
        </div>
        <div class="actions">
          <a class="btn inline" href="<?php echo App::url('/mandates/import-sevdesk'); ?>">Kontakt-Import öffnen</a>
          <a class="btn inline secondary" href="<?php echo App::url('/mandates'); ?>">Mandate verwalten</a>
        </div>
      </div>
    </li>

    <li class="dash-step is-<?php echo $stepStatus[2]; ?>">
      <div class="dash-step-rail">
        <div class="dash-step-bubble">
          <?php if ($stepStatus[2] === 'done'): ?><?php echo $check; ?><?php else: ?>2<?php endif; ?>
        </div>
        <div class="dash-step-line"></div>
      </div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Rechnungen laden</h3>
          <span class="dash-step-badge"><?php echo $stepStatus[2] === 'done' ? 'erledigt' : ($stepStatus[2] === 'current' ? 'als nächstes' : 'wartet'); ?></span>
        </div>
        <p class="dash-step-desc">Offene Rechnungen aus sevdesk in die Auswahl-Liste holen.</p>
        <div class="dash-step-meta muted">
          <?php echo (int)$invoicesCount; ?> Rechnungen geladen ·
          Summe <?php echo number_format((float)$invoicesSum, 2, ',', '.'); ?> €
        </div>
        <div class="actions">
          <form method="post" action="<?php echo App::url('/invoices/load'); ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <button type="submit" class="btn inline">
              <?php echo $hasInvoices ? 'Neu laden' : 'Jetzt laden'; ?>
            </button>
          </form>
          <a class="btn inline secondary" href="<?php echo App::url('/invoices'); ?>">Liste öffnen</a>
        </div>
      </div>
    </li>

    <li class="dash-step is-<?php echo $stepStatus[3]; ?>">
      <div class="dash-step-rail">
        <div class="dash-step-bubble">
          <?php if ($stepStatus[3] === 'done'): ?><?php echo $check; ?><?php else: ?>3<?php endif; ?>
        </div>
        <div class="dash-step-line"></div>
      </div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Rechnungen auswählen</h3>
          <span class="dash-step-badge"><?php echo $stepStatus[3] === 'done' ? 'erledigt' : ($stepStatus[3] === 'current' ? 'als nächstes' : 'wartet'); ?></span>
        </div>
        <p class="dash-step-desc">Markiere die Rechnungen, die per Lastschrift eingezogen werden sollen.</p>
        <div class="dash-step-meta muted">
          <?php echo $hasSelection ? (int)$selectedCount . ' ausgewählt' : 'noch keine Auswahl'; ?>
        </div>
        <div class="actions">
          <?php if ($hasInvoices): ?>
            <a class="btn inline" href="<?php echo App::url('/invoices'); ?>">
              <?php echo $hasSelection ? 'Auswahl bearbeiten' : 'Jetzt auswählen'; ?>
            </a>
          <?php else: ?>
            <button type="button" class="btn inline" disabled title="Erst in Schritt 2 Rechnungen laden">Jetzt auswählen</button>
          <?php endif; ?>
        </div>
      </div>
    </li>

    <li class="dash-step is-<?php echo $stepStatus[4]; ?> is-last">
      <div class="dash-step-rail">
        <div class="dash-step-bubble">4</div>
      </div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Export erstellen</h3>
          <span class="dash-step-badge"><?php echo $stepStatus[4] === 'current' ? 'bereit' : 'wartet'; ?></span>
        </div>
        <p class="dash-step-desc">SEPA pain.008-Datei aus der Auswahl erzeugen.</p>
        <div class="dash-step-meta muted">
          <?php if ($hasSelection): ?>
            <?php echo (int)$selectedCount; ?> Rechnungen bereit für den Export
          <?php else: ?>
            Verfügbar, sobald Schritt 3 abgeschlossen ist
          <?php endif; ?>
        </div>
        <div class="actions">
          <?php if ($hasSelection): ?>
            <a class="btn inline" href="<?php echo App::url('/exports/create'); ?>">Neuen Export anlegen</a>
          <?php else: ?>
            <button type="button" class="btn inline" disabled>Neuen Export anlegen</button>
          <?php endif; ?>
          <a class="btn inline secondary" href="<?php echo App::url('/exports'); ?>">Alle Exporte</a>
        </div>
      </div>
    </li>
  </ol>
</div>
<?php endif; ?>

<div class="card">
  <h2>Letzte Exporte</h2>
  <?php if (empty($latestExports)): ?>
    <p class="muted">Noch kein Export vorhanden.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Titel</th>
            <th>Datum</th>
            <th>Status</th>
            <th>Posten</th>
            <th>Summe</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($latestExports as $r): ?>
            <?php
              $status = (string)($r['status'] ?? '');
              $pillClass = match ($status) {
                  'final', 'exported' => 'ok',
                  'validated' => 'warn',
                  default => '',
              };
            ?>
            <tr>
              <td><?php echo htmlspecialchars((string)($r['title'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars(DateFormatter::toDisplay((string)($r['collection_date'] ?? ''))); ?></td>
              <td><span class="pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
              <td><?php echo (int)($r['total_count'] ?? 0); ?></td>
              <td><?php echo number_format((float)($r['total_sum'] ?? 0), 2, ',', '.'); ?> €</td>
              <td><a class="btn inline secondary" href="<?php echo App::url('/exports/' . (int)$r['id']); ?>">Öffnen</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
