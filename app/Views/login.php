<div class="card" style="max-width: 520px; margin: 30px auto;">
  <h1>Login</h1>
  <form method="post" action="<?php echo \App\Support\App::url('/login'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <label>E Mail</label>
    <input name="email" type="email" required>
    <label>Passwort</label>
    <input name="password" type="password" required>
    <div style="margin-top: 14px">
      <button class="btn" type="submit">Einloggen</button>
    </div>
  </form>
</div>
