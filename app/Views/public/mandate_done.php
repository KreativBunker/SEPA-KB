<?php
use App\Support\App;
?>
<div class="card">
  <h1 style="margin-top:0;">Danke</h1>
  <p>Das Mandat wurde gespeichert.</p>

  <?php if (!empty($item['pdf_path'])): ?>
    <div class="actions" style="margin-top: 12px;">
      <a class="btn" href="<?php echo App::url('/m/' . (string)$item['token'] . '/pdf'); ?>">PDF herunterladen</a>
    </div>
  <?php endif; ?>

  <p class="muted" style="margin-top: 12px;">
    Du kannst dieses Fenster jetzt schließen.
  </p>
</div>
