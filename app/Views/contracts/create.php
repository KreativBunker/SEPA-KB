<?php
use App\Support\App;
?>
<div class="card">
  <div class="topbar">
    <h1>Neuer Vertrag</h1>
    <a href="<?php echo App::url('/contracts'); ?>" class="btn secondary">Zurueck</a>
  </div>

  <form method="post" action="<?php echo App::url('/contracts'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">

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
    <textarea name="body" id="contract_body" required rows="10" style="min-height:180px;" placeholder="Vertragstext eingeben oder Vorlage waehlen..."></textarea>
    <p class="muted">Platzhalter: <code>{{name}}</code>, <code>{{strasse}}</code>, <code>{{plz}}</code>, <code>{{ort}}</code>, <code>{{land}}</code>, <code>{{datum}}</code>, <code>{{firma}}</code></p>

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

<script>
var tplSelect = document.getElementById('template_select');
if (tplSelect) {
  tplSelect.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (opt && opt.value) {
      document.getElementById('contract_title').value = opt.dataset.title || '';
      document.getElementById('contract_body').value = opt.dataset.body || '';
      document.getElementById('include_sepa_cb').checked = opt.dataset.sepa === '1';
    }
  });
}
</script>
