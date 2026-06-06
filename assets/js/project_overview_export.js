/* =====================================================================
   Avatar Electric — Project Overview Image: export & preview
   PNG / JPG / Print-PDF / Fullscreen via html2canvas (scale: 2)
   Supports multiple report pages (.report-page) for >16 panels.
   ===================================================================== */
(function () {
  'use strict';

  var CANVAS_W = 1536;
  // Lazily look up DOM nodes so this script works both in standalone mode
  // (project_overview_image.php loaded directly) and in SPA fragment mode
  // (injected into #fullscreenModalBody after page load).
  function getStage()    { return document.getElementById('canvasStage'); }
  function getStatusEl() { return document.getElementById('exportStatus'); }
  function pages() { return Array.prototype.slice.call(document.querySelectorAll('.report-page')); }

  /* ---- Fit the 1536px canvases into the viewport (preview only) ---- */
  function fitPreview() {
    var stage = getStage();
    if (!stage) return;
    var avail = stage.clientWidth - 20;
    var scale = Math.min(1, avail / CANVAS_W);
    var totalH = 0;
    pages().forEach(function (pg) {
      pg.style.transform = 'scale(' + scale + ')';
      totalH += pg.offsetHeight * scale + 28;
    });
    stage.style.minHeight = (totalH + 40) + 'px';
  }
  window.addEventListener('resize', fitPreview);
  window.addEventListener('load', function () { fitPreview(); setTimeout(fitPreview, 400); });

  function showStatus(on, text) {
    var statusEl = getStatusEl();
    if (!statusEl) return;
    statusEl.hidden = !on;
    if (text) statusEl.lastChild.textContent = ' ' + text;
  }

  /* ---- Render a single page element at full resolution ---- */
  function renderPage(el) {
    var prev = el.style.transform;
    el.style.transform = 'none';
    var opts = {
      scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false,
      width: el.offsetWidth, height: el.offsetHeight,
      windowWidth: el.scrollWidth, windowHeight: el.scrollHeight
    };
    var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    return fontsReady
      .then(function () { return html2canvas(el, opts); })
      .then(function (c) { el.style.transform = prev; return c; })
      .catch(function (e) { el.style.transform = prev; throw e; });
  }

  function triggerDownload(dataUrl, filename) {
    var link = document.createElement('a');
    link.download = filename; link.href = dataUrl;
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
  }

  function fileBase() {
    if (window.REPORT_FILEBASE) { return window.REPORT_FILEBASE; }
    var no = (document.querySelector('.rh-no') || {}).textContent || 'project';
    return 'overview-' + no.trim().replace(/[^\w\-]+/g, '_');
  }

  /* Export every page sequentially as image files. */
  function exportAll(mime, ext, quality) {
    var list = pages();
    showStatus(true, 'กำลังสร้างรูปภาพ...');
    var i = 0;
    function next() {
      if (i >= list.length) { showStatus(false); return; }
      var idx = i + 1;
      showStatus(true, 'กำลังสร้างรูปภาพ ' + idx + '/' + list.length + ' ...');
      renderPage(list[i]).then(function (c) {
        var suffix = list.length > 1 ? ('-p' + idx) : '';
        triggerDownload(c.toDataURL(mime, quality), fileBase() + suffix + '.' + ext);
        i++;
        setTimeout(next, 250); // small gap so browser accepts multiple downloads
      }).catch(function (e) { showStatus(false); alert('สร้างรูปไม่สำเร็จ: ' + e.message); });
    }
    next();
  }

  window.downloadPNG  = function () { exportAll('image/png', 'png'); };
  window.downloadJPG  = function () { exportAll('image/jpeg', 'jpg', 0.95); };
  window.fitPreview   = fitPreview;   // exposed so fragment mode inline script can call it

  /* ---- Print / Save as PDF (all pages) ---- */
  window.printPDF = function () {
    var prev = pages().map(function (pg) { var t = pg.style.transform; pg.style.transform = 'none'; return t; });
    var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    fontsReady.then(function () {
      window.print();
      setTimeout(function () { pages().forEach(function (pg, i) { pg.style.transform = prev[i]; }); fitPreview(); }, 600);
    });
  };

  /* ---- Fullscreen preview ---- */
  window.toggleFullscreen = function () {
    var stage = getStage();
    if (!stage) return;
    if (!document.fullscreenElement) {
      (stage.requestFullscreen || stage.webkitRequestFullscreen).call(stage);
    } else {
      (document.exitFullscreen || document.webkitExitFullscreen).call(document);
    }
  };
  document.addEventListener('fullscreenchange', fitPreview);

})();
