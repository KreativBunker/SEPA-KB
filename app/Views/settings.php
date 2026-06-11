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

    <h2 style="margin-top:24px">E-Mail / Inkasso</h2>
    <p class="muted">Versandweg für Inkasso-Übergaben sowie die E-Mail-Adresse des Inkassobüros.</p>

    <?php $mailProvider = ($settings['mail_provider'] ?? 'smtp') === 'm365' ? 'm365' : 'smtp'; ?>
    <div class="row">
      <div>
        <label>Versand über</label>
        <select name="mail_provider" id="mail_provider" onchange="document.getElementById('mail-smtp').style.display = this.value === 'smtp' ? '' : 'none'; document.getElementById('mail-m365').style.display = this.value === 'm365' ? '' : 'none';">
          <option value="smtp" <?php echo $mailProvider === 'smtp' ? 'selected' : ''; ?>>Eigener SMTP-Server</option>
          <option value="m365" <?php echo $mailProvider === 'm365' ? 'selected' : ''; ?>>Microsoft 365 (Graph API)</option>
        </select>
      </div>
      <div>
        <label>Inkassobüro E-Mail</label>
        <input name="inkasso_email" value="<?php echo htmlspecialchars($settings['inkasso_email'] ?? ''); ?>" placeholder="forderungen@inkasso-beispiel.de">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Absender-Adresse</label>
        <input name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" placeholder="buchhaltung@example.de">
      </div>
      <div>
        <label>Absender-Name</label>
        <input name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>">
      </div>
    </div>

    <div id="mail-m365" style="<?php echo $mailProvider === 'm365' ? '' : 'display:none'; ?>">
      <p class="muted">Benötigt eine App-Registrierung in Microsoft Entra ID mit der Application-Berechtigung <strong>Mail.Send</strong> (mit Admin-Zustimmung). Der Versand erfolgt als das oben angegebene Absender-Postfach.</p>
      <div class="row">
        <div>
          <label>Tenant-ID (Verzeichnis-ID)</label>
          <input name="m365_tenant_id" value="<?php echo htmlspecialchars($settings['m365_tenant_id'] ?? ''); ?>" placeholder="00000000-0000-0000-0000-000000000000">
        </div>
        <div>
          <label>Client-ID (Anwendungs-ID)</label>
          <input name="m365_client_id" value="<?php echo htmlspecialchars($settings['m365_client_id'] ?? ''); ?>" placeholder="00000000-0000-0000-0000-000000000000">
        </div>
      </div>
      <div class="row">
        <div>
          <label>Client Secret</label>
          <input type="password" name="m365_client_secret" value="" autocomplete="new-password" placeholder="<?php echo !empty($settings['m365_client_secret_encrypted']) ? 'gespeichert – leer lassen zum Beibehalten' : ''; ?>">
        </div>
        <div></div>
      </div>
    </div>

    <div id="mail-smtp" style="<?php echo $mailProvider === 'smtp' ? '' : 'display:none'; ?>">
    <div class="row">
      <div>
        <label>SMTP-Host</label>
        <input name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.example.de">
      </div>
      <div>
        <label>SMTP-Port</label>
        <input name="smtp_port" value="<?php echo htmlspecialchars((string)($settings['smtp_port'] ?? 587)); ?>">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Verschlüsselung</label>
        <select name="smtp_encryption">
          <option value="tls" <?php echo (($settings['smtp_encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>STARTTLS (Port 587)</option>
          <option value="ssl" <?php echo (($settings['smtp_encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL/TLS (Port 465)</option>
          <option value="none" <?php echo (($settings['smtp_encryption'] ?? '') === 'none') ? 'selected' : ''; ?>>Keine</option>
        </select>
      </div>
      <div>
        <label>SMTP-Benutzer</label>
        <input name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
      </div>
    </div>

    <div class="row">
      <div>
        <label>SMTP-Passwort</label>
        <input type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?php echo !empty($settings['smtp_pass_encrypted']) ? 'gespeichert – leer lassen zum Beibehalten' : ''; ?>">
      </div>
      <div></div>
    </div>
    </div>

    <div class="row">
      <div>
        <label>Test-Modus (nicht versenden, nur als Datei in storage/logs/mail ablegen)</label>
        <input type="checkbox" name="smtp_test_mode" value="1" <?php echo !empty($settings['smtp_test_mode']) ? 'checked' : ''; ?>>
      </div>
      <div></div>
    </div>

    <div style="margin-top:14px">
      <button class="btn" type="submit">Speichern</button>
    </div>
  </form>

  <form method="post" action="<?php echo \App\Support\App::url('/settings/smtp-test'); ?>" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <button class="btn inline secondary" type="submit">Test-E-Mail senden</button>
  </form>
</div>
