<div class="card">
  <h1>Einstellungen</h1>
  <form method="post" action="<?php echo \App\Support\App::url('/settings'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <div class="row">
      <div>
        <label>Firmenname</label>
        <input name="creditor_name" value="<?php echo htmlspecialchars($settings['creditor_name'] ?? ''); ?>" required>
      </div>
      <div>
        <label>Gläubiger ID</label>
        <input name="creditor_id" value="<?php echo htmlspecialchars($settings['creditor_id'] ?? ''); ?>" required>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Gläubiger Straße</label>
        <input name="creditor_street" value="<?php echo htmlspecialchars($settings['creditor_street'] ?? ''); ?>">
      </div>
      <div>
        <label>Gläubiger PLZ</label>
        <input name="creditor_zip" value="<?php echo htmlspecialchars($settings['creditor_zip'] ?? ''); ?>">
      </div>
    </div>
    <div class="row">
      <div>
        <label>Gläubiger Ort</label>
        <input name="creditor_city" value="<?php echo htmlspecialchars($settings['creditor_city'] ?? ''); ?>">
      </div>
      <div>
        <label>Gläubiger Land (ISO)</label>
        <input name="creditor_country" value="<?php echo htmlspecialchars($settings['creditor_country'] ?? ''); ?>" maxlength="2">
      </div>
    </div>

    <div class="row">
      <div>
        <label>IBAN</label>
        <input name="creditor_iban" value="<?php echo htmlspecialchars($settings['creditor_iban'] ?? ''); ?>" required>
      </div>
      <div>
        <label>BIC optional</label>
        <input name="creditor_bic" value="<?php echo htmlspecialchars($settings['creditor_bic'] ?? ''); ?>">
      </div>
    </div>

    <label>Initiating Party Name optional</label>
    <input name="initiating_party_name" value="<?php echo htmlspecialchars($settings['initiating_party_name'] ?? ''); ?>">

    <div class="row3">
      <div>
        <label>Standard Scheme</label>
        <select name="default_scheme">
          <option value="CORE" <?php echo (($settings['default_scheme'] ?? 'CORE') === 'CORE') ? 'selected' : ''; ?>>CORE</option>
          <option value="B2B" <?php echo (($settings['default_scheme'] ?? '') === 'B2B') ? 'selected' : ''; ?>>B2B</option>
        </select>
      </div>
      <div>
        <label>Tage bis Ausführung</label>
        <input name="default_days_until_collection" value="<?php echo htmlspecialchars((string)($settings['default_days_until_collection'] ?? 5)); ?>">
      </div>
      <div>
        <label>Batch Booking</label>
        <input type="checkbox" name="batch_booking" value="1" <?php echo !empty($settings['batch_booking']) ? 'checked' : ''; ?>>
      </div>
    </div>

    <div class="row3">
      <div>
        <label>Texte bereinigen</label>
        <input type="checkbox" name="sanitize_text" value="1" <?php echo !empty($settings['sanitize_text']) ? 'checked' : ''; ?>>
      </div>
      <div>
        <label>BIC in XML schreiben</label>
        <input type="checkbox" name="include_bic" value="1" <?php echo !empty($settings['include_bic']) ? 'checked' : ''; ?>>
      </div>
      <div></div>
    </div>

    <div style="margin-top:14px">
      <button class="btn" type="submit">Speichern</button>
    </div>
  </form>
</div>
