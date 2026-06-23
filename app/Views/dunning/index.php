<div class="card">
  <div class="topbar">
    <h1>Mahnautomatik</h1>
    <form method="post" action="<?php echo \App\Support\App::url('/dunning/scan'); ?>">
      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <button class="btn inline" type="submit">Jetzt prüfen (Scan)</button>
    </form>
  </div>
  <p class="muted">Automatische Zahlungserinnerungen und Mahnungen: Der Mahnlauf prüft überfällige sevdesk-Rechnungen, erzeugt den Mahnbeleg in sevdesk und versendet ihn per E-Mail an den Kunden. Ab der 2. Mahnung erscheint die Rechnung im <a href="<?php echo \App\Support\App::url('/inkasso'); ?>">Mahnwesen</a> zur manuellen Inkasso-Übergabe.</p>

  <?php if (empty($dunningEnabled)): ?>
    <p><span class="pill warn">Hinweis</span> Die Mahnautomatik ist deaktiviert – der terminierte Cron-Lauf macht nichts. Manuelle Scans und Freigaben funktionieren trotzdem. <a href="<?php echo \App\Support\App::url('/settings'); ?>">Zu den Einstellungen</a></p>
  <?php endif; ?>
  <?php if (empty($mailReady)): ?>
    <p><span class="pill err">Hinweis</span> Der E-Mail-Versand ist noch nicht konfiguriert – Mahnungen können nicht versendet werden. <a href="<?php echo \App\Support\App::url('/settings'); ?>">Zu den Einstellungen</a></p>
  <?php endif; ?>
  <?php if (!empty($testMode)): ?>
    <p><span class="pill warn">Test-Modus</span> E-Mails werden nur in storage/logs/mail abgelegt, es werden keine Mahnbelege in sevdesk erzeugt. Vorschläge bleiben offen.</p>
  <?php endif; ?>
  <p class="muted">
    Modus: <?php if (($dunningMode ?? 'review') === 'auto'): ?><span class="pill primary">Vollautomatisch</span> – der Cron-Lauf versendet ohne Freigabe.<?php else: ?><span class="pill secondary">Freigabe-Modus</span> – der Cron-Lauf merkt Mahnungen nur vor, der Versand erfolgt nach Freigabe hier.<?php endif; ?>
  </p>
</div>

<div class="card">
  <h2>Aktueller Stand aus sevdesk (live)</h2>
  <p class="muted">Direkt bei jedem Aufruf aus sevdesk geladen: alle offenen, überfälligen Rechnungen mit ihrer aktuellen Mahnstufe. Bezahlte oder stornierte Rechnungen erscheinen hier nicht (mehr). „Jetzt prüfen (Scan)“ oben überführt fällige Rechnungen in die Mahnvorschläge.</p>
  <?php if (!empty($liveError)): ?>
    <p><span class="pill err">Hinweis</span> Der Live-Abruf aus sevdesk ist fehlgeschlagen: <?php echo htmlspecialchars((string)$liveError); ?></p>
  <?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nummer</th>
          <th>Kunde</th>
          <th>Fällig</th>
          <th>Tage überfällig</th>
          <th>Mahnstufe (sevdesk)</th>
          <th>Forderung</th>
          <th>Zahlungsart</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($liveOverdue ?? []) as $ov): ?>
          <tr>
            <td class="nowrap"><?php echo htmlspecialchars((string)$ov['invoiceNumber']); ?></td>
            <td><?php echo htmlspecialchars((string)$ov['contact_name']); ?></td>
            <td class="nowrap"><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($ov['dueDate'] ?? ''))); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo (int)($ov['days_overdue'] ?? 0); ?></td>
            <td class="nowrap"><?php echo (int)($ov['dunning_level'] ?? 0); ?></td>
            <td class="nowrap" style="text-align:right;"><?php echo htmlspecialchars(number_format((float)($ov['total_claim'] ?? 0), 2, ',', '.')); ?> <?php echo htmlspecialchars((string)($ov['currency'] ?? 'EUR')); ?></td>
            <td><?php echo htmlspecialchars((string)($ov['payment_method'] ?? '')); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($liveOverdue) && empty($liveError)): ?>
          <tr><td colspan="7" class="muted">Aktuell keine offenen, überfälligen Rechnungen in sevdesk.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>Storno-Prüfung einer Rechnung</h2>
  <p class="muted">Prüft rein lesend, wie eine Rechnung aktuell in sevdesk vorliegt und ob sie über eine Stornorechnung als erledigt erkannt wird. Nützlich für Rechnungen, die trotz Stornierung weiter gemahnt werden.</p>
  <form method="post" action="<?php echo \App\Support\App::url('/dunning/diagnose'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
      <div>
        <label>Rechnungsnummer oder sevdesk-ID</label>
        <input name="invoice" value="<?php echo htmlspecialchars((string)($diagnoseInput ?? '')); ?>" placeholder="z.B. RE-2026/252406" required>
      </div>
      <button class="btn inline secondary" type="submit">Prüfen</button>
    </div>
  </form>

  <?php if (!empty($diagnose) && !empty($diagnose['found'])): $inv = $diagnose['invoice']; ?>
    <div style="margin-top:14px">
      <table>
        <tbody>
          <tr><th style="text-align:left">sevdesk-ID</th><td><?php echo (int)$inv['id']; ?></td></tr>
          <tr><th style="text-align:left">Nummer</th><td><?php echo htmlspecialchars((string)$inv['invoiceNumber']); ?></td></tr>
          <tr><th style="text-align:left">Typ</th><td><?php echo htmlspecialchars((string)$inv['invoiceType']); ?></td></tr>
          <tr><th style="text-align:left">Status</th><td><?php echo htmlspecialchars((string)$inv['status']); ?> <span class="muted">(200 = offen, 1000 = bezahlt)</span></td></tr>
          <tr><th style="text-align:left">Bezahlt am</th><td><?php echo $inv['payDate'] ? htmlspecialchars((string)$inv['payDate']) : '<span class="muted">–</span>'; ?></td></tr>
          <tr><th style="text-align:left">Betrag</th><td><?php echo htmlspecialchars(number_format((float)$inv['amount'], 2, ',', '.')); ?></td></tr>
          <tr><th style="text-align:left">Fällig</th><td><?php echo htmlspecialchars((string)$inv['dueDate']); ?></td></tr>
        </tbody>
      </table>

      <p style="margin-top:12px">
        <?php if (!empty($diagnose['recognized'])): ?>
          <span class="pill ok">Als storniert erkannt</span> Diese Rechnung wird nicht (mehr) gemahnt.
        <?php else: ?>
          <span class="pill err">Nicht als storniert erkannt</span> Diese Rechnung würde weiter gemahnt.
        <?php endif; ?>
      </p>

      <?php if (!empty($diagnose['matched'])): ?>
        <p class="muted">Zugehörige Stornorechnung(en) (verweisen per <span class="mono">origin</span> auf diese Rechnung):</p>
        <ul>
          <?php foreach ($diagnose['matched'] as $m): ?>
            <li>SR-ID <?php echo (int)$m['id']; ?>, Nr. <?php echo htmlspecialchars((string)$m['invoiceNumber'] ?: '–'); ?>, Datum <?php echo htmlspecialchars((string)$m['invoiceDate'] ?: '–'); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted">Geprüfte Stornorechnungen gesamt: <?php echo (int)$diagnose['sr_total']; ?>. Keine davon verweist per <span class="mono">origin</span> auf diese Rechnung.</p>
        <?php if (!empty($diagnose['sample_sr_fields'])): ?>
          <details>
            <summary class="muted" style="cursor:pointer">Felder einer Beispiel-Stornorechnung anzeigen (zur Fehleranalyse)</summary>
            <pre style="white-space:pre-wrap; font-size:12px; margin:6px 0 0"><?php
              foreach ($diagnose['sample_sr_fields'] as $k => $v) {
                  echo htmlspecialchars((string)$k . ' = ' . (is_bool($v) ? var_export($v, true) : (string)$v)) . "\n";
              }
            ?></pre>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Offene Mahnvorschläge</h2>
  <form method="post" action="<?php echo \App\Support\App::url('/dunning/approve'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" onclick="document.querySelectorAll('.dun-check').forEach(c => c.checked = this.checked);"></th>
            <th>Nummer</th>
            <th>Kunde</th>
            <th>Fällig</th>
            <th>Nächste Stufe</th>
            <th>Forderung</th>
            <th>Empfänger</th>
            <th>Aktion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($pending ?? []) as $a): ?>
            <tr>
              <td><input class="dun-check" type="checkbox" name="action_ids[]" value="<?php echo (int)$a['id']; ?>"></td>
              <td class="nowrap"><?php echo htmlspecialchars((string)$a['invoice_number']); ?></td>
              <td><?php echo htmlspecialchars((string)$a['contact_name']); ?></td>
              <td class="nowrap"><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay((string)($a['due_date'] ?? ''))); ?></td>
              <td class="nowrap">
                <?php $stage = (int)$a['stage']; ?>
                <?php if ($stage >= 2): ?>
                  <span class="pill err"><?php echo htmlspecialchars($service->stageLabel($stage)); ?></span>
                <?php else: ?>
                  <span class="pill warn"><?php echo htmlspecialchars($service->stageLabel($stage)); ?></span>
                <?php endif; ?>
              </td>
              <td class="nowrap" style="text-align:right;"><?php echo htmlspecialchars(number_format((float)$a['amount'], 2, ',', '.')); ?> <?php echo htmlspecialchars((string)($a['currency'] ?? 'EUR')); ?></td>
              <td class="nowrap">
                <?php if (!empty($a['recipient_email'])): ?>
                  <?php echo htmlspecialchars((string)$a['recipient_email']); ?>
                <?php else: ?>
                  <span class="pill err">keine E-Mail</span>
                <?php endif; ?>
              </td>
              <td class="nowrap">
                <div class="actions">
                  <button class="btn inline secondary" type="submit" formaction="<?php echo \App\Support\App::url('/dunning/' . (int)$a['id'] . '/skip'); ?>" formnovalidate onclick="return confirm('Diesen Mahnvorschlag überspringen?');">Überspringen</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($pending)): ?>
            <tr><td colspan="8" class="muted">Keine offenen Mahnvorschläge. Klicke oben auf „Jetzt prüfen (Scan)“, um sevdesk zu prüfen.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!empty($pending)): ?>
      <div style="margin-top:12px">
        <button class="btn" type="submit" onclick="return confirm('Ausgewählte Mahnungen jetzt in sevdesk erzeugen und per E-Mail an die Kunden versenden?');" <?php echo empty($mailReady) ? 'disabled' : ''; ?>>Ausgewählte freigeben &amp; senden</button>
      </div>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Ausschlussliste</h2>
  <p class="muted">Rechnungen oder Kontakte auf dieser Liste werden vom automatischen Mahnlauf übersprungen. Rechnungen mit Zahlungsart SEPA-Lastschrift oder aktivem Mandat werden – je nach Einstellung – automatisch ausgenommen.</p>
  <form method="post" action="<?php echo \App\Support\App::url('/dunning/exclude'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <div class="row3">
      <div>
        <label>Typ</label>
        <select name="scope">
          <option value="invoice">Rechnung (sevdesk-ID)</option>
          <option value="contact">Kontakt (sevdesk-ID)</option>
        </select>
      </div>
      <div>
        <label>sevdesk-ID</label>
        <input name="sevdesk_id" placeholder="z.B. 12345" required>
      </div>
      <div>
        <label>Bezeichnung / Notiz</label>
        <input name="label" placeholder="z.B. Rechnungsnummer oder Kundenname">
      </div>
    </div>
    <div style="margin-top:10px">
      <button class="btn inline secondary" type="submit">Ausschluss hinzufügen</button>
    </div>
  </form>

  <?php if (!empty($exclusions)): ?>
    <div class="table-wrap" style="margin-top:12px">
      <table>
        <thead>
          <tr><th>Typ</th><th>sevdesk-ID</th><th>Bezeichnung</th><th>Notiz</th><th>Seit</th><th>Aktion</th></tr>
        </thead>
        <tbody>
          <?php foreach ($exclusions as $ex): ?>
            <tr>
              <td><span class="pill secondary"><?php echo $ex['scope'] === 'contact' ? 'Kontakt' : 'Rechnung'; ?></span></td>
              <td class="nowrap"><?php echo (int)$ex['sevdesk_id']; ?></td>
              <td><?php echo htmlspecialchars((string)($ex['label'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string)($ex['note'] ?? '')); ?></td>
              <td class="nowrap"><?php echo htmlspecialchars(\App\Support\DateFormatter::toDisplay(substr((string)$ex['created_at'], 0, 10))); ?></td>
              <td class="nowrap">
                <form method="post" action="<?php echo \App\Support\App::url('/dunning/exclusions/' . (int)$ex['id'] . '/delete'); ?>" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <button class="btn inline danger" type="submit" onclick="return confirm('Ausschluss entfernen? Die Rechnung/der Kontakt wird wieder automatisch gemahnt.');">Entfernen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Verlauf</h2>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Nummer</th>
          <th>Kunde</th>
          <th>Stufe</th>
          <th>Status</th>
          <th>Empfänger</th>
          <th>Zeitpunkt</th>
          <th>Aktion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($history ?? []) as $a): ?>
          <tr>
            <td class="nowrap"><?php echo htmlspecialchars((string)$a['invoice_number']); ?></td>
            <td><?php echo htmlspecialchars((string)$a['contact_name']); ?></td>
            <td class="nowrap"><?php echo htmlspecialchars($service->stageLabel((int)$a['stage'])); ?></td>
            <td class="nowrap">
              <?php $st = (string)$a['status']; ?>
              <?php if ($st === 'sent'): ?>
                <span class="pill ok">gesendet</span>
              <?php elseif ($st === 'failed'): ?>
                <span class="pill err">fehlgeschlagen</span>
              <?php else: ?>
                <span class="pill secondary">übersprungen</span>
              <?php endif; ?>
              <?php if (!empty($a['error_text'])): ?>
                <div class="muted" style="margin-top:4px; max-width:340px; white-space:normal"><?php echo htmlspecialchars((string)$a['error_text']); ?></div>
              <?php endif; ?>
            </td>
            <td class="nowrap"><?php echo htmlspecialchars((string)($a['recipient_email'] ?? '')); ?></td>
            <td class="nowrap"><?php echo htmlspecialchars((string)($a['sent_at'] ?? $a['created_at'] ?? '')); ?></td>
            <td class="nowrap">
              <?php if (in_array($st, ['failed', 'skipped'], true)): ?>
                <form method="post" action="<?php echo \App\Support\App::url('/dunning/' . (int)$a['id'] . '/retry'); ?>" style="display:inline">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <button class="btn inline secondary" type="submit">Erneut vormerken</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
          <tr><td colspan="7" class="muted">Noch keine verarbeiteten Mahnungen.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>Letzte Mahnläufe</h2>
  <?php if (!empty($cronUrl)): ?>
    <p class="muted">Terminierter Aufruf per Hosting-Cronjob: <span class="mono">php bin/dunning_cron.php</span> oder Webcron-URL: <span class="mono" style="word-break:break-all"><?php echo htmlspecialchars($cronUrl); ?></span></p>
  <?php endif; ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Start</th><th>Auslöser</th><th>Modus</th><th>Geprüft</th><th>Vorgemerkt</th><th>Gesendet</th><th>Übersprungen</th><th>Fehler</th><th>Protokoll</th></tr>
      </thead>
      <tbody>
        <?php foreach (($runs ?? []) as $run): ?>
          <tr>
            <td class="nowrap"><?php echo htmlspecialchars((string)$run['started_at']); ?></td>
            <td class="nowrap"><span class="pill secondary"><?php echo htmlspecialchars((string)$run['trigger_type']); ?></span></td>
            <td class="nowrap"><?php echo htmlspecialchars((string)$run['mode']); ?></td>
            <td class="nowrap"><?php echo (int)$run['candidates']; ?></td>
            <td class="nowrap"><?php echo (int)$run['queued']; ?></td>
            <td class="nowrap"><?php echo (int)$run['sent']; ?></td>
            <td class="nowrap"><?php echo (int)$run['skipped']; ?></td>
            <td class="nowrap"><?php echo (int)$run['errors'] > 0 ? '<span class="pill err">' . (int)$run['errors'] . '</span>' : '0'; ?></td>
            <td>
              <?php if (!empty($run['log_text'])): ?>
                <details>
                  <summary class="muted" style="cursor:pointer">anzeigen</summary>
                  <pre style="white-space:pre-wrap; font-size:12px; margin:6px 0 0"><?php echo htmlspecialchars((string)$run['log_text']); ?></pre>
                </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($runs)): ?>
          <tr><td colspan="9" class="muted">Noch keine Mahnläufe.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
