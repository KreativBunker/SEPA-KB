<?php
use App\Support\App;
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<div class="card">
  <div class="topbar">
    <h1>Neuer Vertrag</h1>
    <a href="<?php echo App::url('/contracts'); ?>" class="btn secondary">Zurück</a>
  </div>

  <form method="post" id="contract-form" action="<?php echo App::url('/contracts'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
    <input type="hidden" name="body" id="body_input" value="">

    <label>Vorlage</label>
    <select name="template_id" id="template_select">
      <option value="">-- Keine Vorlage (Freitext) --</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?php echo (int)$t['id']; ?>"
          data-title="<?php echo htmlspecialchars((string)($t['title'] ?? '')); ?>"
          data-body="<?php echo htmlspecialchars((string)($t['body'] ?? '')); ?>"
          data-sepa="<?php echo (int)($t['include_sepa'] ?? 0); ?>">
          <?php echo htmlspecialchars((string)($t['title'] ?? '')); ?>
          <?php if ((int)($t['include_sepa'] ?? 0)): ?>(inkl. SEPA)<?php endif; ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Titel</label>
    <input type="text" name="title" id="contract_title" required placeholder="Vertragstitel">

    <label>Vertragstext</label>
    <div id="editor-container" style="min-height:220px; background:#fff; border:1px solid #d8dde6; border-radius:0 0 10px 10px;"></div>
    <p class="muted">Platzhalter Mandant: <code>{{mandant_name}}</code>, <code>{{mandant_strasse}}</code>, <code>{{mandant_plz}}</code>, <code>{{mandant_ort}}</code>, <code>{{mandant_land}}</code><br>
    Platzhalter Firma: <code>{{firma}}</code>, <code>{{firma_strasse}}</code>, <code>{{firma_plz}}</code>, <code>{{firma_ort}}</code>, <code>{{firma_land}}</code>, <code>{{firma_iban}}</code>, <code>{{firma_bic}}</code>, <code>{{glaeubiger_id}}</code><br>
    Allgemein: <code>{{datum}}</code></p>

    <div style="margin-top:12px;">
      <label style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" name="include_sepa" id="include_sepa_cb" value="1" style="width:auto;">
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
        <option value="<?php echo (int)($c['id'] ?? 0); ?>">
          <?php echo htmlspecialchars((string)($c['name'] ?? '')); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <div class="row" style="margin-top:8px;">
      <div>
        <label>Kontaktname</label>
        <input type="text" name="contact_name" placeholder="Name des Vertragspartners">
      </div>
      <div>
        <label>E-Mail (optional)</label>
        <input type="email" name="contact_email" placeholder="email@example.com">
      </div>
    </div>

    <div class="actions" style="margin-top: 14px;">
      <button class="btn" type="submit">Vertrag erstellen &amp; Link generieren</button>
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

// Template selection loads content into Quill
var tplSelect = document.getElementById('template_select');
if (tplSelect) {
  tplSelect.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (opt && opt.value) {
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

// Sync editor content to hidden input on submit
document.getElementById('contract-form').addEventListener('submit', function(e) {
  var html = quill.root.innerHTML;
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
</style>
