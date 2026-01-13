<div class="card">
  <div class="topbar">
    <h1>Mandate aus sevdesk importieren</h1>
    <div class="actions">
      <a class="btn inline secondary" href="<?php echo \App\Support\App::url('/mandates'); ?>">Zurück</a>
    </div>
  </div>
  <p class="muted">Hier kannst du Kontakte aus sevdesk laden und daraus Mandate in diesem Tool anlegen. Importiert werden nur Kontakte mit einer IBAN. Du kannst die Auswahl vor dem Import filtern.</p>

  <form method="post" action="<?php echo \App\Support\App::url('/mandates/import-sevdesk/load'); ?>" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <button class="btn inline" type="submit">Kontakte laden</button>
  </form>
</div>

<div class="card">
  <h2>Filter</h2>
  <form method="get" action="<?php echo \App\Support\App::url('/mandates/import-sevdesk'); ?>" style="margin-top:10px">
    <div class="row">
      <div>
        <label>Suche</label>
        <input name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, Kundennummer, Contact ID, IBAN">
      </div>
      <div style="display:flex; align-items:end; gap:10px">
        <button class="btn inline" type="submit">Filtern</button>
        <a class="btn inline secondary" href="<?php echo \App\Support\App::url('/mandates/import-sevdesk'); ?>">Zurücksetzen</a>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <h2>Import</h2>

  <?php if (empty($contacts)): ?>
    <p class="muted">Noch keine Kontakte geladen. Klicke oben auf Kontakte laden.</p>
  <?php else: ?>
    <form method="post" action="<?php echo \App\Support\App::url('/mandates/import-sevdesk/run'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

      <div class="row3" style="margin-bottom: 10px">
        <div>
          <label>Mandatsdatum</label>
          <input name="mandate_date" value="<?php echo htmlspecialchars((string)($mandate_date ?? date('Y-m-d'))); ?>" placeholder="YYYY-MM-DD">
          <div class="muted">Wird für neu angelegte Mandate genutzt. Du kannst es später noch ändern.</div>
        </div>
        <div>
          <label>Schema</label>
          <select name="scheme">
            <option value="CORE" <?php echo (($scheme ?? 'CORE') === 'CORE') ? 'selected' : ''; ?>>CORE</option>
            <option value="B2B" <?php echo (($scheme ?? 'CORE') === 'B2B') ? 'selected' : ''; ?>>B2B</option>
          </select>
        </div>
        <div>
          <label>Status</label>
          <select name="status">
            <option value="active" <?php echo (($status ?? 'active') === 'active') ? 'selected' : ''; ?>>active</option>
            <option value="paused" <?php echo (($status ?? 'active') === 'paused') ? 'selected' : ''; ?>>paused</option>
          </select>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th style="width:42px"><input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;contact_ids[]&quot;]').forEach(cb => cb.checked = this.checked)"></th>
            <th>ID</th>
            <th>Name</th>
            <th>Kundennummer</th>
            <th>IBAN</th>
            <th>BIC</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contacts as $c): ?>
            <tr>
              <td><input type="checkbox" name="contact_ids[]" value="<?php echo (int)$c['id']; ?>"></td>
              <td><?php echo (int)$c['id']; ?></td>
              <td><?php echo htmlspecialchars((string)$c['name']); ?></td>
              <td><?php echo htmlspecialchars((string)($c['customerNumber'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($c['bankAccount'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($c['bankBic'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top: 12px" class="actions">
        <button class="btn" type="submit">Auswahl importieren</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
  // simple client helper, no dependencies
</script>
