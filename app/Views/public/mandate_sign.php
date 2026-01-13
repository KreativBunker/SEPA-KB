<?php
use App\Support\App;
use App\Support\Flash;

$old = $old ?? [];
?>
<div class="card">
  <h1 style="margin-top:0;">SEPA Mandat online ausfüllen</h1>
  <p class="muted">
    Bitte fülle die Daten aus und unterschreibe. Danach wird automatisch ein PDF erstellt.
  </p>

  <div class="card" style="background:#f9fafb;">
    <strong>Gläubiger</strong><br>
    <?php echo htmlspecialchars((string)($settings['creditor_name'] ?? '')); ?><br>
    <span class="muted">Gläubiger ID: <?php echo htmlspecialchars((string)($settings['creditor_id'] ?? '')); ?></span><br>
    <span class="muted">Mandatsreferenz: <?php echo htmlspecialchars((string)($item['mandate_reference'] ?? '')); ?></span>
  </div>

  <form method="post" action="" onsubmit="return submitSignature();">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
    <input type="hidden" name="signature_data" id="signature_data" value="">

    <div class="grid" style="margin-top: 12px;">
      <div>
        <label>Name Kontoinhaber</label>
        <input type="text" name="debtor_name" required value="<?php echo htmlspecialchars((string)($old['debtor_name'] ?? '')); ?>">
      </div>
      <div>
        <label>Straße und Hausnummer</label>
        <input type="text" name="debtor_street" required value="<?php echo htmlspecialchars((string)($old['debtor_street'] ?? '')); ?>">
      </div>
      <div>
        <label>PLZ</label>
        <input type="text" name="debtor_zip" required value="<?php echo htmlspecialchars((string)($old['debtor_zip'] ?? '')); ?>">
      </div>
      <div>
        <label>Ort</label>
        <input type="text" name="debtor_city" required value="<?php echo htmlspecialchars((string)($old['debtor_city'] ?? '')); ?>">
      </div>
      <div>
        <label>Land</label>
        <input type="text" name="debtor_country" value="<?php echo htmlspecialchars((string)($old['debtor_country'] ?? 'DE')); ?>" maxlength="2">
      </div>
      <div>
        <label>IBAN</label>
        <input type="text" id="iban" name="debtor_iban" required placeholder="DE92 3202 ..." value="<?php echo htmlspecialchars((string)($old['debtor_iban'] ?? '')); ?>">
      </div>
      <div>
        <label>BIC optional</label>
        <input type="text" name="debtor_bic" placeholder="" value="<?php echo htmlspecialchars((string)($old['debtor_bic'] ?? '')); ?>">
      </div>
      <div>
        <label>Ort der Unterschrift</label>
        <input type="text" name="signed_place" required value="<?php echo htmlspecialchars((string)($old['signed_place'] ?? '')); ?>">
      </div>
      <div>
        <label>Datum</label>
        <input type="date" name="signed_date" value="<?php echo htmlspecialchars((string)($old['signed_date'] ?? date('Y-m-d'))); ?>" required>
      </div>
    </div>

    <div style="margin-top: 12px;">
      <label>Unterschrift</label>
      <div style="border:1px solid #cbd5e1; border-radius: 10px; overflow:hidden; background:#fff;">
        <canvas id="sig" width="900" height="250" style="width:100%; height:200px; touch-action:none;"></canvas>
      </div>
      <div class="actions" style="margin-top: 10px;">
        <button type="button" class="btn" style="background:#6b7280;" onclick="clearSig();">Unterschrift löschen</button>
      </div>
      <p class="muted">Tipp: Wenn du am Handy bist, nutze den Finger. Am Desktop kannst du die Maus nutzen.</p>
    </div>

    <div class="actions" style="margin-top: 14px;">
      <button class="btn" type="submit">Mandat unterschreiben und absenden</button>
    </div>
  </form>
</div>

<script>
const canvas = document.getElementById('sig');
const ctx = canvas.getContext('2d');
ctx.lineWidth = 2;
ctx.lineCap = 'round';
ctx.strokeStyle = '#111827';

let drawing = false;
let hasStroke = false;

function getPos(e) {
  const rect = canvas.getBoundingClientRect();
  const touch = e.touches && e.touches[0];
  const clientX = touch ? touch.clientX : e.clientX;
  const clientY = touch ? touch.clientY : e.clientY;
  return { x: (clientX - rect.left) * (canvas.width / rect.width), y: (clientY - rect.top) * (canvas.height / rect.height) };
}

function start(e) {
  drawing = true;
  hasStroke = true;
  const p = getPos(e);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
  e.preventDefault();
}

function move(e) {
  if (!drawing) return;
  const p = getPos(e);
  ctx.lineTo(p.x, p.y);
  ctx.stroke();
  e.preventDefault();
}

function end(e) {
  drawing = false;
  e.preventDefault();
}

canvas.addEventListener('mousedown', start);
canvas.addEventListener('mousemove', move);
canvas.addEventListener('mouseup', end);
canvas.addEventListener('mouseleave', end);

canvas.addEventListener('touchstart', start, {passive:false});
canvas.addEventListener('touchmove', move, {passive:false});
canvas.addEventListener('touchend', end, {passive:false});
canvas.addEventListener('touchcancel', end, {passive:false});

function clearSig() {
  ctx.clearRect(0,0,canvas.width,canvas.height);
  hasStroke = false;
}

function submitSignature() {
  if (!hasStroke) {
    alert('Bitte unterschreiben.');
    return false;
  }

  // Try to get JPEG data (preferred, no server-side GD needed)
  let dataUrl = canvas.toDataURL('image/jpeg', 0.92);

  // Some browsers may fallback to PNG. In that case, try again with a white background.
  if (!dataUrl.startsWith('data:image/jpeg;base64,')) {
    const tmp = document.createElement('canvas');
    tmp.width = canvas.width;
    tmp.height = canvas.height;
    const tctx = tmp.getContext('2d');
    tctx.fillStyle = '#ffffff';
    tctx.fillRect(0, 0, tmp.width, tmp.height);
    tctx.drawImage(canvas, 0, 0);

    dataUrl = tmp.toDataURL('image/jpeg', 0.92);
  }

  if (!dataUrl.startsWith('data:image/jpeg;base64,') && !dataUrl.startsWith('data:image/png;base64,')) {
    alert('Die Unterschrift konnte nicht verarbeitet werden. Bitte nutzen Sie einen anderen Browser.');
    return false;
  }

  document.getElementById('signature_data').value = dataUrl;
  return true;
}

function formatIbanValue(v) {
  if (!v) return '';
  let s = v.toString().toUpperCase().replace(/[^A-Z0-9]/g, '');
  // Group in 4s: DE92 3202 ...
  return s.replace(/(.{4})/g, '$1 ').trim();
}

const ibanInput = document.getElementById('iban');
if (ibanInput) {
  ibanInput.addEventListener('input', function() {
    const old = ibanInput.value;
    const formatted = formatIbanValue(old);
    ibanInput.value = formatted;
  });
  ibanInput.addEventListener('blur', function() {
    ibanInput.value = formatIbanValue(ibanInput.value);
  });

  // Initial formatting if value is prefilled
  ibanInput.value = formatIbanValue(ibanInput.value);
}

</script>
