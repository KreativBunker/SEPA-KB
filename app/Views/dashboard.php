<?php
use App\Support\App;
use App\Support\DateFormatter;

$role = $role ?? '';
$canStaff = in_array($role, ['admin', 'staff'], true);
$hasSelection = (int)$selectedCount > 0;
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
  <h2>Workflow</h2>
  <p class="muted" style="margin-top:0">Reihenfolge: Kontakte/Mandate pflegen, Rechnungen laden, Auswahl treffen, Export erzeugen.</p>
  <ol class="dash-steps">
    <li class="dash-step">
      <div class="dash-step-num">1</div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Kontakte aus sevdesk</h3>
          <span class="muted">
            <?php echo (int)$contactsCount; ?> im Cache · <?php echo (int)$contactsWithIban; ?> mit IBAN
          </span>
        </div>
        <p class="muted">Kontakte aus sevdesk laden und als SEPA-Mandate übernehmen. Nur einmalig oder bei neuen Kunden nötig.</p>
        <div class="actions">
          <a class="btn inline" href="<?php echo App::url('/mandates/import-sevdesk'); ?>">Kontakt-Import öffnen</a>
          <a class="btn inline secondary" href="<?php echo App::url('/mandates'); ?>">Mandate verwalten</a>
        </div>
      </div>
    </li>

    <li class="dash-step">
      <div class="dash-step-num">2</div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Rechnungen aus sevdesk laden</h3>
          <span class="muted"><?php echo (int)$invoicesCount; ?> aktuell geladen</span>
        </div>
        <p class="muted">Offene Rechnungen aus sevdesk in den Arbeitsspeicher holen.</p>
        <div class="actions">
          <form method="post" action="<?php echo App::url('/invoices/load'); ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
            <button type="submit" class="btn inline">Jetzt laden</button>
          </form>
          <a class="btn inline secondary" href="<?php echo App::url('/invoices'); ?>">Liste öffnen</a>
        </div>
      </div>
    </li>

    <li class="dash-step <?php echo $invoicesCount === 0 ? 'is-disabled' : ''; ?>">
      <div class="dash-step-num">3</div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Rechnungen auswählen</h3>
          <span class="muted">
            <?php if ($hasSelection): ?>
              <?php echo (int)$selectedCount; ?> ausgewählt
            <?php else: ?>
              keine Auswahl
            <?php endif; ?>
          </span>
        </div>
        <p class="muted">In der Rechnungsliste die zu lastschriftenden Posten markieren und „Auswahl speichern".</p>
        <div class="actions">
          <a class="btn inline" href="<?php echo App::url('/invoices'); ?>">
            <?php echo $hasSelection ? 'Auswahl bearbeiten' : 'Auswahl treffen'; ?>
          </a>
        </div>
      </div>
    </li>

    <li class="dash-step <?php echo !$hasSelection ? 'is-disabled' : ''; ?>">
      <div class="dash-step-num">4</div>
      <div class="dash-step-body">
        <div class="dash-step-head">
          <h3>Export erstellen</h3>
          <span class="muted">SEPA pain.008 erzeugen</span>
        </div>
        <p class="muted">
          <?php if ($hasSelection): ?>
            Aus <?php echo (int)$selectedCount; ?> ausgewählten Rechnungen einen Lastschrift-Lauf anlegen.
          <?php else: ?>
            Verfügbar, sobald in Schritt 3 mindestens eine Rechnung ausgewählt wurde.
          <?php endif; ?>
        </p>
        <div class="actions">
          <?php if ($hasSelection): ?>
            <a class="btn inline" href="<?php echo App::url('/exports/create'); ?>">Neuer Export</a>
          <?php else: ?>
            <button type="button" class="btn inline" disabled>Neuer Export</button>
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
