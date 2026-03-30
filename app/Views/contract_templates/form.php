<?php
use App\Support\App;
$isEdit = !empty($template);
$title = $isEdit ? (string)($template['title'] ?? '') : '';
$body = $isEdit ? (string)($template['body'] ?? '') : '';
$includeSepa = $isEdit ? (int)($template['include_sepa'] ?? 0) : 0;
$isActive = $isEdit ? (int)($template['is_active'] ?? 1) : 1;
?>
<div class="card">
  <div class="topbar">
    <h1><?php echo $isEdit ? 'Vorlage bearbeiten' : 'Neue Vertragsvorlage'; ?></h1>
    <a href="<?php echo App::url('/contract-templates'); ?>" class="btn secondary">Zurueck</a>
  </div>

  <form method="post" action="<?php echo $isEdit ? App::url('/contract-templates/' . (int)$template['id']) : App::url('/contract-templates'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">

    <label>Titel</label>
    <input type="text" name="title" required value="<?php echo htmlspecialchars($title); ?>" placeholder="z.B. Dienstleistungsvertrag">

    <label>Vertragstext</label>
    <textarea name="body" required rows="14" style="min-height:220px;" placeholder="Vertragstext hier eingeben. Platzhalter: {{name}}, {{strasse}}, {{plz}}, {{ort}}, {{land}}, {{datum}}, {{firma}}"><?php echo htmlspecialchars($body); ?></textarea>
    <p class="muted">Verfuegbare Platzhalter: <code>{{name}}</code>, <code>{{strasse}}</code>, <code>{{plz}}</code>, <code>{{ort}}</code>, <code>{{land}}</code>, <code>{{datum}}</code>, <code>{{firma}}</code></p>

    <div class="row" style="margin-top: 12px;">
      <div>
        <label style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" name="include_sepa" value="1" <?php echo $includeSepa ? 'checked' : ''; ?> style="width:auto;">
          SEPA-Lastschriftmandat einschliessen
        </label>
        <p class="muted">Wenn aktiviert, werden beim Unterschreiben zusaetzlich IBAN und BIC abgefragt.</p>
      </div>
      <div>
        <label style="display:flex; align-items:center; gap:8px;">
          <input type="checkbox" name="is_active" value="1" <?php echo $isActive ? 'checked' : ''; ?> style="width:auto;">
          Vorlage aktiv
        </label>
        <p class="muted">Nur aktive Vorlagen koennen fuer neue Vertraege verwendet werden.</p>
      </div>
    </div>

    <div class="actions" style="margin-top: 14px;">
      <button class="btn" type="submit"><?php echo $isEdit ? 'Speichern' : 'Vorlage erstellen'; ?></button>
    </div>
  </form>
</div>
