<?php
use App\Support\App;
use App\Support\Auth;
use App\Support\Flash;
$messages = $messages ?? Flash::all();
$__view = $__view ?? '';
$__isDashboard = ($__view === 'dashboard');
$__bodyClass = 'is-modern' . ($__isDashboard ? ' is-dashboard' : '');
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

/* =====================================================================
   MODERN THEME — wird nur auf Nicht-Dashboard-Seiten angewendet
   ===================================================================== */
body.is-modern {
  background:
    radial-gradient(1200px 600px at -10% -20%, rgba(29,56,96,.08), transparent 60%),
    radial-gradient(900px 500px at 110% 0%, rgba(99,102,241,.08), transparent 55%),
    linear-gradient(180deg, #f4f6fb 0%, #eef1f8 100%);
  color: #0f172a;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  font-feature-settings: "ss01", "cv11";
}

body.is-modern header {
  background: linear-gradient(135deg, #14274a 0%, #1D3860 55%, #28477a 100%);
  box-shadow: 0 6px 24px rgba(15, 23, 42, .12);
  border-bottom: 1px solid rgba(255,255,255,.06);
  padding: 16px 18px;
}
body.is-modern header a {
  font-weight: 600;
  letter-spacing: .005em;
  padding: 6px 10px;
  border-radius: 8px;
  transition: background-color .15s ease, color .15s ease;
}
body.is-modern header a:hover { background: rgba(255,255,255,.10); }
body.is-modern .nav-sep { background: rgba(255,255,255,.18); }
body.is-modern .nav-dropdown-menu {
  background: #14274a;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 12px;
  padding: 6px;
  box-shadow: 0 18px 48px rgba(2,6,23,.35);
  margin-top: 4px;
}
body.is-modern .nav-dropdown-menu a {
  border-bottom: 0;
  border-radius: 8px;
  padding: 8px 12px;
}

body.is-modern .wrap { max-width: 1320px; margin: 26px auto; padding: 0 18px; }

/* Cards */
body.is-modern .card {
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, .06);
  border-radius: 16px;
  padding: 22px 22px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, .04),
    0 12px 32px -16px rgba(15, 23, 42, .14);
  margin-bottom: 18px;
}

body.is-modern h1 { font-size: 26px; font-weight: 800; letter-spacing: -0.01em; }
body.is-modern h2 { font-size: 18px; font-weight: 700; letter-spacing: -0.005em; }
body.is-modern h3 { font-weight: 700; letter-spacing: -0.005em; }

/* Page header */
body.is-modern .page-header {
  margin-bottom: 18px;
  padding: 4px 2px;
}
body.is-modern .page-header-text h1 {
  background: linear-gradient(135deg, #1D3860 0%, #4f46e5 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
body.is-modern .page-header-text p { color: #64748b; font-size: 14px; }

/* Form controls */
body.is-modern label {
  font-weight: 600;
  font-size: 13px;
  color: #334155;
  letter-spacing: .01em;
}
body.is-modern input,
body.is-modern select,
body.is-modern textarea {
  background: #ffffff;
  border: 1px solid #dbe1ec;
  border-radius: 10px;
  padding: 11px 13px;
  font-size: 14.5px;
  color: #0f172a;
  transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
}
body.is-modern input::placeholder,
body.is-modern textarea::placeholder { color: #94a3b8; }
body.is-modern input:hover,
body.is-modern select:hover,
body.is-modern textarea:hover { border-color: #c2cbdb; }
body.is-modern input:focus,
body.is-modern select:focus,
body.is-modern textarea:focus {
  outline: none;
  border-color: #1D3860;
  box-shadow: 0 0 0 4px rgba(29,56,96,.12);
}
body.is-modern input[type="file"] { padding: 8px 10px; background: #f8fafc; }
body.is-modern input[type="checkbox"] {
  width: 18px; height: 18px; padding: 0;
  accent-color: #1D3860;
  cursor: pointer;
}

/* Buttons */
body.is-modern .btn {
  background: linear-gradient(180deg, #234775 0%, #1D3860 100%);
  color: #fff;
  border: 1px solid rgba(15, 23, 42, .08);
  padding: 11px 16px;
  border-radius: 10px;
  font-weight: 600;
  letter-spacing: .005em;
  box-shadow:
    0 1px 0 rgba(255,255,255,.18) inset,
    0 1px 2px rgba(15,23,42,.10),
    0 6px 16px -8px rgba(29,56,96,.55);
  transition: transform .08s ease, box-shadow .15s ease, background .15s ease, opacity .15s ease;
}
body.is-modern .btn:hover {
  background: linear-gradient(180deg, #2a5288 0%, #1D3860 100%);
  box-shadow:
    0 1px 0 rgba(255,255,255,.20) inset,
    0 2px 4px rgba(15,23,42,.12),
    0 12px 22px -10px rgba(29,56,96,.55);
}
body.is-modern .btn:active { transform: translateY(1px); }
body.is-modern .btn.secondary {
  background: #ffffff;
  color: #1D3860;
  border: 1px solid #d8dde6;
  box-shadow: 0 1px 1px rgba(15,23,42,.04);
}
body.is-modern .btn.secondary:hover {
  background: #f3f5fb;
  border-color: #c2cbdb;
}
body.is-modern .btn.danger {
  background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%);
  border-color: rgba(127, 29, 29, .35);
  box-shadow:
    0 1px 0 rgba(255,255,255,.18) inset,
    0 6px 16px -8px rgba(185,28,28,.55);
}
body.is-modern .btn.danger:hover { background: linear-gradient(180deg, #ef4444 0%, #b91c1c 100%); }
body.is-modern .btn.inline { padding: 9px 13px; font-size: 13.5px; }
body.is-modern .btn[disabled] { opacity: .45; box-shadow: none; }

/* Tables */
body.is-modern table {
  border-collapse: separate;
  border-spacing: 0;
}
body.is-modern th {
  background: #f6f8fc;
  color: #475569;
  font-size: 11.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  padding: 12px 14px;
  border-bottom: 1px solid #e6ebf3;
}
body.is-modern th:first-child { border-top-left-radius: 10px; }
body.is-modern th:last-child  { border-top-right-radius: 10px; }
body.is-modern td {
  padding: 13px 14px;
  border-bottom: 1px solid #eef1f7;
  font-size: 14px;
  color: #1e293b;
}
body.is-modern tbody tr { transition: background-color .12s ease; }
body.is-modern tbody tr:hover { background: #f8faff; }
body.is-modern tbody tr:last-child td { border-bottom: 0; }

/* Pills */
body.is-modern .pill {
  border: 1px solid transparent;
  font-weight: 600;
  font-size: 11.5px;
  letter-spacing: .02em;
  padding: 4px 10px;
}
body.is-modern .pill.ok       { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
body.is-modern .pill.err      { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
body.is-modern .pill.warn     { background:#fefce8; color:#a16207; border-color:#fde68a; }
body.is-modern .pill.primary  { background:#eef2ff; color:#3730a3; border-color:#c7d2fe; }
body.is-modern .pill.secondary{ background:#f1f5f9; color:#334155; border-color:#e2e8f0; }
body.is-modern .pill:not(.ok):not(.err):not(.warn):not(.primary):not(.secondary){ background:#f1f5f9; color:#334155; border-color:#e2e8f0; }

/* Flash messages */
body.is-modern .flash {
  border-radius: 12px;
  padding: 12px 14px;
  border: 1px solid transparent;
  font-weight: 500;
  box-shadow: 0 6px 18px -10px rgba(15,23,42,.20);
}
body.is-modern .flash.success { background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
body.is-modern .flash.error   { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
body.is-modern .flash.info    { background:#eff6ff; color:#1e3a8a; border-color:#bfdbfe; }

/* Filter bar */
body.is-modern .filter-bar {
  background: linear-gradient(180deg, #fafbfe 0%, #f4f6fb 100%);
  border: 1px solid #e6ebf3;
  border-radius: 14px;
  padding: 14px 16px;
}
body.is-modern .filter-bar label {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #64748b;
  font-weight: 700;
}

/* Stat tiles */
body.is-modern .stat-row { gap: 14px; margin-bottom: 18px; }
body.is-modern .stat-tile {
  position: relative;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, .06);
  border-radius: 14px;
  padding: 16px 18px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, .04),
    0 12px 24px -18px rgba(15, 23, 42, .18);
  overflow: hidden;
}
body.is-modern .stat-tile::before {
  content:""; position:absolute; left:0; top:0; bottom:0; width:4px;
  background: linear-gradient(180deg, #1D3860, #4f46e5);
}
body.is-modern .stat-tile.is-ok::before   { background: linear-gradient(180deg, #10b981, #047857); }
body.is-modern .stat-tile.is-warn::before { background: linear-gradient(180deg, #f59e0b, #b45309); }
body.is-modern .stat-tile.is-err::before  { background: linear-gradient(180deg, #ef4444, #b91c1c); }
body.is-modern .stat-tile-label {
  font-size: 11px; text-transform: uppercase; letter-spacing: .07em;
  color: #64748b; font-weight: 700;
}
body.is-modern .stat-tile-value {
  font-size: 28px; font-weight: 800; color: #0f172a;
  letter-spacing: -.015em; line-height: 1.1;
}
body.is-modern .stat-tile.is-ok   .stat-tile-value { color: #047857; }
body.is-modern .stat-tile.is-warn .stat-tile-value { color: #b45309; }
body.is-modern .stat-tile.is-err  .stat-tile-value { color: #b91c1c; }

/* Customer cell */
body.is-modern .cust-avatar {
  background: linear-gradient(135deg, #1D3860, #4f46e5);
  box-shadow: 0 4px 10px -4px rgba(29,56,96,.55);
}

/* Empty state */
body.is-modern .empty-state {
  padding: 44px 16px;
  background:
    radial-gradient(circle at 50% 0%, rgba(99,102,241,.05), transparent 70%);
  border-radius: 12px;
}
body.is-modern .empty-state-title {
  font-size: 16px; font-weight: 700; color: #1e293b;
}

/* Topbar in cards */
body.is-modern .topbar h1,
body.is-modern .topbar h2,
body.is-modern .topbar h3 { margin: 0; }

/* Details/summary */
body.is-modern details.card > summary {
  font-weight: 600;
  color: #0f172a;
  list-style: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
}
body.is-modern details.card > summary::before {
  content: "\25B8";
  display: inline-block;
  color: #94a3b8;
  transition: transform .15s ease;
}
body.is-modern details[open].card > summary::before { transform: rotate(90deg); }

/* Muted */
body.is-modern .muted { color: #64748b; }

/* Login centering */
body.is-modern.login-page .wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 140px); }
body.is-modern.login-page .card {
  max-width: 440px; width: 100%;
  padding: 32px 28px;
  border-radius: 18px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, .04),
    0 30px 60px -30px rgba(29, 56, 96, .45);
}
body.is-modern.login-page .card h1 {
  font-size: 24px; margin-bottom: 18px;
  background: linear-gradient(135deg, #1D3860 0%, #4f46e5 100%);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}

/* Section heading subtle separator */
body.is-modern .card > h2:first-child,
body.is-modern .card > h1:first-child { margin-top: 0; }

/* Smooth scrollbars in table-wrap */
body.is-modern .table-wrap { border-radius: 12px; }

/* Subtle link color in tables */
body.is-modern td a { color: #1D3860; text-decoration: none; font-weight: 600; }
body.is-modern td a:hover { text-decoration: underline; }

/* Show more breathing room on row gap */
body.is-modern .row, body.is-modern .row3, body.is-modern .grid { gap: 14px; }

/* ----- Listen: ausgewogen + in Containerbreite ----- */
body.is-modern .table-wrap { overflow-x: auto; border-radius: 12px; }
body.is-modern .table-wrap table,
body.is-modern .mandates-table { min-width: 0; width: 100%; table-layout: auto; }

/* Header-Spaltentitel nie abschneiden */
body.is-modern table th {
  white-space: nowrap;
  vertical-align: middle;
}

/* Zellen duerfen umbrechen; nur lange unteilbare Strings (URLs) brechen */
body.is-modern table td {
  vertical-align: middle;
  overflow-wrap: break-word;
}

/* Aktion-Spalten: einzeilig, schrumpfen auf Inhalt */
body.is-modern table td:has(.actions),
body.is-modern table td:has(form),
body.is-modern table td:has(.btn),
body.is-modern table td:has(input[type="checkbox"]) {
  white-space: nowrap;
  width: 1%;
}
body.is-modern .actions { flex-wrap: nowrap; }

/* Status/Pill-Spalten: einzeilig, schrumpfen auf Inhalt */
body.is-modern table td:has(> .pill:only-child) {
  white-space: nowrap;
  width: 1%;
}

/* Datums-/Zahlen-Spalten und IBAN/BIC bleiben einzeilig */
body.is-modern td.mono,
body.is-modern td.iban,
body.is-modern td.bic,
body.is-modern td.nowrap {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
  letter-spacing: .01em;
}
body.is-modern td.iban,
body.is-modern td.bic { word-break: keep-all; overflow-wrap: normal; }

/* Customer-Zelle */
body.is-modern .cust { flex-wrap: nowrap; min-width: 0; }
body.is-modern .cust-body { min-width: 0; }
body.is-modern .cust-name,
body.is-modern .cust-meta {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

/* Etwas kompaktere Tabellen-Paddings */
body.is-modern .mandates-table th,
body.is-modern .mandates-table td { padding: 10px 12px; }

/* Lange URL-Anzeigen sauber umbrechen */
body.is-modern td a[href^="http"] + br + .muted { word-break: break-all; }

/* Key/Value-Tabellen (z.B. Update-Seite): erste Spalte nicht umbrechen */
body.is-modern table:not(:has(thead)) td:first-child {
  white-space: nowrap;
  width: 1%;
  padding-right: 18px;
}

/* ===== Dashboard im Modern-Stil ===== */
body.is-modern.is-dashboard .kpi {
  position: relative;
  background: #ffffff;
  border: 1px solid rgba(15, 23, 42, .06);
  border-radius: 16px;
  padding: 18px 20px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, .04),
    0 12px 32px -16px rgba(15, 23, 42, .14);
  overflow: hidden;
}
body.is-modern.is-dashboard .kpi::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
  background: linear-gradient(180deg, #1D3860, #4f46e5);
}
body.is-modern.is-dashboard .kpi-label {
  font-size: 11px; text-transform: uppercase; letter-spacing: .07em;
  color: #64748b; font-weight: 700;
}
body.is-modern.is-dashboard .kpi-value {
  font-size: 30px; font-weight: 800; color: #0f172a;
  letter-spacing: -.015em; line-height: 1.1; margin: 8px 0 4px;
}
body.is-modern.is-dashboard .kpi-sub { color: #64748b; font-size: 13px; }

body.is-modern.is-dashboard .dash-hero {
  background: linear-gradient(135deg, #ffffff 0%, #f4f6fb 100%);
}
body.is-modern.is-dashboard .dash-hero-text h1 {
  background: linear-gradient(135deg, #1D3860 0%, #4f46e5 100%);
  -webkit-background-clip: text; background-clip: text; color: transparent;
}

body.is-modern.is-dashboard .dash-action {
  background: linear-gradient(180deg, #fafbfe 0%, #f4f6fb 100%);
  border: 1px solid #e6ebf3; border-radius: 14px;
}
body.is-modern.is-dashboard .dash-step-body {
  border-radius: 14px; border-color: #e6ebf3;
  box-shadow: 0 8px 24px -16px rgba(15, 23, 42, .14);
}
body.is-modern.is-dashboard .dash-step.is-current .dash-step-body {
  box-shadow: 0 12px 30px -14px rgba(29, 56, 96, .35);
  border-color: #1D3860;
}
body.is-modern.is-dashboard .dash-warning {
  border-radius: 12px; border: 1px solid transparent;
}
body.is-modern.is-dashboard .dash-warning.error { border-color: #fecaca; }
body.is-modern.is-dashboard .dash-warning.warn  { border-color: #fde68a; }
body.is-modern.is-dashboard .dash-warning.info  { border-color: #bfdbfe; }
</style>
</head>
<body class="<?php echo htmlspecialchars($__bodyClass); ?><?php echo $__view === 'login' ? ' login-page' : ''; ?>">
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
