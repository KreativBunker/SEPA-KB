<div class="card">
  <h1>Ratenplan erstellen</h1>

  <form method="post" action="<?php echo \App\Support\App::url('/installments'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <label>Quelle</label>
    <div style="display:flex; gap:28px; flex-wrap:wrap; margin-bottom:10px">
      <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer">
        <input type="radio" name="source" value="invoice" checked onclick="toggleSource()" style="width:auto; max-width:none; margin:0; flex:0 0 auto"> Aus sevdesk-Rechnung
      </label>
      <label style="display:flex; align-items:center; gap:8px; font-weight:400; cursor:pointer">
        <input type="radio" name="source" value="manual" onclick="toggleSource()" style="width:auto; max-width:none; margin:0; flex:0 0 auto"> Freier Betrag (über Mandat)
      </label>
    </div>

    <div id="src-invoice">
      <label>Rechnung</label>
      <?php if (empty($invoices)): ?>
        <p class="muted">Keine geladenen Rechnungen im Cache. Bitte zuerst unter <a href="<?php echo \App\Support\App::url('/invoices'); ?>">Rechnungen</a> laden.</p>
      <?php else: ?>
        <select name="sevdesk_invoice_id">
          <option value="">– bitte wählen –</option>
          <?php foreach ($invoices as $inv): ?>
            <option value="<?php echo (int)($inv['id'] ?? 0); ?>">
              <?php echo htmlspecialchars((string)($inv['invoiceNumber'] ?? $inv['id'] ?? '')); ?>
              – <?php echo htmlspecialchars((string)($inv['contact_name'] ?? '')); ?>
              – <?php echo htmlspecialchars(number_format((float)($inv['sumGross'] ?? 0), 2, ',', '.')); ?> EUR
            </option>
          <?php endforeach; ?>
        </select>
        <p class="muted">Der Gesamtbetrag wird aus der Rechnung übernommen. Ein aktives SEPA-Mandat des Kontakts ist erforderlich.</p>
      <?php endif; ?>
    </div>

    <div id="src-manual" style="display:none">
      <div class="row">
        <div>
          <label>Mandat</label>
          <select name="mandate_id">
            <option value="">– bitte wählen –</option>
            <?php foreach ($mandates as $m): ?>
              <option value="<?php echo (int)$m['id']; ?>">
                <?php echo htmlspecialchars((string)($m['debtor_name'] ?? '')); ?>
                – <?php echo htmlspecialchars((string)($m['mandate_reference'] ?? '')); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Gesamtbetrag (EUR)</label>
          <input name="total_amount" inputmode="decimal" placeholder="z. B. 1200,00">
        </div>
      </div>
      <label>Bezeichnung / Verwendungszweck-Text</label>
      <input name="invoice_number" placeholder="z. B. Ratenvereinbarung 2026-01">
    </div>

    <hr style="margin:16px 0;border:none;border-top:1px solid rgba(0,0,0,.08)">

    <div class="row3">
      <div>
        <label>Anzahl Raten</label>
        <input type="number" name="rate_count" min="1" max="60" value="<?php echo (int)$defaultRates; ?>" required>
      </div>
      <div>
        <label>Abstand zwischen Raten (Monate)</label>
        <input type="number" name="interval_months" min="1" max="12" value="1" required>
        <p class="muted" style="margin-top:4px">1 = monatlich, 3 = vierteljährlich</p>
      </div>
      <div>
        <label>Erstes Einzugsdatum</label>
        <input type="date" name="first_collection_date" value="<?php echo htmlspecialchars($defaultFirstDate); ?>" required>
      </div>
    </div>

    <label>Verwendungszweck Template</label>
    <input name="remittance_template" value="<?php echo htmlspecialchars($defaultRemittance); ?>" placeholder="Rechnung {invoice_number} Rate {rate_no}/{rate_count}">
    <p class="muted">Platzhalter: <code>{invoice_number}</code>, <code>{rate_no}</code>, <code>{rate_count}</code></p>

    <label>Notiz (optional)</label>
    <input name="notes" placeholder="interne Notiz">

    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit">Ratenplan anlegen</button>
      <a class="btn secondary" href="<?php echo \App\Support\App::url('/installments'); ?>">Zurück</a>
    </div>
  </form>
</div>

<script>
function toggleSource() {
  var isManual = document.querySelector('input[name="source"]:checked').value === 'manual';
  document.getElementById('src-invoice').style.display = isManual ? 'none' : 'block';
  document.getElementById('src-manual').style.display = isManual ? 'block' : 'none';
}
</script>
