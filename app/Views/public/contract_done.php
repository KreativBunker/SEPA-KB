<?php
use App\Support\App;
?>
<div class="card" style="text-align:center; padding: 40px 20px;">
  <h1 style="color:#166534;">Vertrag unterschrieben</h1>
  <p>Vielen Dank! Der Vertrag <strong><?php echo htmlspecialchars((string)($item['title'] ?? '')); ?></strong> wurde erfolgreich unterschrieben.</p>
  <p class="muted">Sie koennen das unterschriebene Dokument als PDF herunterladen:</p>
  <div class="actions" style="justify-content:center; margin-top:14px;">
    <a href="<?php echo App::url('/c/' . htmlspecialchars((string)($item['token'] ?? '')) . '/pdf'); ?>" class="btn">PDF herunterladen</a>
  </div>
</div>
