<div class="card">
  <div class="topbar">
    <h1>Nutzer</h1>
    <a class="btn inline" href="<?php echo \App\Support\App::url('/users/create'); ?>">Neu</a>
  </div>
</div>

<div class="card">
  <h2>Liste</h2>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Rolle</th>
        <th>Letzter Login</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?php echo (int)$u['id']; ?></td>
          <td><?php echo htmlspecialchars($u['email']); ?></td>
          <td><?php echo htmlspecialchars($u['role']); ?></td>
          <td class="muted"><?php echo htmlspecialchars((string)($u['last_login_at'] ?? '')); ?></td>
          <td>
            <form method="post" action="<?php echo \App\Support\App::url('/users/' . (int)$u['id'] . '/reset-password'); ?>" style="display:inline-block">
              <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input name="new_password" placeholder="Neues Passwort" style="width:180px; display:inline-block">
              <button class="btn inline secondary" type="submit">Setzen</button>
            </form>
            <form method="post" action="<?php echo \App\Support\App::url('/users/' . (int)$u['id'] . '/delete'); ?>" style="display:inline-block">
              <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <button class="btn inline danger" type="submit" onclick="return confirm('Nutzer löschen?');">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
        <tr><td colspan="5" class="muted">Keine Nutzer</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
