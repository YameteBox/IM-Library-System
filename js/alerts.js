/* Converts the site's toast messages into Bootstrap dismissible, fading alerts.
   Loaded after bootstrap.bundle.min.js and before app.js, so anything app.js adds
   to a [data-toast-region] (or any element still using the old .toast classes)
   is upgraded on the fly. Existing elements, ids and listeners are kept. */
(function () {
  'use strict';

  var VARIANTS = { success: 'success', error: 'danger', danger: 'danger' };

  function variantOf(el) {
    for (var key in VARIANTS) {
      if (el.classList.contains('toast--' + key)) return VARIANTS[key];
    }
    return null;
  }

  function setVariant(el, variant) {
    el.classList.remove('alert-success', 'alert-danger');
    el.classList.add('alert-' + variant);
  }

  function convert(el) {
    if (el.dataset.alertReady) return;
    el.dataset.alertReady = '1';

    setVariant(el, variantOf(el) || 'success');
    el.classList.remove('toast', 'toast--success', 'toast--error', 'toast--danger');
    el.classList.add('alert', 'alert-dismissible', 'fade');
    el.setAttribute('role', 'alert');

    var close = el.querySelector('.toast__close, .btn-close');
    if (!close) {
      close = document.createElement('button');
      close.type = 'button';
      el.appendChild(close);
    }
    close.classList.remove('toast__close');
    close.classList.add('btn-close');
    close.textContent = '';
    close.setAttribute('aria-label', 'Close');

    // Messages created on the fly are removed by Bootstrap's dismiss.
    // Elements with an id are reused by app.js, so they are only styled.
    if (!el.id) close.setAttribute('data-bs-dismiss', 'alert');

    // Fade in
    requestAnimationFrame(function () { el.classList.add('show'); });

    // app.js may flip toast--success / toast--error on a reused element
    if (el.id) {
      new MutationObserver(function () {
        var v = variantOf(el);
        if (!v) return;
        el.classList.remove('toast', 'toast--success', 'toast--error', 'toast--danger');
        setVariant(el, v);
      }).observe(el, { attributes: true, attributeFilter: ['class'] });
    }
  }

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.classList.contains('toast')) convert(root);
    root.querySelectorAll('.toast').forEach(convert);
  }

  document.querySelectorAll('.toast').forEach(convert);

  new MutationObserver(function (mutations) {
    mutations.forEach(function (m) { m.addedNodes.forEach(scan); });
  }).observe(document.body, { childList: true, subtree: true });
})();
