<?php
use App\Support\App;
use App\Support\Flash;

$old = $old ?? [];
$includeSepa = (int)($item['include_sepa'] ?? 0);
?>
<div class="card">
  <h1 style="margin-top:0;"><?php echo htmlspecialchars((string)($item['title'] ?? 'Vertrag')); ?></h1>
  <p class="muted">
    Bitte lesen Sie den Vertrag, füllen Sie Ihre Daten aus und unterschreiben Sie.
  </p>

  <?php if (!empty($settings['creditor_name'])): ?>
  <div class="card" style="background:#f9fafb;">
    <strong>Vertragspartner</strong><br>
    <?php echo htmlspecialchars((string)($settings['creditor_name'] ?? '')); ?><br>
    <?php if (!empty($settings['creditor_street'])): ?>
      <?php echo htmlspecialchars((string)($settings['creditor_street'] ?? '')); ?><br>
    <?php endif; ?>
    <?php
      $creditorCityLine = trim((string)($settings['creditor_zip'] ?? '') . ' ' . (string)($settings['creditor_city'] ?? ''));
    ?>
    <?php if ($creditorCityLine !== ''): ?>
      <?php echo htmlspecialchars($creditorCityLine); ?><br>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div style="margin: 14px 0; padding: 14px; background:#f9fafb; border-radius:10px; border:1px solid #e5e7eb; font-size:14px; max-height:400px; overflow-y:auto; line-height:1.6;">
<?php echo strip_tags((string)($item['body'] ?? ''), '<b><i><u><strong><em><h1><h2><h3><p><br><ul><ol><li><a>'); ?>
  </div>

  <form method="post" action="" onsubmit="return submitSignature();">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string)$csrf); ?>">
    <input type="hidden" name="signature_data" id="signature_data" value="">

    <h2 style="margin-top:16px;">Ihre Daten</h2>
    <div class="grid" style="margin-top: 8px;">
      <div>
        <label>Vollständiger Name</label>
        <input type="text" name="signer_name" required value="<?php echo htmlspecialchars((string)($old['signer_name'] ?? '')); ?>">
      </div>
      <div>
        <label>Strasse und Hausnummer</label>
        <input type="text" name="signer_street" required value="<?php echo htmlspecialchars((string)($old['signer_street'] ?? '')); ?>">
      </div>
      <div>
        <label>PLZ</label>
        <input type="text" name="signer_zip" required value="<?php echo htmlspecialchars((string)($old['signer_zip'] ?? '')); ?>">
      </div>
      <div>
        <label>Ort</label>
        <input type="text" name="signer_city" required value="<?php echo htmlspecialchars((string)($old['signer_city'] ?? '')); ?>">
      </div>
      <div>
        <label>Land</label>
        <input type="text" name="signer_country" value="<?php echo htmlspecialchars((string)($old['signer_country'] ?? 'DE')); ?>" maxlength="2">
      </div>
    </div>

    <?php if ($includeSepa): ?>
    <h2 style="margin-top:18px;">SEPA-Lastschriftmandat</h2>
    <p class="muted">Ich ermächtige den Vertragspartner, Zahlungen von meinem Konto mittels SEPA-Lastschrift einzuziehen.</p>
    <?php if (!empty($item['mandate_reference'])): ?>
      <p class="muted">Mandatsreferenz: <?php echo htmlspecialchars((string)$item['mandate_reference']); ?></p>
    <?php endif; ?>
    <div class="grid" style="margin-top: 8px;">
      <div>
        <label>IBAN</label>
        <input type="text" id="iban" name="debtor_iban" required placeholder="DE92 3202 ..." value="<?php echo htmlspecialchars((string)($old['debtor_iban'] ?? '')); ?>">
      </div>
      <div>
        <label>BIC (optional)</label>
        <input type="text" name="debtor_bic" value="<?php echo htmlspecialchars((string)($old['debtor_bic'] ?? '')); ?>">
      </div>
      <div>
        <label>Zahlungsart</label>
        <?php $paymentType = (string)($old['payment_type'] ?? 'RCUR'); ?>
        <div style="display:flex; gap: 12px; margin-top: 6px; flex-wrap: wrap;">
          <label style="display:flex; align-items:center; gap: 6px;">
            <input type="radio" name="payment_type" value="RCUR" <?php echo $paymentType === 'RCUR' ? 'checked' : ''; ?> required>
            wiederkehrend
          </label>
          <label style="display:flex; align-items:center; gap: 6px;">
            <input type="radio" name="payment_type" value="OOFF" <?php echo $paymentType === 'OOFF' ? 'checked' : ''; ?> required>
            einmalig
          </label>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <h2 style="margin-top:18px;">Unterschrift</h2>
    <div class="grid">
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
      <p class="muted">Tipp: Am Handy den Finger nutzen, am Desktop die Maus.</p>
    </div>

    <div class="actions" style="margin-top: 14px;">
      <button class="btn" type="submit">Vertrag unterschreiben und absenden</button>
    </div>
  </form>
</div>

<script>
var canvas = document.getElementById('sig');
var ctx = canvas.getContext('2d');
ctx.lineWidth = 2;
ctx.lineCap = 'round';
ctx.strokeStyle = '#111827';
ctx.fillStyle = '#ffffff';
ctx.fillRect(0, 0, canvas.width, canvas.height);

var drawing = false;
var hasStroke = false;

function getPos(e) {
  var rect = canvas.getBoundingClientRect();
  var touch = e.touches && e.touches[0];
  var clientX = touch ? touch.clientX : e.clientX;
  var clientY = touch ? touch.clientY : e.clientY;
  return { x: (clientX - rect.left) * (canvas.width / rect.width), y: (clientY - rect.top) * (canvas.height / rect.height) };
}

function start(e) {
  drawing = true;
  hasStroke = true;
  var p = getPos(e);
  ctx.beginPath();
  ctx.moveTo(p.x, p.y);
  e.preventDefault();
}

function move(e) {
  if (!drawing) return;
  var p = getPos(e);
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
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  hasStroke = false;
}

function submitSignature() {
  if (!hasStroke) {
    alert('Bitte unterschreiben.');
    return false;
  }
  var tmp = document.createElement('canvas');
  tmp.width = canvas.width;
  tmp.height = canvas.height;
  var tctx = tmp.getContext('2d');
  tctx.fillStyle = '#ffffff';
  tctx.fillRect(0, 0, tmp.width, tmp.height);
  tctx.drawImage(canvas, 0, 0);

  var dataUrl = tmp.toDataURL('image/jpeg', 0.92);
  if (!dataUrl.startsWith('data:image/jpeg;base64,')) {
    dataUrl = tmp.toDataURL('image/png');
  }
  if (!dataUrl.startsWith('data:image/jpeg;base64,') && !dataUrl.startsWith('data:image/png;base64,')) {
    alert('Die Unterschrift konnte nicht verarbeitet werden. Bitte nutzen Sie einen anderen Browser.');
    return false;
  }
  document.getElementById('signature_data').value = dataUrl;
  return true;
}

<?php if ($includeSepa): ?>
function formatIbanValue(v) {
  if (!v) return '';
  var s = v.toString().toUpperCase().replace(/[^A-Z0-9]/g, '');
  return s.replace(/(.{4})/g, '$1 ').trim();
}

function formatIbanInput(input) {
  var selectionStart = input.selectionStart || input.value.length;
  var beforeCursor = input.value.slice(0, selectionStart);
  var cleanedBefore = beforeCursor.toUpperCase().replace(/[^A-Z0-9]/g, '');
  var cleanedAll = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  var formatted = formatIbanValue(cleanedAll);
  var newCursor = formatIbanValue(cleanedBefore).length;
  input.value = formatted;
  if (input === document.activeElement) {
    input.setSelectionRange(newCursor, newCursor);
  }
}

var ibanInput = document.getElementById('iban');
if (ibanInput) {
  ibanInput.addEventListener('input', function() { formatIbanInput(ibanInput); });
  ibanInput.addEventListener('blur', function() { ibanInput.value = formatIbanValue(ibanInput.value); });
  ibanInput.value = formatIbanValue(ibanInput.value);
}
<?php endif; ?>
</script>
