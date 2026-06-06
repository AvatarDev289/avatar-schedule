<?php
/**
 * app.php — SPA Shell for Avatar Electric Schedule of Project
 * All navigation happens client-side; content loads into #app-content via AJAX.
 */
require_once __DIR__ . '/functions.php';
$today = format_date(date('Y-m-d'));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avatar Electric — Schedule of Project</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<link href="assets/css/project_overview.css" rel="stylesheet">
</head>
<body>

<div class="app-shell">

  <!-- ─── Sidebar ─── -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="assets/img/logo.png" alt="logo" onerror="this.style.display='none'">
      <div>
        <div class="brand-name">AVATAR</div>
        <div class="brand-sub">ELECTRIC</div>
      </div>
    </div>

    <nav class="side-nav" id="sideNav">
      <a href="#/dashboard"  data-route="/dashboard">
        <i class="bi bi-speedometer2"></i><span>ภาพรวม Dashboard</span></a>
      <a href="#/projects"   data-route="/projects">
        <i class="bi bi-folder2-open"></i><span>รายการ Project</span></a>
      <a href="#/panels"     data-route="/panels">
        <i class="bi bi-hdd-stack"></i><span>ติดตามรายตู้</span></a>
      <a href="#/deliveries" data-route="/deliveries">
        <i class="bi bi-truck"></i><span>Deliveries</span></a>
      <a href="#/reports"    data-route="/reports">
        <i class="bi bi-file-earmark-bar-graph"></i><span>รายงาน</span></a>
      <a href="#/settings"   data-route="/settings">
        <i class="bi bi-gear"></i><span>ตั้งค่าระบบ</span></a>
    </nav>

    <div class="side-foot">
      <div>Schedule of Project</div>
      <div style="color:rgba(255,255,255,0.3);font-size:11px;margin-top:2px">v2.0 SPA</div>
    </div>
  </aside>

  <!-- ─── Main Area ─── -->
  <div class="main">
    <header class="topbar">
      <div class="d-flex align-items-center gap-2">
        <button class="sidebar-toggle" id="sidebarToggle" title="เมนู">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <h1 class="page-title" id="pageTitle">Dashboard</h1>
          <div class="page-sub">ระบบบริหารแผนงานโครงการ บริษัท อวตาร อิเล็คทริค จำกัด</div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="data-date">
          <i class="bi bi-calendar3"></i> <?= $today ?>
        </div>
        <div class="user-chip"><i class="bi bi-person-circle"></i> ผู้ดูแลระบบ</div>
      </div>
    </header>

    <main class="content">
      <div id="app-content">
        <div class="spa-init-loader">
          <div class="spinner-border" style="color:var(--primary-color);width:2.5rem;height:2.5rem"></div>
        </div>
      </div>
    </main>

    <footer class="app-footer">
      © <?= date('Y') ?> Avatar Electric Co., Ltd. — Schedule of Project Dashboard
    </footer>
  </div>
</div>

<!-- ─── Toast Container ─── -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index:9990"></div>

<!-- ─── Standard Modal (Project / Panel Add) ─── -->
<div class="modal fade" id="spaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary-color)">
        <h5 class="modal-title text-white" id="spaModalTitle">Loading...</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" id="spaModalBody">
        <div class="text-center py-5">
          <div class="spinner-border" style="color:var(--primary-color)"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Fullscreen Modal (Overview Report / Print) ─── -->
<div class="modal fade" id="fullscreenModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--sidebar-color);border-bottom:3px solid var(--primary-color)">
        <h5 class="modal-title" id="fullscreenModalTitle" style="color:#fff">
          <i class="bi bi-image me-2" style="color:var(--primary-color)"></i>Overview Report
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0 overflow-auto" id="fullscreenModalBody"
           style="height:calc(100dvh - 60px);background:#f0f0f0">
        <div class="text-center py-5">
          <div class="spinner-border" style="color:var(--primary-color)"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ─── Side Drawer (Project Edit) ─── -->
<div id="spaDrawer" class="spa-drawer">
  <div class="drawer-backdrop" id="drawerBackdrop"></div>
  <div class="drawer-panel" id="drawerPanel">
    <div class="drawer-header">
      <h5 class="mb-0" id="drawerTitle">-</h5>
      <button class="btn-close btn-close-white" id="drawerClose"></button>
    </div>
    <div class="drawer-body" id="drawerBody">
      <div class="text-center py-5">
        <div class="spinner-border" style="color:var(--primary-color)"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/project_overview_export.js"></script>
<script src="assets/js/spa.js"></script>
</body>
</html>
