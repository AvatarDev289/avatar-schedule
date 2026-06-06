/**
 * spa.js — Avatar Electric SPA Router
 * Hash-based routing + AJAX page loading + modal/drawer + toast
 */

const SPA = (function () {

  /* ─── Active Chart registry ─── */
  const _charts = {};
  function _destroyCharts() {
    Object.keys(_charts).forEach(k => { try { _charts[k].destroy(); } catch (e) {} delete _charts[k]; });
  }
  function registerChart(id, instance) { _charts[id] = instance; }

  /* ─── Route Table ─── */
  const ROUTES = [
    { pattern: '/dashboard',              page: 'pages/dashboard.php',     target: 'content',    title: 'ภาพรวม Dashboard',       nav: '/dashboard' },
    { pattern: '/projects',               page: 'pages/projects.php',      target: 'content',    title: 'รายการ Project',         nav: '/projects'  },
    { pattern: '/projects/view/:id',      page: 'pages/project_view.php',  target: 'content',    title: 'รายละเอียดโครงการ',       nav: '/projects',  params: ['id'] },
    { pattern: '/projects/add',           page: 'pages/project_form.php',  target: 'modal',      title: 'เพิ่มโครงการใหม่',       nav: '/projects'  },
    { pattern: '/projects/edit/:id',      page: 'pages/project_form.php',  target: 'drawer',     title: 'แก้ไขโครงการ',           nav: '/projects',  params: ['id'] },
    { pattern: '/panels',                 page: 'pages/panels.php',        target: 'content',    title: 'ติดตามรายตู้ (Panels)',   nav: '/panels'    },
    { pattern: '/panels/add',             page: 'pages/panel_form.php',    target: 'modal',      title: 'เพิ่มตู้ใหม่',           nav: '/panels'    },
    { pattern: '/panels/edit/:id',        page: 'pages/panel_form.php',    target: 'modal',      title: 'แก้ไขตู้',               nav: '/panels',    params: ['id'] },
    { pattern: '/deliveries',             page: 'pages/deliveries.php',    target: 'content',    title: 'ติดตามการส่งมอบ',         nav: '/deliveries' },
    { pattern: '/reports',                page: 'pages/reports.php',       target: 'content',    title: 'รายงาน',                 nav: '/reports'   },
    { pattern: '/reports/select/:project_id', page: 'pages/report_select.php', target: 'content', title: 'เลือกขอบเขตรายงาน',   nav: '/reports',   params: ['project_id'] },
    { pattern: '/settings',               page: 'pages/settings.php',      target: 'content',    title: 'ตั้งค่าระบบ',            nav: '/settings'  },
  ];

  /* ─── State ─── */
  let _current   = null;  // current content route
  let _drawerOpen = false;
  let _bsModal   = null;
  let _bsFullscreen = null;

  /* ─── Route Matching ─── */
  function _matchRoute(path) {
    const qIdx    = path.indexOf('?');
    const pathname = qIdx >= 0 ? path.slice(0, qIdx) : path;
    const qs       = qIdx >= 0 ? path.slice(qIdx) : '';

    for (const r of ROUTES) {
      const rSegs = r.pattern.split('/').filter(Boolean);
      const pSegs = pathname.replace(/^\//, '').split('/').filter(Boolean);
      if (rSegs.length !== pSegs.length) continue;

      const params = {};
      let match = true;
      for (let i = 0; i < rSegs.length; i++) {
        if (rSegs[i].startsWith(':')) {
          params[rSegs[i].slice(1)] = decodeURIComponent(pSegs[i]);
        } else if (rSegs[i] !== pSegs[i]) {
          match = false; break;
        }
      }
      if (match) return { ...r, params, qs };
    }
    return null;
  }

  function _buildUrl(route) {
    const qp = new URLSearchParams((route.qs || '').replace(/^\?/, ''));
    Object.entries(route.params || {}).forEach(([k, v]) => { if (v !== undefined) qp.set(k, v); });
    const qs = qp.toString();
    return qs ? route.page + '?' + qs : route.page;
  }

  /* ─── Script execution after innerHTML assignment ─── */
  function _execScripts(container) {
    container.querySelectorAll('script').forEach(old => {
      const s = document.createElement('script');
      [...old.attributes].forEach(a => s.setAttribute(a.name, a.value));
      s.textContent = old.textContent;
      old.replaceWith(s);
    });
  }

  /* ─── Nav + Title ─── */
  function _setNav(navRoute) {
    document.querySelectorAll('#sideNav [data-route]').forEach(a => {
      a.classList.toggle('active', a.dataset.route === navRoute);
    });
  }

  function _setTitle(title) {
    const el = document.getElementById('pageTitle');
    if (el) el.textContent = title;
    document.title = title + ' — Avatar Electric';
  }

  /* ─── Content Area Loader ─── */
  async function _loadContent(url) {
    const el = document.getElementById('app-content');
    el.classList.add('spa-loading');

    try {
      const resp = await fetch(url, { headers: { 'X-SPA': '1' } });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const html = await resp.text();

      _destroyCharts();
      el.innerHTML = html;
      _execScripts(el);
      el.classList.remove('spa-loading');

      // Scroll to top
      const main = document.querySelector('.content');
      if (main) main.scrollTop = 0;
    } catch (err) {
      el.classList.remove('spa-loading');
      el.innerHTML = `
        <div class="alert alert-danger m-4">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          โหลดหน้าไม่สำเร็จ: ${_esc(err.message)}
          <button class="btn btn-sm btn-outline-danger ms-3" onclick="SPA.reload()">
            <i class="bi bi-arrow-clockwise"></i> ลองใหม่
          </button>
        </div>`;
    }
  }

  /* ─── Modal Loader ─── */
  async function loadModal(url, title) {
    const body    = document.getElementById('spaModalBody');
    const titleEl = document.getElementById('spaModalTitle');

    titleEl.textContent = title || 'Loading...';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color:var(--primary-color)"></div></div>';

    if (!_bsModal) _bsModal = new bootstrap.Modal(document.getElementById('spaModal'));
    _bsModal.show();

    try {
      const resp = await fetch(url, { headers: { 'X-SPA': '1' } });
      const html = await resp.text();
      body.innerHTML = html;
      _execScripts(body);
    } catch (err) {
      body.innerHTML = `<div class="alert alert-danger">โหลดไม่สำเร็จ: ${_esc(err.message)}</div>`;
    }
  }

  /* ─── Drawer Loader ─── */
  async function loadDrawer(url, title) {
    const body    = document.getElementById('drawerBody');
    const titleEl = document.getElementById('drawerTitle');

    titleEl.textContent = title || '-';
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color:var(--primary-color)"></div></div>';

    document.getElementById('spaDrawer').classList.add('open');
    document.body.classList.add('spa-drawer-open');
    _drawerOpen = true;

    try {
      const resp = await fetch(url, { headers: { 'X-SPA': '1' } });
      const html = await resp.text();
      body.innerHTML = html;
      _execScripts(body);
    } catch (err) {
      body.innerHTML = `<div class="alert alert-danger">โหลดไม่สำเร็จ: ${_esc(err.message)}</div>`;
    }
  }

  /* ─── Fullscreen Modal Loader ─── */
  async function loadFullscreen(url, title) {
    const body    = document.getElementById('fullscreenModalBody');
    const titleEl = document.getElementById('fullscreenModalTitle');

    titleEl.innerHTML = `<i class="bi bi-image me-2" style="color:var(--primary-color)"></i>${_esc(title || 'Overview Report')}`;
    body.innerHTML = '<div class="text-center py-5"><div class="spinner-border" style="color:var(--primary-color)"></div></div>';

    if (!_bsFullscreen) _bsFullscreen = new bootstrap.Modal(document.getElementById('fullscreenModal'));
    _bsFullscreen.show();

    try {
      const resp = await fetch(url, { headers: { 'X-SPA': '1' } });
      const html = await resp.text();
      body.innerHTML = html;
      _execScripts(body);
    } catch (err) {
      body.innerHTML = `<div class="alert alert-danger p-4">โหลดไม่สำเร็จ: ${_esc(err.message)}</div>`;
    }
  }

  /* ─── Close Drawer ─── */
  function closeDrawer() {
    document.getElementById('spaDrawer').classList.remove('open');
    document.body.classList.remove('spa-drawer-open');
    _drawerOpen = false;
  }

  /* ─── Close Modal ─── */
  function closeModal() { if (_bsModal) _bsModal.hide(); }

  /* ─── Toast Notification ─── */
  function toast(msg, type, duration) {
    type     = type     || 'success';
    duration = duration || 3500;
    const C  = document.getElementById('toast-container');
    if (!C) return;

    const COLORS = { success: '#10B981', danger: '#EF4444', warning: '#F59E0B', info: '#3B82F6' };
    const ICONS  = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };

    const el = document.createElement('div');
    el.className = 'toast show align-items-center text-white border-0 mb-2';
    el.style.cssText = 'background:' + (COLORS[type] || COLORS.info) + ';min-width:260px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.18)';
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="bi ${ICONS[type] || ICONS.info}"></i> ${_esc(msg)}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
      </div>`;
    C.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(() => el.remove(), 450); }, duration);
  }

  /* ─── HTML escape helper ─── */
  function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ─── Main Navigate ─── */
  function navigate(path, push) {
    push = push !== false;
    if (!path || path === '#') path = '/dashboard';
    if (!path.startsWith('/')) path = '/' + path;

    const route = _matchRoute(path);
    if (!route) { toast('ไม่พบหน้า: ' + path, 'warning'); return; }

    if (push) history.pushState({ path }, route.title, 'app.php#' + path.split('?')[0] + (route.qs || ''));

    if (route.target === 'content') {
      _current = route;
      _setNav(route.nav || '');
      _setTitle(route.title);
      _loadContent(_buildUrl(route));
    } else if (route.target === 'modal') {
      loadModal(_buildUrl(route), route.title);
    } else if (route.target === 'drawer') {
      loadDrawer(_buildUrl(route), route.title);
    } else if (route.target === 'fullscreen') {
      loadFullscreen(_buildUrl(route), route.title);
    }
  }

  function go(path) { navigate(path, true); }

  function reload() {
    if (_current) _loadContent(_buildUrl(_current));
  }

  /* ─── Panel Status AJAX (inline quick-change selects) ─── */
  function _handlePanelStatus(form) {
    const id     = (form.querySelector('[name=id]') || {}).value;
    const status = (form.querySelector('[name=status]') || {}).value;
    if (!id || !status) return;

    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', status);

    fetch('api/panel_status.php', { method: 'POST', body: fd, headers: { 'X-SPA': '1' } })
      .then(r => r.json())
      .then(json => {
        if (json.success) { toast('อัปเดตสถานะแล้ว', 'success'); reload(); }
        else toast(json.error || 'เกิดข้อผิดพลาด', 'danger');
      })
      .catch(err => toast('เกิดข้อผิดพลาด: ' + err.message, 'danger'));
  }

  /* ─── AJAX Form Submit ─── */
  async function submitAjaxForm(form, onSuccess) {
    const btn      = form.querySelector('[type=submit]');
    const origHTML = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังบันทึก...'; }

    try {
      const resp = await fetch(form.dataset.apiAction || form.getAttribute('action') || '', {
        method: 'POST', body: new FormData(form), headers: { 'X-SPA': '1' }
      });
      const json = await resp.json();

      if (json.success) {
        toast(json.msg || 'บันทึกสำเร็จ', 'success');
        if (typeof onSuccess === 'function') onSuccess(json);
      } else {
        const errs = (json.errors || ['เกิดข้อผิดพลาด']).map(e => `<li>${_esc(e)}</li>`).join('');
        let errDiv = form.querySelector('.spa-form-errors');
        if (!errDiv) { errDiv = document.createElement('div'); errDiv.className = 'alert alert-danger spa-form-errors'; form.prepend(errDiv); }
        errDiv.innerHTML = '<ul class="mb-0">' + errs + '</ul>';
        errDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    } catch (err) {
      toast('เกิดข้อผิดพลาด: ' + err.message, 'danger');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
    }
  }

  /* ─── Confirm Delete ─── */
  function confirmDelete(msg, url, navTo) {
    if (!confirm(msg || 'ยืนยันการลบ?')) return;
    fetch(url, { method: 'POST', headers: { 'X-SPA': '1' } })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          toast(json.msg || 'ลบเรียบร้อยแล้ว', 'success');
          closeModal(); closeDrawer();
          navigate(navTo || '/dashboard', false);
          if (!navTo) reload();
        } else toast(json.error || 'เกิดข้อผิดพลาด', 'danger');
      })
      .catch(err => toast('เกิดข้อผิดพลาด: ' + err.message, 'danger'));
  }

  /* ─── Event Delegation ─── */
  function _setupEvents() {
    // ── Click delegation ──
    document.addEventListener('click', function (e) {
      const link = e.target.closest('a[href]');
      if (!link) return;
      const href = link.getAttribute('href') || '';

      // Hash SPA routes
      if (href.startsWith('#/')) {
        e.preventDefault();
        navigate(href.slice(1)); // strip #
        return;
      }
      // data-spa-modal
      if (link.dataset.spaModal !== undefined) {
        e.preventDefault();
        loadModal(link.dataset.spaModal || href, link.dataset.spaTitle || 'Loading...');
        return;
      }
      // data-spa-drawer
      if (link.dataset.spaDrawer !== undefined) {
        e.preventDefault();
        loadDrawer(link.dataset.spaDrawer || href, link.dataset.spaTitle || '-');
        return;
      }
      // data-spa-fullscreen
      if (link.dataset.spaFullscreen !== undefined) {
        e.preventDefault();
        loadFullscreen(link.dataset.spaFullscreen || href, link.dataset.spaTitle || 'Report');
        return;
      }
    });

    // ── Form delegation ──
    document.addEventListener('submit', function (e) {
      const form = e.target;

      // Panel status quick-change
      if (form.classList.contains('panel-status-form')) {
        e.preventDefault();
        _handlePanelStatus(form);
        return;
      }

      // Filter forms (GET forms that reload content with params)
      if (form.dataset.spaFilter) {
        e.preventDefault();
        const qp = new URLSearchParams(new FormData(form));
        navigate(form.dataset.spaFilter + '?' + qp.toString());
        return;
      }

      // Fullscreen report form
      if (form.dataset.spaForm === 'fullscreen') {
        e.preventDefault();
        const qp   = new URLSearchParams(new FormData(form));
        // Handle panel_ids built by JS
        const pf   = document.getElementById('panelIdsField');
        if (pf && pf.value) qp.set('panel_ids', pf.value);
        qp.set('fragment', '1');
        const base = form.getAttribute('action') || 'project_overview_image.php';
        loadFullscreen(base + '?' + qp.toString(), 'Project Overview Report');
        return;
      }

      // Print form (fullscreen modal for print_report)
      if (form.dataset.spaForm === 'print') {
        e.preventDefault();
        const qp  = new URLSearchParams(new FormData(form));
        qp.set('fragment', '1');
        const base = form.getAttribute('action') || 'print_report.php';
        loadFullscreen(base + '?' + qp.toString(), 'รายงาน');
        return;
      }

      // General AJAX forms
      if (form.dataset.spaForm === 'ajax') {
        e.preventDefault();
        submitAjaxForm(form, function (json) {
          closeModal(); closeDrawer();
          const navTo = form.dataset.spaNav;
          if (navTo) navigate(navTo, true);
          else reload();
        });
        return;
      }
    });

    // ── Drawer controls ──
    document.getElementById('drawerBackdrop')?.addEventListener('click', closeDrawer);
    document.getElementById('drawerClose')?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && _drawerOpen) closeDrawer(); });

    // ── Mobile sidebar toggle ──
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
      document.getElementById('sidebar').classList.toggle('mobile-open');
    });

    // Click outside mobile sidebar to close
    document.addEventListener('click', function (e) {
      const sidebar = document.getElementById('sidebar');
      const toggle  = document.getElementById('sidebarToggle');
      if (sidebar?.classList.contains('mobile-open') && !sidebar.contains(e.target) && !toggle?.contains(e.target)) {
        sidebar.classList.remove('mobile-open');
      }
    });
  }

  /* ─── Init ─── */
  function init() {
    _setupEvents();

    // Browser back/forward
    window.addEventListener('popstate', function (e) {
      const hash = location.hash || '';
      const path = hash.startsWith('#/') ? hash.slice(1) : '/dashboard';
      navigate(path, false);
    });

    // Load initial route from hash or default
    const initHash = location.hash || '';
    const initPath = initHash.startsWith('#/') ? initHash.slice(1) : '/dashboard';
    navigate(initPath, false);
  }

  /* ─── Public API ─── */
  return { go, navigate, reload, toast, closeDrawer, closeModal, loadModal, loadDrawer, loadFullscreen, confirmDelete, registerChart, submitAjaxForm, init };

})();

document.addEventListener('DOMContentLoaded', function () { SPA.init(); });
