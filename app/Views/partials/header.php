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
.wrap { max-width: 1320px; margin: 18px auto; padding: 0 14px; }
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
.pill.secondary { background:#e5e7eb; color:#374151; }
.pill.primary { background:#dbeafe; color:#1e40af; }
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
.nav-secondary { font-weight: 500; opacity: 0.75; }
.nav-secondary:hover { opacity: 1; }


.table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.table-wrap table { min-width: 980px; }
td { word-break: break-word; }
.mandates-table { min-width: 1180px; }
.mandates-table th,
.mandates-table td { padding: 12px 14px; vertical-align: middle; }
.mandates-table th { white-space: nowrap; }
.mandates-table td { word-break: normal; }
.mandates-table td.iban,
.mandates-table td.bic { white-space: nowrap; }
.mandates-table .actions { flex-wrap: nowrap; }
.mandates-table tbody tr { transition: background-color .12s ease; }
.mandates-table tbody tr:hover { background:#f8faff; }
.mandates-table tbody tr.is-revoked:hover { background:#ffecec; }

/* Page header (Listing-Seiten) */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom: 14px; }
.page-header-text h1 { margin: 0 0 4px; }
.page-header-text p { margin: 0; color:#6b7280; font-size:14px; }
.page-header .actions { flex-shrink:0; }

/* Filter bar */
.filter-bar { background:#fafbfe; border:1px solid #edf0f6; border-radius:12px; padding:12px 14px; }
.filter-bar label { margin-top:0; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }

/* Mini KPI Reihe (kompakter als Dashboard) */
.stat-row { display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px; }
.stat-tile { background:#fff; border:1px solid #edf0f6; border-radius:12px; padding:12px 14px; box-shadow:0 4px 12px rgba(0,0,0,.04); display:flex; flex-direction:column; gap:4px; }
.stat-tile-label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; font-weight:700; }
.stat-tile-value { font-size:24px; font-weight:800; color:#1D3860; line-height:1.1; }
.stat-tile.is-ok .stat-tile-value { color:#166534; }
.stat-tile.is-warn .stat-tile-value { color:#854d0e; }
.stat-tile.is-err .stat-tile-value { color:#991b1b; }
@media (max-width: 980px) { .stat-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .stat-row { grid-template-columns: 1fr; } }

/* Customer-Zelle mit Initialien-Avatar */
.cust { display:flex; align-items:center; gap:10px; min-width:0; }
.cust-avatar { width:34px; height:34px; border-radius:50%; flex:0 0 auto; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; background:#1D3860; text-transform:uppercase; letter-spacing:.02em; }
.cust-body { min-width:0; }
.cust-name { font-weight:600; color:#1b1f24; }
.cust-meta { font-size:12px; color:#6b7280; }

/* Empty State */
.empty-state { text-align:center; padding: 32px 16px; color:#6b7280; }
.empty-state-title { font-size:15px; font-weight:600; color:#374151; margin-bottom:4px; }
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

.dash-workflow-head { margin-bottom: 14px; }
.dash-workflow-head h2 { margin: 0 0 4px; }
.dash-workflow-head p { margin: 0; }

.dash-steps {
  list-style:none; padding:0; margin:0;
  display:grid; grid-template-columns: repeat(4, 1fr); gap: 0;
}
.dash-step {
  display:flex; flex-direction:column; align-items:stretch;
  padding:0; border:0; background:transparent; position:relative;
  min-width: 0;
}

.dash-step-marker {
  display:flex; align-items:center; justify-content:center;
  position:relative; height:48px; margin-bottom:10px;
}
.dash-step-bubble {
  width:40px; height:40px; border-radius:50%;
  background:#fff; color:#9ca3af;
  border: 2px solid #e5e7eb;
  font-weight:800; font-size:15px;
  display:flex; align-items:center; justify-content:center;
  flex:0 0 auto; position:relative; z-index:1;
  transition: all .15s ease;
}
.dash-step-line {
  position:absolute; top:50%; height:2px; background:#e5e7eb;
  transform: translateY(-50%);
}
.dash-step-line-prev { left:0; right:calc(50% + 22px); }
.dash-step-line-next { left:calc(50% + 22px); right:0; }
.dash-step:first-child .dash-step-line-prev { display:none; }
.dash-step.is-last .dash-step-line-next,
.dash-step:last-child .dash-step-line-next { display:none; }

.dash-step-body {
  flex:1 1 auto; display:flex; flex-direction:column; gap:6px;
  background:#fff; border:1px solid #edf0f6; border-radius:12px;
  padding:14px 16px; margin: 0 8px;
  transition: all .15s ease;
}
.dash-step:first-child .dash-step-body { margin-left: 0; }
.dash-step:last-child .dash-step-body { margin-right: 0; }
.dash-step-head { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.dash-step-head h3 { margin:0; font-size:15px; }
.dash-step-desc { margin:0; color:#374151; font-size:13px; line-height:1.4; }
.dash-step-meta { font-size:12px; }
.dash-step-badge {
  display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:.04em; padding:3px 8px; border-radius:999px;
  background:#f3f4f6; color:#6b7280; white-space:nowrap;
}
.dash-step .actions { margin-top:auto; padding-top:6px; }

/* done */
.dash-step.is-done .dash-step-bubble { background:#16a34a; color:#fff; border-color:#16a34a; }
.dash-step.is-done .dash-step-line-prev,
.dash-step.is-done + .dash-step .dash-step-line-prev { background:#16a34a; }
.dash-step.is-done .dash-step-line-next { background:#16a34a; }
.dash-step.is-done .dash-step-badge { background:#dcfce7; color:#166534; }
.dash-step.is-done .dash-step-body { background:#fafefb; border-color:#d1fae5; }

/* current */
.dash-step.is-current .dash-step-bubble {
  background:#1D3860; color:#fff; border-color:#1D3860;
  box-shadow:0 0 0 4px rgba(29,56,96,.15);
}
.dash-step.is-current .dash-step-badge { background:#dbeafe; color:#1e40af; }
.dash-step.is-current .dash-step-body {
  border-color:#1D3860; box-shadow:0 8px 22px rgba(29,56,96,.10);
}
.dash-step.is-current .dash-step-head h3 { color:#1D3860; }

/* pending */
.dash-step.is-pending .dash-step-body { opacity:0.65; }
.dash-step.is-pending .dash-step-head h3 { color:#6b7280; }

.btn[disabled] { opacity:0.5; cursor:not-allowed; pointer-events:none; }

@media (max-width: 980px) {
  .dash-steps { grid-template-columns: repeat(2, 1fr); }
  /* Linie zwischen Spalten 1 & 2 (oben) bleibt; zwischen 2 & 3 fällt durch Zeilenumbruch weg */
  .dash-step:nth-child(2) .dash-step-line-next,
  .dash-step:nth-child(3) .dash-step-line-prev { display:none; }
}
@media (max-width: 560px) {
  .dash-steps { grid-template-columns: 1fr; }
  .dash-step { display:grid; grid-template-columns: 48px 1fr; align-items:start; }
  .dash-step-marker { height:auto; min-height:48px; margin-bottom:0; padding-top:8px; }
  .dash-step-line-prev { left:50%; right:auto; top:0; bottom:50%; width:2px; height:auto; transform:none; }
  .dash-step-line-next { left:50%; right:auto; top:50%; bottom:0; width:2px; height:auto; transform:none; }
  .dash-step:first-child .dash-step-line-prev,
  .dash-step:last-child .dash-step-line-next { display:none; }
  .dash-step-body { margin: 0 0 12px 0; }
  .dash-step-head { flex-wrap:wrap; }
}
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
          <a class="nav-secondary" href="<?php echo App::url('/invoices'); ?>">Rechnungen</a>
          <a class="nav-secondary" href="<?php echo App::url('/exports'); ?>">Exporte</a>
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
