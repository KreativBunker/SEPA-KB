<?php
use App\Support\App;
use App\Support\Auth;
use App\Support\Flash;
$messages = $messages ?? Flash::all();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEPA-Lastschriftmandat</title>
    <style>
* { box-sizing: border-box; }
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f6f7fb; color: #1b1f24; }
header { background: #1D3860; color: #fff; padding: 14px 18px; }
header a { color: #fff; text-decoration: none; margin-right: 12px; font-weight: 600; }
.wrap { max-width: 1100px; margin: 18px auto; padding: 0 14px; }
.card { background: #fff; border-radius: 12px; padding: 14px; box-shadow: 0 6px 18px rgba(0,0,0,.06); margin-bottom: 14px; }
h1 { font-size: 22px; margin: 0 0 10px; }
h2 { font-size: 18px; margin: 0 0 10px; }
label { display:block; font-weight: 600; margin: 10px 0 6px; }
input, select, textarea { width: 100%; max-width: 100%; padding: 10px 11px; border: 1px solid #d8dde6; border-radius: 10px; font-size: 15px; }
textarea { min-height: 90px; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 740px) {
    .row, .row3, .grid { grid-template-columns: 1fr; }
    header a { display: inline-block; margin-bottom: 6px; }
    .nav-sep { display: none; }
    .nav-group { display: block; margin-bottom: 4px; }
    .nav-dropdown-menu { position: static; box-shadow: none; min-width: 0; display: block; padding: 0; border-radius: 0; }
    .nav-dropdown-toggle::after { display: none; }
}
.btn { background: #1D3860; color:#fff; border:0; padding: 11px 14px; border-radius: 10px; cursor:pointer; font-weight: 700; text-decoration: none; display: inline-block; }
.btn.inline { padding: 9px 12px; }
.btn.secondary { background: #6b7280; }
.btn.danger { background: #b91c1c; }
.muted { color:#6b7280; font-size: 14px; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 10px; border-bottom: 1px solid #edf0f6; vertical-align: top; font-size: 14px; }
th { background: #f3f5fb; font-size: 13px; text-transform: uppercase; letter-spacing: .03em; }
.pill { display:inline-block; padding: 4px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.pill.ok { background:#dcfce7; color:#166534; }
.pill.err { background:#fee2e2; color:#991b1b; }
.pill.warn { background:#fef9c3; color:#854d0e; }
.flash { padding: 10px 12px; border-radius: 10px; margin: 8px 0; }
.flash.success { background:#dcfce7; color:#166534; }
.flash.error { background:#fee2e2; color:#991b1b; }
.flash.info { background:#e0f2fe; color:#075985; }
.actions { display:flex; gap: 10px; flex-wrap: wrap; }
.topbar { display:flex; align-items:center; justify-content:space-between; gap: 10px; }
.nav-group { display: inline; }
.nav-sep { display: inline-block; width: 1px; height: 16px; background: rgba(255,255,255,0.3); margin: 0 8px; vertical-align: middle; }
.nav-dropdown { position: relative; display: inline-block; }
.nav-dropdown-toggle { cursor: pointer; }
.nav-dropdown-toggle::after { content: ' \25BE'; font-size: 11px; }
.nav-dropdown-menu {
  display: none; position: absolute; top: 100%; right: 0;
  background: #1D3860; border-radius: 0 0 10px 10px;
  min-width: 180px; padding: 6px 0;
  box-shadow: 0 8px 24px rgba(0,0,0,.2); z-index: 100;
}
.nav-dropdown:hover .nav-dropdown-menu,
.nav-dropdown:focus-within .nav-dropdown-menu { display: block; }
.nav-dropdown-menu a {
  display: block; padding: 8px 16px; margin: 0;
  white-space: nowrap; border-bottom: 1px solid rgba(255,255,255,0.1);
}
.nav-dropdown-menu a:last-child { border-bottom: 0; }
.nav-dropdown-menu a:hover { background: rgba(255,255,255,0.1); }


.table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table-wrap table { min-width: 980px; }
td { word-break: break-word; }
.mandates-table { min-width: 1180px; }
.mandates-table th,
.mandates-table td { padding: 12px 14px; }
.mandates-table th { white-space: nowrap; }
.mandates-table td { word-break: normal; }
.mandates-table td.iban,
.mandates-table td.bic { white-space: nowrap; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }


.iban { word-break: break-all; }

/* Dashboard */
.dash-hero { display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
.dash-hero-text h1 { margin: 0 0 4px; }
.dash-hero-text p { margin: 0; }
.dash-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px; }
.kpi { background:#fff; border-radius:12px; padding:14px; box-shadow:0 6px 18px rgba(0,0,0,.06); }
.kpi-label { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; font-weight:700; }
.kpi-value { font-size:28px; font-weight:800; color:#1D3860; margin:6px 0 4px; line-height:1.1; }
.kpi-sub { font-size:13px; }
.dash-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.dash-action { border:1px solid #edf0f6; border-radius:12px; padding:14px; background:#fafbfe; display:flex; flex-direction:column; gap:8px; }
.dash-action-head { display:flex; align-items:baseline; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.dash-action-head h3 { margin:0; font-size:16px; }
.dash-action p { margin:0; }
.dash-warnings { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
.dash-warning { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-radius:10px; }
.dash-warning.error { background:#fee2e2; color:#991b1b; }
.dash-warning.warn { background:#fef9c3; color:#854d0e; }
.dash-warning.info { background:#e0f2fe; color:#075985; }
@media (max-width: 980px) {
  .dash-kpis { grid-template-columns: repeat(2, 1fr); }
  .dash-actions { grid-template-columns: 1fr; }
}
@media (max-width: 520px) {
  .dash-kpis { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<header>
  <div class="wrap topbar">
    <div>
      <span class="nav-group">
        <a href="<?php echo App::url('/'); ?>">Dashboard</a>
      </span>
      <?php if (Auth::check()): ?>
        <span class="nav-sep"></span>
        <span class="nav-group">
          <a href="<?php echo App::url('/mandates'); ?>">Mandate</a>
          <a href="<?php echo App::url('/contracts'); ?>">Verträge</a>
          <a href="<?php echo App::url('/invoices'); ?>">Rechnungen</a>
          <a href="<?php echo App::url('/exports'); ?>">Exporte</a>
        </span>
        <span class="nav-sep"></span>
        <span class="nav-dropdown">
          <a class="nav-dropdown-toggle">Einstellungen</a>
          <div class="nav-dropdown-menu">
            <a href="<?php echo App::url('/settings'); ?>">Einstellungen</a>
            <?php if (Auth::role() === 'admin'): ?>
              <a href="<?php echo App::url('/contract-templates'); ?>">Vorlagen</a>
              <a href="<?php echo App::url('/sevdesk'); ?>">sevdesk</a>
              <a href="<?php echo App::url('/users'); ?>">Nutzer</a>
              <a href="<?php echo App::url('/update'); ?>">Update</a>
            <?php endif; ?>
          </div>
        </span>
      <?php endif; ?>
    </div>
    <div>
      <?php if (Auth::check()): ?>
        <span class="muted" style="color:#cfd7e6"><?php echo htmlspecialchars(Auth::user()['email'] ?? ''); ?></span>
        <a href="<?php echo App::url('/logout'); ?>">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="wrap">
  <?php foreach (($messages ?? []) as $m): ?>
    <div class="flash <?php echo htmlspecialchars($m['type']); ?>">
      <?php echo htmlspecialchars($m['message']); ?>
    </div>
  <?php endforeach; ?>
