<div class="card">
  <h1>sevdesk Verbindung</h1>
  <p class="muted">Base URL ist meist https://my.sevdesk.de/api/v1</p>

  <form method="post" action="<?php echo \App\Support\App::url('/sevdesk'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <label>API Token</label>
    <input name="api_token" type="password" placeholder="Token einfügen" required>

    <div class="row">
      <div>
        <label>Header Modus</label>
        <select name="header_mode">
          <option value="Authorization" <?php echo (($account['header_mode'] ?? 'Authorization') === 'Authorization') ? 'selected' : ''; ?>>Authorization</option>
          <option value="X-Authorization" <?php echo (($account['header_mode'] ?? '') === 'X-Authorization') ? 'selected' : ''; ?>>X-Authorization</option>
        </select>
      </div>
      <div>
        <label>Base URL</label>
        <input name="base_url" value="<?php echo htmlspecialchars($account['base_url'] ?? 'https://my.sevdesk.de/api/v1'); ?>" required>
      </div>
    </div>

    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit">Speichern</button>
    </div>
  </form>

  <form method="post" action="<?php echo \App\Support\App::url('/sevdesk/test'); ?>" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <button class="btn secondary" type="submit">Verbindung testen</button>
  </form>
</div>
