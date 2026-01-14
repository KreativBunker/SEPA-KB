<div class="card">
  <h1>Setup</h1>
  <p class="muted">Beim ersten Aufruf werden Datenbank und Admin Nutzer angelegt.</p>

  <form method="post" action="<?php echo \App\Support\App::url('/setup'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <h2>Datenbank</h2>
    <div class="row">
      <div>
        <label>DB Host</label>
        <input name="db_host" value="<?php echo htmlspecialchars($defaults['db_host']); ?>" required>
      </div>
      <div>
        <label>DB Port</label>
        <input name="db_port" value="<?php echo htmlspecialchars((string)$defaults['db_port']); ?>" required>
      </div>
    </div>
    <div class="row">
      <div>
        <label>DB Name</label>
        <input name="db_name" required>
      </div>
      <div>
        <label>DB Charset</label>
        <input name="db_charset" value="<?php echo htmlspecialchars($defaults['db_charset']); ?>" required>
      </div>
    </div>
    <div class="row">
      <div>
        <label>DB User</label>
        <input name="db_user" required>
      </div>
      <div>
        <label>DB Passwort</label>
        <input name="db_pass" type="password">
      </div>
    </div>

    <h2>App</h2>
    <label>Base URL</label>
    <input name="base_url" value="<?php echo htmlspecialchars($defaults['base_url']); ?>" required>

    <h2>Admin</h2>
    <div class="row">
      <div>
        <label>Admin E Mail</label>
        <input name="admin_email" type="email" required>
      </div>
      <div>
        <label>Admin Passwort</label>
        <input name="admin_password" type="password" required>
      </div>
    </div>

    <h2>Gläubiger Daten</h2>
    <div class="row">
      <div>
        <label>Firmenname</label>
        <input name="creditor_name" placeholder="z.B. Dein Firmenname">
      </div>
      <div>
        <label>Gläubiger ID</label>
        <input name="creditor_id" placeholder="z.B. DE98ZZZ09999999999">
      </div>
    </div>
    <div class="row">
      <div>
        <label>Gläubiger Straße</label>
        <input name="creditor_street" placeholder="z.B. Musterstraße 1">
      </div>
      <div>
        <label>Gläubiger PLZ</label>
        <input name="creditor_zip" placeholder="z.B. 12345">
      </div>
    </div>
    <div class="row">
      <div>
        <label>Gläubiger Ort</label>
        <input name="creditor_city" placeholder="z.B. Musterstadt">
      </div>
      <div>
        <label>Gläubiger Land (ISO)</label>
        <input name="creditor_country" placeholder="z.B. DE" maxlength="2">
      </div>
    </div>
    <div class="row">
      <div>
        <label>IBAN</label>
        <input name="creditor_iban" placeholder="z.B. DE...">
      </div>
      <div>
        <label>BIC optional</label>
        <input name="creditor_bic" placeholder="z.B. NOLADE21XXX">
      </div>
    </div>
    <label>Initiating Party Name optional</label>
    <input name="initiating_party_name" placeholder="wenn abweichend">

    <h2>Standard</h2>
    <div class="row3">
      <div>
        <label>Tage bis Ausführung</label>
        <input name="default_days_until_collection" value="<?php echo htmlspecialchars((string)$defaults['default_days_until_collection']); ?>">
      </div>
      <div>
        <label>Batch Booking</label>
        <select name="batch_booking">
          <option value="1" selected>ja</option>
          <option value="0">nein</option>
        </select>
      </div>
      <div>
        <label>Texte bereinigen</label>
        <select name="sanitize_text">
          <option value="1" selected>ja</option>
          <option value="0">nein</option>
        </select>
      </div>
    </div>

    <label>BIC in XML schreiben</label>
    <select name="include_bic">
      <option value="0" selected>nein</option>
      <option value="1">ja</option>
    </select>

    <div style="margin-top:14px">
      <button class="btn" type="submit">Installation starten</button>
    </div>
  </form>
</div>
