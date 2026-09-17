/* =========================================================================
   Public digital card runtime.
   Handles: event tracking, native share, lightbox, enquiry form, reduced
   motion and scroll reveal. Everything degrades gracefully without JS —
   links are real <a href> elements and the enquiry form posts normally.
   ========================================================================= */
(function () {
  'use strict';

  const root = document.querySelector('.dvc-card');
  if (!root) return;

  const cardSlug = root.dataset.slug || '';
  const base = document.documentElement.dataset.base || '';
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const trackingEnabled = root.dataset.track === '1';

  function url(path) {
    return base.replace(/\/$/, '') + '/' + String(path).replace(/^\//, '');
  }

  /* ------------------------------------------------------------ tracking */
  const sent = new Set();

  function track(event, label, once) {
    if (!trackingEnabled || !cardSlug || !event) return;
    const key = event + '|' + (label || '');
    if (once && sent.has(key)) return;
    sent.add(key);

    const payload = JSON.stringify({ slug: cardSlug, event: event, label: label || null });

    // sendBeacon survives the page unload that follows tel:/wa.me links.
    if (navigator.sendBeacon) {
      try {
        navigator.sendBeacon(url('api/v1/track'), new Blob([payload], { type: 'application/json' }));
        return;
      } catch (e) { /* fall through to fetch */ }
    }

    fetch(url('api/v1/track'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: payload,
      keepalive: true,
      credentials: 'same-origin'
    }).catch(function () {});
  }

  window.dvcTrack = track;

  document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-track]');
    if (!el) return;
    track(el.dataset.track, el.dataset.trackLabel, el.hasAttribute('data-track-once'));
  });

  // A QR scan arrives with ?src=qr appended by the QR target URL.
  const params = new URLSearchParams(window.location.search);
  const source = params.get('src') || params.get('utm_source');
  if (source === 'qr') track('qr_scan', 'qr');

  /* ------------------------------------------------------- share actions */
  const sheet = document.querySelector('[data-share-sheet]');
  const sheetBackdrop = document.querySelector('[data-share-backdrop]');

  function openSheet() {
    sheet && sheet.classList.add('open');
    sheetBackdrop && sheetBackdrop.classList.add('open');
  }
  function closeSheet() {
    sheet && sheet.classList.remove('open');
    sheetBackdrop && sheetBackdrop.classList.remove('open');
  }

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-share]');
    if (trigger) {
      e.preventDefault();
      const shareUrl = trigger.dataset.shareUrl || window.location.href;
      const title = trigger.dataset.shareTitle || document.title;
      const text = trigger.dataset.shareText || title;
      track('share', 'button');

      if (navigator.share) {
        navigator.share({ title: title, text: text, url: shareUrl }).catch(function () { openSheet(); });
      } else {
        openSheet();
      }
      return;
    }

    if (e.target.closest('[data-share-close]') || e.target === sheetBackdrop) {
      closeSheet();
    }

    const copyBtn = e.target.closest('[data-copy-link]');
    if (copyBtn) {
      e.preventDefault();
      const value = copyBtn.dataset.copyLink || window.location.href;
      const done = function () {
        copyBtn.textContent = 'Copied!';
        setTimeout(function () { copyBtn.textContent = copyBtn.dataset.label || 'Copy link'; }, 1800);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(done).catch(function () {});
      } else {
        const area = document.createElement('textarea');
        area.value = value; area.style.position = 'fixed'; area.style.opacity = '0';
        document.body.appendChild(area); area.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        area.remove();
      }
      track('share', 'copy_link');
    }
  });

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSheet(); closeLightbox(); } });

  /* ----------------------------------------------------------- lightbox */
  const lightbox = document.querySelector('[data-lightbox]');

  function openLightbox(html) {
    if (!lightbox) return;
    const holder = lightbox.querySelector('[data-lightbox-content]');
    if (holder) holder.innerHTML = html;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLightbox() {
    if (!lightbox) return;
    lightbox.classList.remove('open');
    const holder = lightbox.querySelector('[data-lightbox-content]');
    if (holder) holder.innerHTML = '';
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function (e) {
    const item = e.target.closest('[data-gallery-item]');
    if (item) {
      e.preventDefault();
      const type = item.dataset.galleryType;
      const src = item.dataset.gallerySrc;
      if (!src) return;
      if (type === 'youtube') {
        openLightbox('<iframe src="' + src + '" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>');
      } else if (type === 'video') {
        openLightbox('<video src="' + src + '" controls playsinline style="max-height:86vh;border-radius:12px"></video>');
      } else {
        openLightbox('<img src="' + src + '" alt="' + (item.dataset.galleryAlt || '') + '">');
      }
      return;
    }
    if (e.target.closest('[data-lightbox-close]') || e.target === lightbox) closeLightbox();
  });

  /* ------------------------------------------------------- enquiry form */
  const form = document.querySelector('[data-enquiry-form]');
  if (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const status = form.querySelector('[data-enquiry-status]');
      const button = form.querySelector('[type="submit"]');
      const original = button ? button.innerHTML : '';

      if (button) { button.disabled = true; button.innerHTML = 'Sending…'; }
      if (status) { status.className = 'dvc-form-status'; status.textContent = ''; }

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        const data = await response.json();

        if (response.ok && data.success) {
          if (status) { status.className = 'dvc-form-status ok'; status.textContent = data.message || 'Thank you! We will get back to you shortly.'; }
          form.reset();
          track('enquiry_sent', 'enquiry_form');
        } else {
          if (status) { status.className = 'dvc-form-status err'; status.textContent = data.message || 'Could not send your message. Please try again.'; }
        }
      } catch (err) {
        if (status) { status.className = 'dvc-form-status err'; status.textContent = 'Network error. Please check your connection and try again.'; }
      } finally {
        if (button) { button.disabled = false; button.innerHTML = original; }
      }
    });
  }

  /* --------------------------------------------- motion + scroll reveal */
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let lowPower = false;
  try {
    lowPower = (navigator.deviceMemory && navigator.deviceMemory <= 2) ||
               (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 2) ||
               (navigator.connection && /2g/.test(navigator.connection.effectiveType || ''));
  } catch (e) {}

  const storedMotion = (function () { try { return localStorage.getItem('dvc-motion'); } catch (e) { return null; } })();

  if (prefersReduced || lowPower || storedMotion === 'off' || root.dataset.reducedMotion === '1') {
    root.classList.add('no-motion');
  }

  document.querySelectorAll('[data-motion-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const off = root.classList.toggle('no-motion');
      try { localStorage.setItem('dvc-motion', off ? 'off' : 'on'); } catch (e) {}
      btn.setAttribute('aria-pressed', off ? 'true' : 'false');
    });
  });

  if (!root.classList.contains('no-motion') && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.dvc-section').forEach(function (section) { observer.observe(section); });
  } else {
    document.querySelectorAll('.dvc-section').forEach(function (section) { section.classList.add('visible'); });
  }

  /* --------------------------------------------------------- view timing */
  // A "real" view is recorded server-side on render; this records engaged
  // time so bounced previews do not distort the analytics.
  let engaged = false;
  const markEngaged = function () {
    if (engaged) return;
    engaged = true;
    track('engaged', 'dwell', true);
  };
  setTimeout(markEngaged, 8000);
  window.addEventListener('scroll', function () {
    if (window.scrollY > 300) markEngaged();
  }, { passive: true });
})();
