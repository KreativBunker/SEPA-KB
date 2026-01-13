<div class="card">
  <h1>Export erstellen</h1>
  <p class="muted">Ausgewählte Rechnungen: <?php echo (int)$selected_count; ?></p>

  <?php
    $days = (int)($settings['default_days_until_collection'] ?? 5);
    $defaultDate = date('Y-m-d', time() + ($days * 86400));
  ?>

  <form method="post" action="<?php echo \App\Support\App::url('/exports'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <div class="row">
      <div>
        <label>Titel</label>
        <input name="title" value="Lastschrift <?php echo date('Y-m-d'); ?>">
      </div>
      <div>
        <label>Ausführungstermin</label>
        <input type="date" name="collection_date" value="<?php echo htmlspecialchars($defaultDate); ?>" required>
      </div>
    </div>

    <div class="row3">
      <div>
        <label>Scheme Default</label>
        <select name="scheme_default">
          <option value="CORE" selected>CORE</option>
          <option value="B2B">B2B</option>
        </select>
      </div>
      <div>
        <label>EndToEndId</label>
        <select name="endtoend_strategy">
          <option value="invoice_number" selected>Rechnungsnummer</option>
          <option value="generated">generiert</option>
        </select>
      </div>
      <div>
        <label>Batch Booking</label>
        <input type="checkbox" name="batch_booking" value="1" <?php echo !empty($settings['batch_booking']) ? 'checked' : ''; ?>>
      </div>
    </div>

    <label>Verwendungszweck Template</label>
    <input name="remittance_template" value="<?php echo htmlspecialchars($settings['remittance_template'] ?? 'Rechnung {invoice_number}'); ?>" placeholder="Rechnung {invoice_number}">

    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit">Lauf erstellen</button>
      <a class="btn secondary" href="<?php echo \App\Support\App::url('/invoices'); ?>">Zurück</a>
    </div>
  </form>
</div>
