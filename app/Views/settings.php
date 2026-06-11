<?php
// Kleiner WYSIWYG-Editor (contenteditable) für formatierte E-Mail-Texte.
// Der HTML-Inhalt wird in das versteckte Textarea synchronisiert und
// serverseitig auf erlaubte Formatierungs-Tags reduziert.
$wysField = static function (string $name, string $value, string $placeholder = ''): void {
    ?>
    <div class="wys" data-placeholder="<?php echo htmlspecialchars($placeholder); ?>">
      <div class="wys-toolbar">
        <button type="button" data-cmd="bold" title="Fett"><b>F</b></button>
        <button type="button" data-cmd="italic" title="Kursiv"><i>K</i></button>
        <button type="button" data-cmd="underline" title="Unterstrichen"><u>U</u></button>
        <button type="button" data-cmd="strikeThrough" title="Durchgestrichen"><s>S</s></button>
        <button type="button" data-cmd="insertUnorderedList" title="Aufzählung">&bull; Liste</button>
        <button type="button" data-cmd="insertOrderedList" title="Nummerierte Liste">1. Liste</button>
        <button type="button" data-cmd="createLink" title="Link einfügen">Link</button>
        <button type="button" data-cmd="removeFormat" title="Formatierung entfernen">Format löschen</button>
      </div>
      <div class="wys-editor" contenteditable="true"></div>
      <textarea name="<?php echo htmlspecialchars($name); ?>" style="display:none"><?php echo htmlspecialchars($value); ?></textarea>
    </div>
    <?php
};
?>
<style>
.wys { border: 1px solid #d8dde6; border-radius: 10px; background: #fff; overflow: hidden; }
.wys-toolbar { display: flex; flex-wrap: wrap; gap: 4px; padding: 6px 8px; border-bottom: 1px solid #edf0f6; background: #f6f8fc; }
.wys-toolbar button { background: #fff; border: 1px solid #d8dde6; border-radius: 7px; padding: 4px 9px; font-size: 12.5px; cursor: pointer; color: #334155; }
.wys-toolbar button:hover { background: #eef2f9; }
.wys-editor { min-height: 110px; padding: 10px 12px; font-size: 14.5px; line-height: 1.5; outline: none; }
.wys-editor:empty::before { content: attr(data-ph); color: #94a3b8; }
.wys-editor ul, .wys-editor ol { margin: 6px 0; padding-left: 22px; }
.wys-editor a { color: #1D3860; }
</style>
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

    <label style="margin-top:10px">E-Mail-Signatur (wird an Mahnungen und Inkasso-E-Mails angehängt)</label>
    <?php $wysField('inkasso_signature', (string)($settings['inkasso_signature'] ?? ''), 'Mit freundlichen Grüßen – Kreativbunker GmbH, Buchhaltung, Tel. ...'); ?>

    <div class="row">
      <div>
        <label>Test-Modus (nicht versenden, nur als Datei in storage/logs/mail ablegen)</label>
        <input type="checkbox" name="smtp_test_mode" value="1" <?php echo !empty($settings['smtp_test_mode']) ? 'checked' : ''; ?>>
      </div>
      <div></div>
    </div>

    <h2 style="margin-top:24px">Mahnwesen-Automatik</h2>
    <p class="muted">Terminierter Mahnlauf: erzeugt nach Ablauf der Fristen automatisch Zahlungserinnerung, 1. und 2. Mahnung als Beleg in sevdesk und versendet sie per E-Mail an den Kunden. Ab der 2. Mahnung verbleibt die Rechnung zur manuellen Inkasso-Übergabe im Mahnwesen.</p>

    <div class="row">
      <div>
        <label>Mahnautomatik aktiv (Cron-Lauf erlaubt)</label>
        <input type="checkbox" name="dunning_enabled" value="1" <?php echo !empty($settings['dunning_enabled']) ? 'checked' : ''; ?>>
      </div>
      <div>
        <label>Modus</label>
        <select name="dunning_mode">
          <option value="review" <?php echo (($settings['dunning_mode'] ?? 'review') !== 'auto') ? 'selected' : ''; ?>>Mit Freigabe (Vorschläge manuell freigeben)</option>
          <option value="auto" <?php echo (($settings['dunning_mode'] ?? '') === 'auto') ? 'selected' : ''; ?>>Vollautomatisch (Cron versendet direkt)</option>
        </select>
      </div>
    </div>

    <div class="row">
      <div>
        <label>Tage nach Fälligkeit bis Zahlungserinnerung</label>
        <input name="dunning_days_stage1" value="<?php echo htmlspecialchars((string)($settings['dunning_days_stage1'] ?? 7)); ?>">
      </div>
      <div>
        <label>Tage nach Zahlungserinnerung bis 1. Mahnung</label>
        <input name="dunning_days_stage2" value="<?php echo htmlspecialchars((string)($settings['dunning_days_stage2'] ?? 7)); ?>">
      </div>
    </div>
    <div class="row">
      <div>
        <label>Tage nach 1. Mahnung bis 2. Mahnung</label>
        <input name="dunning_days_stage3" value="<?php echo htmlspecialchars((string)($settings['dunning_days_stage3'] ?? 7)); ?>">
      </div>
      <div>
        <label>Zahlungsziel der Mahnung (Tage)</label>
        <input name="dunning_pay_days" value="<?php echo htmlspecialchars((string)($settings['dunning_pay_days'] ?? 7)); ?>">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Rechnungen mit SEPA-Lastschrift / aktivem Mandat ausnehmen</label>
        <input type="checkbox" name="dunning_skip_sepa" value="1" <?php echo (!isset($settings['dunning_skip_sepa']) || !empty($settings['dunning_skip_sepa'])) ? 'checked' : ''; ?>>
      </div>
      <div>
        <label>Webcron-Token neu generieren (alte URL wird ungültig)</label>
        <input type="checkbox" name="dunning_regenerate_cron_token" value="1">
      </div>
    </div>

    <?php if (!empty($settings['dunning_cron_token'])): ?>
      <label>Webcron-URL (täglich aufrufen lassen, z.B. per Hosting-Cronjob oder cron-job.org)</label>
      <input readonly value="<?php echo htmlspecialchars(\App\Support\App::url('/cron/dunning/' . (string)$settings['dunning_cron_token'])); ?>" onclick="this.select();">
      <p class="muted">Alternativ per CLI-Cronjob: <span class="mono">php bin/dunning_cron.php</span></p>
    <?php else: ?>
      <p class="muted">Die Webcron-URL wird beim ersten Speichern automatisch erzeugt.</p>
    <?php endif; ?>

    <p class="muted" style="margin-top:10px">E-Mail-Vorlagen je Stufe. Platzhalter: <span class="mono">{name}</span>, <span class="mono">{invoice_number}</span>, <span class="mono">{amount}</span>, <span class="mono">{due_date}</span>, <span class="mono">{pay_until}</span>, <span class="mono">{stage_label}</span>. Leer lassen = eingebaute deutsche Standardtexte. Die Signatur (siehe oben) wird automatisch angehängt.</p>

    <label>Betreff Zahlungserinnerung</label>
    <input name="dunning_subject_1" value="<?php echo htmlspecialchars($settings['dunning_subject_1'] ?? ''); ?>" placeholder="Zahlungserinnerung zur Rechnung {invoice_number}">
    <label>Text Zahlungserinnerung</label>
    <?php $wysField('dunning_body_1', (string)($settings['dunning_body_1'] ?? ''), 'Standardtext wird verwendet, wenn leer'); ?>

    <label>Betreff 1. Mahnung</label>
    <input name="dunning_subject_2" value="<?php echo htmlspecialchars($settings['dunning_subject_2'] ?? ''); ?>" placeholder="1. Mahnung zur Rechnung {invoice_number}">
    <label>Text 1. Mahnung</label>
    <?php $wysField('dunning_body_2', (string)($settings['dunning_body_2'] ?? ''), 'Standardtext wird verwendet, wenn leer'); ?>

    <label>Betreff 2. Mahnung</label>
    <input name="dunning_subject_3" value="<?php echo htmlspecialchars($settings['dunning_subject_3'] ?? ''); ?>" placeholder="2. Mahnung zur Rechnung {invoice_number}">
    <label>Text 2. Mahnung</label>
    <?php $wysField('dunning_body_3', (string)($settings['dunning_body_3'] ?? ''), 'Standardtext wird verwendet, wenn leer'); ?>

    <div style="margin-top:14px">
      <button class="btn" type="submit">Speichern</button>
    </div>
  </form>

  <form method="post" action="<?php echo \App\Support\App::url('/settings/smtp-test'); ?>" style="margin-top:10px">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <button class="btn inline secondary" type="submit">Test-E-Mail senden</button>
  </form>
</div>

<script>
document.querySelectorAll('.wys').forEach(function (wrap) {
  var ta = wrap.querySelector('textarea');
  var ed = wrap.querySelector('.wys-editor');
  ed.setAttribute('data-ph', wrap.getAttribute('data-placeholder') || '');

  // Alt-Bestand ohne HTML: Zeilenumbrüche als <br> darstellen
  var val = ta.value;
  if (val && val.indexOf('<') === -1) {
    var esc = document.createElement('div');
    esc.textContent = val;
    val = esc.innerHTML.replace(/\n/g, '<br>');
  }
  ed.innerHTML = val;

  function sync() { ta.value = ed.innerHTML.trim(); }

  wrap.querySelectorAll('.wys-toolbar button').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      ed.focus();
      var cmd = btn.getAttribute('data-cmd');
      if (cmd === 'createLink') {
        var url = prompt('Link-Adresse (https://... oder mailto:...):', 'https://');
        if (url) document.execCommand('createLink', false, url);
      } else {
        document.execCommand(cmd, false, null);
      }
      sync();
    });
  });

  ed.addEventListener('input', sync);
  ed.addEventListener('blur', sync);
  var form = wrap.closest('form');
  if (form) form.addEventListener('submit', sync);
});
</script>
