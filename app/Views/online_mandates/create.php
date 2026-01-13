<?php
use App\Support\App;
?>
<div class="card">
  <h1 style="margin-top:0;">Online Mandat Link erstellen</h1>
  <p class="muted">
    Du wählst einen sevdesk Kontakt aus. Der Kunde füllt danach seine Bankdaten aus und unterschreibt. Danach wird automatisch ein PDF erzeugt.
  </p>

  <?php if (empty($contacts ?? [])): ?>
    <div class="flash info">
      Hinweis: Es sind noch keine sevdesk Kontakte im Cache. Lade sie bitte einmal im Mandate Bereich über Import sevdesk.
    </div>
  <?php endif; ?>

  <form method="post" action="<?php echo App::url('/online-mandates'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">

    <label>sevdesk Kontakt</label>
    <select id="sevdesk_contact_id" name="sevdesk_contact_id" required>
      <option value="">Bitte wählen</option>
      <?php foreach (($contacts ?? []) as $c): ?>
        <option value="<?php echo (int)($c['id'] ?? 0); ?>" data-email="<?php echo htmlspecialchars((string)($c['email'] ?? '')); ?>">
          <?php echo htmlspecialchars((string)($c['name'] ?? '')); ?> (<?php echo (int)($c['id'] ?? 0); ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <label>E Mail optional</label>
    <input id="debtor_email" type="email" name="debtor_email" placeholder="kunde@example.de">

    <div class="actions" style="margin-top: 12px;">
      <button class="btn" type="submit">Link erstellen</button>
      <a class="btn" style="background:#6b7280;" href="<?php echo App::url('/online-mandates'); ?>">Abbrechen</a>
    </div>
  </form>
</div>

<script>
(function(){
  const sel = document.getElementById('sevdesk_contact_id');
  const email = document.getElementById('debtor_email');
  if (!sel || !email) return;

  function prefillEmail(){
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    const em = opt.getAttribute('data-email') || '';
    if (em && email.value.trim() === '') {
      email.value = em;
      return;
    }

    const id = sel.value;
    if (!id) return;

    fetch('<?php echo App::url('/online-mandates/contact'); ?>/' + encodeURIComponent(id), {credentials:'same-origin'})
      .then(r => r.ok ? r.json() : null)
      .then(d => {
        if (!d || !d.email) return;
        if (email.value.trim() === '') {
          email.value = d.email;
        }
      })
      .catch(() => {});
  }

  sel.addEventListener('change', prefillEmail);
  prefillEmail();
})();
</script>
