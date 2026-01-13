<div class="card" style="max-width: 620px">
  <h1>Nutzer neu</h1>

  <form method="post" action="<?php echo \App\Support\App::url('/users'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">

    <label>Email</label>
    <input name="email" type="email" required>

    <label>Passwort</label>
    <input name="password" type="password" required>

    <label>Rolle</label>
    <select name="role">
      <option value="staff" selected>staff</option>
      <option value="admin">admin</option>
      <option value="viewer">viewer</option>
    </select>

    <div class="actions" style="margin-top:14px">
      <button class="btn" type="submit">Erstellen</button>
      <a class="btn secondary" href="<?php echo \App\Support\App::url('/users'); ?>">Zurück</a>
    </div>
  </form>
</div>
