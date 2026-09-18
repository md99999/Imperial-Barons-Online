/* Imperial Barons Online front-end: confirmation prompts and galaxy map pan/zoom. */
(function () {
  'use strict';

  // Buttons with data-confirm ask before submitting.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-confirm]');
    if (btn && !window.confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
  });

  // Prevent double submits (each action costs turns).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList || !form.classList.contains('ib-form')) return;
    if (form.dataset.submitted) { e.preventDefault(); return; }
    form.dataset.submitted = '1';
  });

  function initMap(svg) {
    var vb = svg.getAttribute('viewBox').split(/\s+/).map(Number);
    var home = { x: parseFloat(svg.dataset.cx), y: parseFloat(svg.dataset.cy) };
    var MIN = 60, MAX = 1100;

    function apply() {
      svg.setAttribute('viewBox', vb.join(' '));
      svg.classList.toggle('ib-zoomed-out', vb[2] > 450);
    }
    function zoom(factor, cx, cy) {
      var w = Math.min(MAX, Math.max(MIN, vb[2] * factor));
      var h = w * (vb[3] / vb[2]);
      if (cx === undefined) { cx = vb[0] + vb[2] / 2; cy = vb[1] + vb[3] / 2; }
      var rx = (cx - vb[0]) / vb[2], ry = (cy - vb[1]) / vb[3];
      vb = [cx - rx * w, cy - ry * h, w, h];
      apply();
    }
    function toSvg(clientX, clientY) {
      var r = svg.getBoundingClientRect();
      return { x: vb[0] + (clientX - r.left) / r.width * vb[2], y: vb[1] + (clientY - r.top) / r.height * vb[3] };
    }

    svg.addEventListener('wheel', function (e) {
      e.preventDefault();
      var pt = toSvg(e.clientX, e.clientY);
      zoom(e.deltaY > 0 ? 1.2 : 1 / 1.2, pt.x, pt.y);
    }, { passive: false });

    var drag = null, moved = false;
    svg.addEventListener('pointerdown', function (e) {
      drag = { x: e.clientX, y: e.clientY, vb: vb.slice() };
      moved = false;
    });
    window.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var r = svg.getBoundingClientRect();
      var dx = (e.clientX - drag.x) / r.width * drag.vb[2];
      var dy = (e.clientY - drag.y) / r.height * drag.vb[3];
      if (Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) > 4) {
        moved = true;
        svg.classList.add('ib-dragging');
      }
      vb = [drag.vb[0] - dx, drag.vb[1] - dy, drag.vb[2], drag.vb[3]];
      apply();
    });
    window.addEventListener('pointerup', function () {
      drag = null;
      svg.classList.remove('ib-dragging');
    });
    // A drag should not also follow a sector link.
    svg.addEventListener('click', function (e) { if (moved) { e.preventDefault(); moved = false; } }, true);

    var controls = svg.closest('.ib-panel').querySelectorAll('[data-map]');
    Array.prototype.forEach.call(controls, function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.getAttribute('data-map');
        if (action === 'in') zoom(1 / 1.5);
        if (action === 'out') zoom(1.5);
        if (action === 'home') { vb = [home.x - 125, home.y - 125, 250, 250]; apply(); }
        if (action === 'all') { vb = [-20, -20, 1040, 1040]; apply(); }
      });
    });
    apply();
  }

  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('svg.ib-map'), initMap);
  });
})();
