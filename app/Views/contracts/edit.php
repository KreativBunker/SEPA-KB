<?php
use App\Support\App;
$item = $item ?? [];
$selectedTemplateId = (int)($item['template_id'] ?? 0);
$selectedContactId = (int)($item['sevdesk_contact_id'] ?? 0);
$includeSepa = (int)($item['include_sepa'] ?? 0);
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<div class="card">
  <div class="topbar">
    <h1>Vertrag bearbeiten</h1>
    <a href="<?php echo App::url('/contracts/' . (int)$item['id']); ?>" class="btn secondary">Zurück</a>
  </div>

  <form method="post" id="contract-form" action="<?php echo App::url('/contracts/' . (int)$item['id']); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
    <input type="hidden" name="body" id="body_input" value="">

    <label>Vorlage</label>
    <select name="template_id" id="template_select">
      <option value="">-- Keine Vorlage (Freitext) --</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?php echo (int)$t['id']; ?>"
          data-title="<?php echo htmlspecialchars((string)($t['title'] ?? '')); ?>"
          data-body="<?php echo htmlspecialchars((string)($t['body'] ?? '')); ?>"
          data-sepa="<?php echo (int)($t['include_sepa'] ?? 0); ?>"
          <?php echo $selectedTemplateId === (int)$t['id'] ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars((string)($t['title'] ?? '')); ?>
          <?php if ((int)($t['include_sepa'] ?? 0)): ?>(inkl. SEPA)<?php endif; ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Titel</label>
    <input type="text" name="title" id="contract_title" required placeholder="Vertragstitel" value="<?php echo htmlspecialchars((string)($item['title'] ?? '')); ?>">

    <label>Vertragstext</label>
    <div id="editor-container" style="min-height:220px; background:#fff; border:1px solid #d8dde6; border-radius:0 0 10px 10px;"></div>
    <textarea id="html-editor" style="display:none; width:100%; min-height:220px; font-family:monospace; font-size:13px; padding:12px; border:1px solid #d8dde6; border-radius:0 0 10px 10px; background:#f8fafc; tab-size:2; white-space:pre-wrap; box-sizing:border-box;"></textarea>
    <p class="muted">Platzhalter Mandant: <code>{{mandant_name}}</code>, <code>{{mandant_strasse}}</code>, <code>{{mandant_plz}}</code>, <code>{{mandant_ort}}</code>, <code>{{mandant_land}}</code><br>
    Platzhalter Firma: <code>{{firma}}</code>, <code>{{firma_strasse}}</code>, <code>{{firma_plz}}</code>, <code>{{firma_ort}}</code>, <code>{{firma_land}}</code>, <code>{{firma_iban}}</code>, <code>{{firma_bic}}</code>, <code>{{glaeubiger_id}}</code><br>
    Allgemein: <code>{{datum}}</code></p>

    <div style="margin-top:12px;">
      <label style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" name="include_sepa" id="include_sepa_cb" value="1" style="width:auto;" <?php echo $includeSepa ? 'checked' : ''; ?>>
        SEPA-Lastschriftmandat einschliessen
      </label>
    </div>

    <hr style="margin:16px 0; border:0; border-top:1px solid #e5e7eb;">

    <h2>Kontakt</h2>
    <?php if (!empty($contacts)): ?>
    <label>sevdesk Kontakt (optional)</label>
    <select name="sevdesk_contact_id" id="contact_select">
      <option value="">-- Kein sevdesk Kontakt --</option>
      <?php foreach ($contacts as $c): ?>
        <option value="<?php echo (int)($c['id'] ?? 0); ?>" <?php echo $selectedContactId === (int)($c['id'] ?? 0) ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars((string)($c['name'] ?? '')); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <div class="row" style="margin-top:8px;">
      <div>
        <label>Kontaktname</label>
        <input type="text" name="contact_name" id="contact_name" placeholder="Name des Vertragspartners" value="<?php echo htmlspecialchars((string)($item['contact_name'] ?? '')); ?>">
      </div>
      <div>
        <label>E-Mail (optional)</label>
        <input type="email" name="contact_email" id="contact_email" placeholder="email@example.com" value="<?php echo htmlspecialchars((string)($item['contact_email'] ?? '')); ?>">
      </div>
    </div>

    <hr style="margin:16px 0; border:0; border-top:1px solid #e5e7eb;">

    <h2>Vertragspartner (Mandant)</h2>
    <p class="muted">Diese Daten werden im Vertrag als Platzhalter eingesetzt und im Signierformular vorausgefüllt.</p>
    <div class="row" style="margin-top:8px;">
      <div>
        <label>Vollständiger Name</label>
        <input type="text" name="signer_name" id="signer_name" placeholder="Max Mustermann" value="<?php echo htmlspecialchars((string)($item['signer_name'] ?? '')); ?>">
      </div>
      <div>
        <label>Strasse und Hausnummer</label>
        <input type="text" name="signer_street" id="signer_street" placeholder="Musterstrasse 1" value="<?php echo htmlspecialchars((string)($item['signer_street'] ?? '')); ?>">
      </div>
    </div>
    <div class="row" style="margin-top:8px;">
      <div>
        <label>PLZ</label>
        <input type="text" name="signer_zip" id="signer_zip" placeholder="12345" value="<?php echo htmlspecialchars((string)($item['signer_zip'] ?? '')); ?>">
      </div>
      <div>
        <label>Ort</label>
        <input type="text" name="signer_city" id="signer_city" placeholder="Musterstadt" value="<?php echo htmlspecialchars((string)($item['signer_city'] ?? '')); ?>">
      </div>
      <div>
        <label>Land</label>
        <input type="text" name="signer_country" id="signer_country" value="<?php echo htmlspecialchars((string)($item['signer_country'] ?? 'DE')); ?>" maxlength="2" placeholder="DE">
      </div>
    </div>

    <div class="actions" style="margin-top: 14px;">
      <button class="btn" type="submit">Änderungen speichern</button>
      <a class="btn secondary" href="<?php echo App::url('/contracts/' . (int)$item['id']); ?>">Abbrechen</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>
<script>
var quill = new Quill('#editor-container', {
  theme: 'snow',
  placeholder: 'Vertragstext eingeben oder Vorlage wählen...',
  modules: {
    toolbar: [
      [{ 'header': [1, 2, 3, false] }],
      ['bold', 'italic', 'underline'],
      [{ 'list': 'ordered' }, { 'list': 'bullet' }],
      ['link'],
      ['clean']
    ]
  }
});

// Load existing body content into Quill
var initialBody = <?php echo json_encode((string)($item['body'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
if (initialBody.trim() !== '') {
  if (initialBody.indexOf('<') !== -1) {
    quill.root.innerHTML = initialBody;
  } else {
    quill.setText(initialBody);
  }
}

// Template selection loads content into Quill
var tplSelect = document.getElementById('template_select');
if (tplSelect) {
  tplSelect.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (opt && opt.value) {
      if (!confirm('Vorlage anwenden? Aktueller Vertragstext wird ersetzt.')) {
        return;
      }
      document.getElementById('contract_title').value = opt.dataset.title || '';
      var bodyContent = opt.dataset.body || '';
      if (bodyContent.indexOf('<') !== -1) {
        quill.root.innerHTML = bodyContent;
      } else {
        quill.setText(bodyContent);
      }
      document.getElementById('include_sepa_cb').checked = opt.dataset.sepa === '1';
    }
  });
}

// HTML toggle
var htmlMode = false;
var htmlEditor = document.getElementById('html-editor');
var editorContainer = document.getElementById('editor-container');

var toolbarEl = document.querySelector('.ql-toolbar');
if (toolbarEl) {
  var htmlBtn = document.createElement('button');
  htmlBtn.type = 'button';
  htmlBtn.innerHTML = '&lt;/&gt;';
  htmlBtn.title = 'HTML bearbeiten';
  htmlBtn.className = 'ql-html-toggle';
  htmlBtn.addEventListener('click', function() {
    htmlMode = !htmlMode;
    if (htmlMode) {
      htmlEditor.value = quill.root.innerHTML;
      editorContainer.style.display = 'none';
      htmlEditor.style.display = 'block';
      htmlBtn.classList.add('ql-active');
    } else {
      quill.root.innerHTML = htmlEditor.value;
      htmlEditor.style.display = 'none';
      editorContainer.style.display = '';
      htmlBtn.classList.remove('ql-active');
    }
  });
  var span = document.createElement('span');
  span.className = 'ql-formats';
  span.appendChild(htmlBtn);
  toolbarEl.appendChild(span);
}

// Auto-fill signer fields from sevdesk contact
var contactSelect = document.getElementById('contact_select');
if (contactSelect) {
  contactSelect.addEventListener('change', function() {
    var id = this.value;
    if (!id) return;
    fetch('<?php echo App::url('/contracts/contact/'); ?>' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.json() : null; })
      .then(function(d) {
        if (!d) return;
        if (d.name) {
          document.getElementById('contact_name').value = d.name;
          document.getElementById('signer_name').value = d.name;
        }
        if (d.email) {
          document.getElementById('contact_email').value = d.email;
        }
        if (d.street) {
          document.getElementById('signer_street').value = d.street;
        }
        if (d.zip) {
          document.getElementById('signer_zip').value = d.zip;
        }
        if (d.city) {
          document.getElementById('signer_city').value = d.city;
        }
        if (d.country) {
          document.getElementById('signer_country').value = d.country;
        }
      })
      .catch(function(e) { console.error('Kontaktdaten laden fehlgeschlagen:', e); });
  });
}

// Sync editor content to hidden input on submit
document.getElementById('contract-form').addEventListener('submit', function(e) {
  var html = htmlMode ? htmlEditor.value : quill.root.innerHTML;
  if (htmlMode) { quill.root.innerHTML = html; }
  if (quill.getText().trim() === '') {
    e.preventDefault();
    alert('Bitte Vertragstext eingeben.');
    return false;
  }
  document.getElementById('body_input').value = html;
});
</script>
<style>
.ql-toolbar.ql-snow { border-radius: 10px 10px 0 0; border-color: #d8dde6; }
.ql-container.ql-snow { border-color: #d8dde6; font-size: 15px; }
.ql-editor { min-height: 190px; }
.ql-html-toggle { font-family: monospace !important; font-size: 13px !important; font-weight: 700 !important; padding: 2px 6px !important; }
.ql-html-toggle.ql-active { color: #06c !important; background: #e8f0fe !important; border-radius: 3px; }
</style>
