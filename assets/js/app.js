/* =========================================================================
   Digital Visiting Card — panel/site JavaScript.
   Vanilla JS, no framework. Progressive enhancement only: every action has
   a working server-side fallback.
   ========================================================================= */
(function () {
  'use strict';

  const DVC = {};
  window.DVC = DVC;

  DVC.base = document.documentElement.dataset.base || '';
  DVC.token = document.querySelector('meta[name="csrf-token"]')?.content || '';

  /* ------------------------------------------------------------- helpers */
  DVC.url = function (path) {
    if (/^https?:\/\//i.test(path)) return path;
    return DVC.base.replace(/\/$/, '') + '/' + String(path).replace(/^\//, '');
  };

  DVC.toast = function (message, type) {
    let host = document.getElementById('toasts');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toasts';
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = 'toast ' + (type || 'info');
    el.setAttribute('role', 'status');
    el.textContent = message;
    host.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transform = 'translateX(20px)';
      el.style.transition = 'all 250ms ease';
      setTimeout(function () { el.remove(); }, 260);
    }, 4200);
  };

  DVC.request = async function (url, options) {
    options = options || {};
    const headers = Object.assign({
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }, options.headers || {});

    if (!(options.body instanceof FormData) && options.body) {
      headers['Content-Type'] = 'application/json';
    }
    if (options.method && options.method.toUpperCase() !== 'GET') {
      headers['X-CSRF-Token'] = DVC.token;
    }

    const response = await fetch(DVC.url(url), {
      method: options.method || 'GET',
      headers: headers,
      body: options.body,
      credentials: 'same-origin'
    });

    let payload = null;
    try { payload = await response.json(); } catch (e) { payload = null; }

    if (!response.ok) {
      const message = (payload && payload.message) || 'Request failed (' + response.status + ')';
      const error = new Error(message);
      error.status = response.status;
      error.payload = payload;
      throw error;
    }
    return payload;
  };

  DVC.copy = async function (text, successMessage) {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
      }
      DVC.toast(successMessage || 'Copied to clipboard', 'success');
      return true;
    } catch (e) {
      DVC.toast('Could not copy — please copy manually.', 'error');
      return false;
    }
  };

  DVC.debounce = function (fn, wait) {
    let timer;
    return function () {
      const args = arguments, context = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(context, args); }, wait || 300);
    };
  };

  /* ------------------------------------------------------ navigation/UI  */
  function initNav() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        const open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    if (sidebarToggle && sidebar) {
      const close = function () {
        sidebar.classList.remove('open');
        backdrop && backdrop.classList.remove('open');
        sidebarToggle.setAttribute('aria-expanded', 'false');
      };
      sidebarToggle.addEventListener('click', function () {
        const open = sidebar.classList.toggle('open');
        backdrop && backdrop.classList.toggle('open', open);
        sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      backdrop && backdrop.addEventListener('click', close);
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    }
  }

  function initTheme() {
    const stored = localStorage.getItem('dvc-theme');
    if (stored) document.documentElement.setAttribute('data-theme', stored);

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('dvc-theme', next); } catch (e) {}
      });
    });
  }

  function initConfirms() {
    document.addEventListener('submit', function (e) {
      const form = e.target;
      const message = form.dataset.confirm;
      if (message && !window.confirm(message)) {
        e.preventDefault();
        return;
      }
      const submit = form.querySelector('[type="submit"]:not([data-no-lock])');
      if (submit && !form.dataset.noLock) {
        setTimeout(function () {
          submit.disabled = true;
          submit.dataset.original = submit.innerHTML;
          submit.innerHTML = '<span class="spinner"></span> Working…';
        }, 0);
      }
    });

    document.addEventListener('click', function (e) {
      const el = e.target.closest('[data-confirm-link]');
      if (el && !window.confirm(el.dataset.confirmLink)) e.preventDefault();
    });
  }

  function initCopyButtons() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-copy]');
      if (!btn) return;
      e.preventDefault();
      const target = btn.dataset.copy;
      const value = target.startsWith('#')
        ? (document.querySelector(target) || {}).value
        : target;
      if (value) DVC.copy(value, btn.dataset.copyMessage);
    });
  }

  function initModals() {
    document.addEventListener('click', function (e) {
      const opener = e.target.closest('[data-modal-open]');
      if (opener) {
        e.preventDefault();
        const modal = document.getElementById(opener.dataset.modalOpen);
        if (modal) {
          modal.classList.add('open');
          const focusable = modal.querySelector('input, select, textarea, button');
          focusable && focusable.focus();
          if (opener.dataset.modalFill) {
            try {
              const data = JSON.parse(opener.dataset.modalFill);
              Object.keys(data).forEach(function (key) {
                const field = modal.querySelector('[name="' + key + '"]');
                if (!field) return;
                if (field.type === 'checkbox') field.checked = !!data[key];
                else field.value = data[key] === null ? '' : data[key];
              });
              const action = opener.dataset.modalAction;
              const form = modal.querySelector('form');
              if (action && form) form.action = action;
            } catch (err) {}
          }
        }
        return;
      }

      const closer = e.target.closest('[data-modal-close]');
      if (closer) {
        e.preventDefault();
        const modal = closer.closest('.modal-backdrop');
        modal && modal.classList.remove('open');
        return;
      }

      if (e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('open');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach(function (m) { m.classList.remove('open'); });
      }
    });
  }

  function initFilePreviews() {
    document.addEventListener('change', function (e) {
      const input = e.target;
      if (input.type !== 'file' || !input.dataset.preview) return;
      const preview = document.querySelector(input.dataset.preview);
      const file = input.files && input.files[0];
      if (!preview || !file) return;

      if (input.dataset.maxSize && file.size > parseInt(input.dataset.maxSize, 10)) {
        DVC.toast('That file is larger than the allowed limit.', 'error');
        input.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = function (ev) {
        preview.src = ev.target.result;
        preview.classList.remove('hidden');
      };
      reader.readAsDataURL(file);
    });

    document.querySelectorAll('.upload-zone').forEach(function (zone) {
      const input = zone.querySelector('input[type="file"]');
      if (!input) return;
      zone.addEventListener('click', function (e) { if (e.target !== input) input.click(); });
      ['dragenter', 'dragover'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('dragover'); });
      });
      ['dragleave', 'drop'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.remove('dragover'); });
      });
      zone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });
  }

  function initSlugChecker() {
    const input = document.querySelector('[data-slug-check]');
    if (!input) return;
    const status = document.querySelector(input.dataset.slugStatus || '#slug-status');
    const cardId = input.dataset.cardId || '';

    const check = DVC.debounce(async function () {
      const value = input.value.trim().toLowerCase();
      input.value = value;
      if (value.length < 3) {
        if (status) { status.textContent = 'At least 3 characters.'; status.className = 'hint'; }
        return;
      }
      try {
        const result = await DVC.request('api/v1/slug/available?slug=' + encodeURIComponent(value) + (cardId ? '&card_id=' + cardId : ''));
        if (status) {
          status.textContent = result.available ? '✓ ' + value + ' is available' : '✕ ' + (result.message || 'Already taken');
          status.className = result.available ? 'hint' : 'error-text';
          status.style.color = result.available ? 'var(--success)' : '';
        }
      } catch (err) {
        if (status) { status.textContent = ''; }
      }
    }, 420);

    input.addEventListener('input', check);
  }

  /* --------------------------------------------------------- mini charts */
  DVC.lineChart = function (el, series, options) {
    options = options || {};
    if (!el || !series || !series.length) return;

    const width = 640, height = 220, padding = { top: 16, right: 12, bottom: 26, left: 40 };
    const values = series.map(function (p) { return p.value; });
    const max = Math.max.apply(null, values.concat([1]));
    const innerW = width - padding.left - padding.right;
    const innerH = height - padding.top - padding.bottom;
    const stepX = series.length > 1 ? innerW / (series.length - 1) : 0;
    const color = options.color || getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#4f46e5';

    const points = series.map(function (p, i) {
      const x = padding.left + i * stepX;
      const y = padding.top + innerH - (p.value / max) * innerH;
      return [x, y];
    });

    const line = points.map(function (p, i) { return (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join(' ');
    const area = line + ' L' + (padding.left + innerW).toFixed(1) + ' ' + (padding.top + innerH) + ' L' + padding.left + ' ' + (padding.top + innerH) + ' Z';

    let grid = '';
    for (let i = 0; i <= 4; i++) {
      const y = padding.top + (innerH / 4) * i;
      const label = Math.round(max - (max / 4) * i);
      grid += '<line x1="' + padding.left + '" y1="' + y + '" x2="' + (width - padding.right) + '" y2="' + y + '" stroke="currentColor" stroke-opacity=".12"/>';
      grid += '<text x="' + (padding.left - 8) + '" y="' + (y + 4) + '" text-anchor="end" font-size="10" fill="currentColor" fill-opacity=".55">' + label + '</text>';
    }

    let labels = '';
    const every = Math.max(1, Math.ceil(series.length / 6));
    series.forEach(function (p, i) {
      if (i % every !== 0 && i !== series.length - 1) return;
      const x = padding.left + i * stepX;
      const text = (p.label || '').slice(5);
      labels += '<text x="' + x + '" y="' + (height - 8) + '" text-anchor="middle" font-size="10" fill="currentColor" fill-opacity=".55">' + text + '</text>';
    });

    const id = 'g' + Math.random().toString(36).slice(2, 8);
    el.innerHTML =
      '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" role="img" aria-label="' + (options.label || 'chart') + '">' +
      '<defs><linearGradient id="' + id + '" x1="0" y1="0" x2="0" y2="1">' +
      '<stop offset="0%" stop-color="' + color + '" stop-opacity=".30"/>' +
      '<stop offset="100%" stop-color="' + color + '" stop-opacity="0"/></linearGradient></defs>' +
      grid +
      '<path d="' + area + '" fill="url(#' + id + ')"/>' +
      '<path d="' + line + '" fill="none" stroke="' + color + '" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>' +
      labels +
      '</svg>';
  };

  DVC.barChart = function (el, series, options) {
    options = options || {};
    if (!el || !series || !series.length) return;
    const max = Math.max.apply(null, series.map(function (p) { return p.value; }).concat([1]));
    const color = options.color || 'var(--brand)';
    el.innerHTML = '<div class="stack" style="--stack-gap:10px">' + series.map(function (p) {
      const pct = Math.round((p.value / max) * 100);
      return '<div><div class="row-between tiny" style="margin-bottom:4px"><span>' +
        String(p.label).replace(/[<>&]/g, '') + '</span><strong>' + p.value + '</strong></div>' +
        '<div class="progress"><span style="width:' + pct + '%;background:' + color + '"></span></div></div>';
    }).join('') + '</div>';
  };

  function initCharts() {
    document.querySelectorAll('[data-chart]').forEach(function (el) {
      let data;
      try { data = JSON.parse(el.dataset.chart); } catch (e) { return; }
      if (el.dataset.chartType === 'bar') DVC.barChart(el, data, { color: el.dataset.chartColor });
      else DVC.lineChart(el, data, { color: el.dataset.chartColor, label: el.dataset.chartLabel });
    });
  }

  /* --------------------------------------------------------- auto-submit */
  function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
      el.addEventListener('change', function () { el.form && el.form.submit(); });
    });

    document.querySelectorAll('[data-live-search]').forEach(function (input) {
      const submit = DVC.debounce(function () { input.form && input.form.submit(); }, 550);
      input.addEventListener('input', submit);
    });
  }

  /* --------------------------------------------------------- PWA install */
  function initPwa() {
    if ('serviceWorker' in navigator && document.documentElement.dataset.sw === '1') {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register(DVC.url('service-worker.js')).catch(function () {});
      });
    }

    let deferred = null;
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferred = e;
      document.querySelectorAll('[data-pwa-install]').forEach(function (btn) {
        btn.classList.remove('hidden');
        btn.addEventListener('click', async function () {
          if (!deferred) return;
          deferred.prompt();
          await deferred.userChoice;
          deferred = null;
          btn.classList.add('hidden');
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initTheme();
    initConfirms();
    initCopyButtons();
    initModals();
    initFilePreviews();
    initSlugChecker();
    initCharts();
    initAutoSubmit();
    initPwa();
  });
})();
