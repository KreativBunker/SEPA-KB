<?php
use App\Support\App;
$isEdit = !empty($template);
$title = $isEdit ? (string)($template['title'] ?? '') : '';
$body = $isEdit ? (string)($template['body'] ?? '') : '';
$includeSepa = $isEdit ? (int)($template['include_sepa'] ?? 0) : 0;
$isActive = $isEdit ? (int)($template['is_active'] ?? 1) : 1;
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
    <p class="muted">Platzhalter Mandant: <code>{{mandant_name}}</code>, <code>{{mandant_strasse}}</code>, <code>{{mandant_plz}}</code>, <code>{{mandant_ort}}</code>, <code>{{mandant_land}}</code><br>
    Platzhalter Firma: <code>{{firma}}</code>, <code>{{firma_strasse}}</code>, <code>{{firma_plz}}</code>, <code>{{firma_ort}}</code>, <code>{{firma_land}}</code>, <code>{{firma_iban}}</code>, <code>{{firma_bic}}</code>, <code>{{glaeubiger_id}}</code><br>
    Allgemein: <code>{{datum}}</code></p>

    <div class="row" style="margin-top: 12px;">
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

// Sync editor content to hidden input on submit
document.getElementById('template-form').addEventListener('submit', function(e) {
  var html = quill.root.innerHTML;
  // Don't submit empty editor (Quill has <p><br></p> when empty)
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
</style>
