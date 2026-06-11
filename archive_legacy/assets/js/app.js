/* =====================================================================
   Avatar Electric — Dashboard front-end scripts
   ===================================================================== */
(function () {
  'use strict';

  // Bootstrap form validation
  document.querySelectorAll('.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  if (typeof window.DASHBOARD === 'undefined' || typeof Chart === 'undefined') {
    return;
  }

  Chart.defaults.font.family = "'Prompt','Sarabun',sans-serif";
  Chart.defaults.color = '#6B7280';

  // brand colors pulled from CSS tokens (no hardcoding)
  var css = getComputedStyle(document.documentElement);
  var BRAND = (css.getPropertyValue('--primary-color') || '#FF7A00').trim();
  var GRIDC = (css.getPropertyValue('--gray-200') || '#E5E7EB').trim();

  // ---- Doughnut: status distribution ----
  var sc = document.getElementById('statusChart');
  if (sc) {
    new Chart(sc, {
      type: 'doughnut',
      data: {
        labels: window.DASHBOARD.status.labels,
        datasets: [{
          data: window.DASHBOARD.status.data,
          backgroundColor: window.DASHBOARD.status.colors,
          borderWidth: 2,
          borderColor: '#fff',
          hoverOffset: 6
        }]
      },
      options: {
        cutout: '68%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                var pct = total ? Math.round(ctx.parsed / total * 100) : 0;
                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }

  // ---- Doughnut: panel status distribution ----
  var pc = document.getElementById('panelChart');
  if (pc && window.DASHBOARD.panels) {
    // drop zero-count slices for a cleaner chart
    var pl = [], pd = [], pcol = [];
    window.DASHBOARD.panels.data.forEach(function (v, i) {
      if (v > 0) {
        pl.push(window.DASHBOARD.panels.labels[i]);
        pd.push(v);
        pcol.push(window.DASHBOARD.panels.colors[i]);
      }
    });
    if (pd.length) {
      new Chart(pc, {
        type: 'doughnut',
        data: { labels: pl, datasets: [{ data: pd, backgroundColor: pcol, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
        options: {
          cutout: '68%',
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: function (ctx) {
              var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
              var pct = total ? Math.round(ctx.parsed / total * 100) : 0;
              return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
            } } }
          }
        }
      });
    }
  }

  // ---- Bar: projects per month ----
  var mc = document.getElementById('monthChart');
  if (mc) {
    new Chart(mc, {
      type: 'bar',
      data: {
        labels: window.DASHBOARD.months.labels,
        datasets: [{
          label: 'จำนวนโครงการ (ตามกำหนดส่ง)',
          data: window.DASHBOARD.months.data,
          backgroundColor: BRAND,
          borderRadius: 6,
          maxBarThickness: 38
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRIDC } },
          x: { grid: { display: false } }
        }
      }
    });
  }
})();
