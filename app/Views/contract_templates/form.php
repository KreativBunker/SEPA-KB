<?php
use App\Support\App;
$isEdit = !empty($template);
$title = $isEdit ? (string)($template['title'] ?? '') : '';
$body = $isEdit ? (string)($template['body'] ?? '') : '';
$includeSepa = $isEdit ? (int)($template['include_sepa'] ?? 0) : 0;
$isActive = $isEdit ? (int)($template['is_active'] ?? 1) : 1;
$fields = isset($fields) && is_array($fields) ? $fields : [];
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<div class="card">
  <div class="topbar">
    <h1><?php echo $isEdit ? 'Vorlage bearbeiten' : 'Neue Vertragsvorlage'; ?></h1>
    <a href="<?php echo App::url('/contract-templates'); ?>" class="btn secondary">Zurück</a>
  </div>

  <form method="post" id="template-form" action="<?php echo $isEdit ? App::url('/contract-templates/' . (int)$template['id']) : App::url('/contract-templates'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
    <input type="hidden" name="body" id="body_input" value="">

    <label>Titel</label>
    <input type="text" name="title" required value="<?php echo htmlspecialchars($title); ?>" placeholder="z.B. Dienstleistungsvertrag">

    <label>Vertragstext</label>
    <div id="editor-container" style="min-height:280px; background:#fff; border:1px solid #d8dde6; border-radius:0 0 10px 10px;"></div>
    <textarea id="html-editor" style="display:none; width:100%; min-height:280px; font-family:monospace; font-size:13px; padding:12px; border:1px solid #d8dde6; border-radius:0 0 10px 10px; background:#f8fafc; tab-size:2; white-space:pre-wrap; box-sizing:border-box;"></textarea>
    <p class="muted">Platzhalter Mandant: <code>{{mandant_name}}</code>, <code>{{mandant_strasse}}</code>, <code>{{mandant_plz}}</code>, <code>{{mandant_ort}}</code>, <code>{{mandant_land}}</code><br>
    Platzhalter Firma: <code>{{firma}}</code>, <code>{{firma_strasse}}</code>, <code>{{firma_plz}}</code>, <code>{{firma_ort}}</code>, <code>{{firma_land}}</code>, <code>{{firma_iban}}</code>, <code>{{firma_bic}}</code>, <code>{{glaeubiger_id}}</code><br>
    Allgemein: <code>{{datum}}</code></p>

    <hr style="margin:18px 0; border:0; border-top:1px solid #e5e7eb;">
    <h2 style="margin-bottom:6px;">Variable Felder</h2>
    <p class="muted" style="margin-top:0;">Definieren Sie zusätzliche Platzhalter, die im Vertragstext mit <code>{{schluessel}}</code> verwendet werden können. Felder können entweder bei der Vertragserstellung (Admin) oder beim Unterschreiben durch den Kunden ausgefüllt werden.</p>

    <div id="fields-container" style="margin-top:8px;"></div>
    <button type="button" class="btn secondary" id="add-field-btn" style="margin-top:8px;">+ Feld hinzufügen</button>

    <div class="row" style="margin-top: 18px;">
      <div>
        <label style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" name="include_sepa" value="1" <?php echo $includeSepa ? 'checked' : ''; ?> style="width:auto;">
          SEPA-Lastschriftmandat einschliessen
        </label>
        <p class="muted">Wenn aktiviert, werden beim Unterschreiben zusätzlich IBAN und BIC abgefragt.</p>
      </div>
      <div>
        <label style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" name="is_active" value="1" <?php echo $isActive ? 'checked' : ''; ?> style="width:auto;">
          Vorlage aktiv
        </label>
        <p class="muted">Nur aktive Vorlagen können für neue Verträge verwendet werden.</p>
      </div>
    </div>

    <div class="actions" style="margin-top: 14px;">
      <button class="btn" type="submit"><?php echo $isEdit ? 'Speichern' : 'Vorlage erstellen'; ?></button>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.min.js"></script>
<script>
var existingFields = <?php echo json_encode(array_map(static function (array $f): array {
    return [
        'field_key' => (string)($f['field_key'] ?? ''),
        'label' => (string)($f['label'] ?? ''),
        'field_type' => (string)($f['field_type'] ?? 'text'),
        'fill_by' => (string)($f['fill_by'] ?? 'admin'),
        'required' => (int)($f['required'] ?? 0) === 1,
        'default_value' => (string)($f['default_value'] ?? ''),
    ];
}, $fields), JSON_HEX_TAG | JSON_HEX_AMP); ?>;

(function() {
  var container = document.getElementById('fields-container');
  var addBtn = document.getElementById('add-field-btn');

  function sanitizeKey(v) {
    return (v || '').toString().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').substring(0, 64);
  }

  function escAttr(v) {
    return (v || '').toString()
      .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
      .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function rowTemplate(idx, f) {
    f = f || {};
    var typeOpts = ['text','textarea','number','date','email'].map(function(t) {
      var sel = (f.field_type || 'text') === t ? ' selected' : '';
      return '<option value="' + t + '"' + sel + '>' + t + '</option>';
    }).join('');
    var fillOpts = [
      ['admin','Admin (bei Vertragserstellung)'],
      ['customer','Kunde (beim Unterschreiben)']
    ].map(function(o) {
      var sel = (f.fill_by || 'admin') === o[0] ? ' selected' : '';
      return '<option value="' + o[0] + '"' + sel + '>' + o[1] + '</option>';
    }).join('');
    var req = f.required ? ' checked' : '';
    return '<div class="field-row" data-idx="' + idx + '" style="border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin-bottom:10px; background:#f9fafb;">'
      + '<div class="row" style="gap:10px;">'
      + '  <div style="flex:1;"><label style="font-size:12px;">Schlüssel (Platzhalter)</label>'
      + '    <input type="text" name="field_keys[]" value="' + escAttr(f.field_key) + '" placeholder="z.B. vertragslaufzeit" required>'
      + '    <div class="muted" style="font-size:12px; margin-top:4px;">Im Text: <code>{{<span class="key-preview">' + escAttr(f.field_key || 'schluessel') + '</span>}}</code></div>'
      + '  </div>'
      + '  <div style="flex:1;"><label style="font-size:12px;">Anzeige-Label</label>'
      + '    <input type="text" name="field_labels[]" value="' + escAttr(f.label) + '" placeholder="z.B. Vertragslaufzeit (Monate)" required>'
      + '  </div>'
      + '</div>'
      + '<div class="row" style="gap:10px; margin-top:8px;">'
      + '  <div style="flex:1;"><label style="font-size:12px;">Feldtyp</label>'
      + '    <select name="field_types[]">' + typeOpts + '</select>'
      + '  </div>'
      + '  <div style="flex:2;"><label style="font-size:12px;">Auszufüllen von</label>'
      + '    <select name="field_fill_by[]">' + fillOpts + '</select>'
      + '  </div>'
      + '  <div style="flex:1;"><label style="font-size:12px;">Standardwert (optional)</label>'
      + '    <input type="text" name="field_defaults[]" value="' + escAttr(f.default_value) + '">'
      + '  </div>'
      + '</div>'
      + '<div style="display:flex; align-items:center; justify-content:space-between; margin-top:10px;">'
      + '  <label style="display:flex; align-items:center; gap:6px; font-size:13px;">'
      + '    <input type="hidden" name="field_required[]" value="0">'
      + '    <input type="checkbox" class="req-cb" value="1"' + req + ' style="width:auto;"> Pflichtfeld'
      + '  </label>'
      + '  <button type="button" class="btn danger inline remove-field-btn">Entfernen</button>'
      + '</div>'
      + '</div>';
  }

  function bindRow(row) {
    var keyInput = row.querySelector('input[name="field_keys[]"]');
    var preview = row.querySelector('.key-preview');
    keyInput.addEventListener('input', function() {
      var sanitized = sanitizeKey(keyInput.value);
      preview.textContent = sanitized || 'schluessel';
    });
    keyInput.addEventListener('blur', function() {
      keyInput.value = sanitizeKey(keyInput.value);
      preview.textContent = keyInput.value || 'schluessel';
    });
    var reqCb = row.querySelector('.req-cb');
    var reqHidden = row.querySelector('input[name="field_required[]"]');
    reqCb.addEventListener('change', function() {
      reqHidden.value = reqCb.checked ? '1' : '0';
    });
    reqHidden.value = reqCb.checked ? '1' : '0';
    row.querySelector('.remove-field-btn').addEventListener('click', function() {
      row.remove();
    });
  }

  function addRow(f) {
    var idx = container.children.length;
    var wrap = document.createElement('div');
    wrap.innerHTML = rowTemplate(idx, f);
    var row = wrap.firstChild;
    container.appendChild(row);
    bindRow(row);
  }

  if (Array.isArray(existingFields) && existingFields.length > 0) {
    existingFields.forEach(function(f) { addRow(f); });
  }

  addBtn.addEventListener('click', function() { addRow(); });
})();

var quill = new Quill('#editor-container', {
  theme: 'snow',
  placeholder: 'Vertragstext hier eingeben...',
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

// Load existing body content
var existingBody = <?php echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
if (existingBody) {
  // Check if content is HTML or plain text
  if (existingBody.indexOf('<') !== -1) {
    quill.root.innerHTML = existingBody;
  } else {
    // Plain text: convert newlines to paragraphs
    quill.setText(existingBody);
  }
}

// HTML toggle
var htmlMode = false;
var htmlEditor = document.getElementById('html-editor');
var editorContainer = document.getElementById('editor-container');

// Add HTML toggle button to toolbar
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

// Sync editor content to hidden input on submit
document.getElementById('template-form').addEventListener('submit', function(e) {
  var html = htmlMode ? htmlEditor.value : quill.root.innerHTML;
  // Sync back so getText works
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
.ql-editor { min-height: 250px; }
.ql-html-toggle { font-family: monospace !important; font-size: 13px !important; font-weight: 700 !important; padding: 2px 6px !important; }
.ql-html-toggle.ql-active { color: #06c !important; background: #e8f0fe !important; border-radius: 3px; }
</style>
