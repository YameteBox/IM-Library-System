// Replaces the browser confirm() on every logout link with an on-page modal box.
// Works for any link whose href ends in logout.php or admin-logout.php.
(function () {
  var css =
    '.lo-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:9999;padding:16px}' +
    '.lo-overlay.is-open{display:flex}' +
    '.lo-box{background:#fff;color:#111827;border-radius:14px;padding:24px;max-width:380px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.25);font-family:inherit}' +
    '.lo-box h2{margin:0 0 8px;font-size:1.15rem}' +
    '.lo-box p{margin:0 0 20px;font-size:.95rem}' +
    '.lo-actions{display:flex;gap:10px;justify-content:flex-end}' +
    '.lo-btn{border:0;border-radius:999px;padding:9px 18px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}' +
    '.lo-btn--cancel{background:#e5e7eb;color:#111827}' +
    '.lo-btn--confirm{background:#b91c1c;color:#fff}';

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var overlay = document.createElement('div');
  overlay.className = 'lo-overlay';
  overlay.innerHTML =
    '<div class="lo-box" role="dialog" aria-modal="true" aria-labelledby="lo-title">' +
    '<h2 id="lo-title">Log out</h2>' +
    '<p>Are you sure you want to log out?</p>' +
    '<div class="lo-actions">' +
    '<button type="button" class="lo-btn lo-btn--cancel" id="lo-cancel">Cancel</button>' +
    '<a class="lo-btn lo-btn--confirm" id="lo-confirm" href="#">Log out</a>' +
    '</div></div>';
  document.body.appendChild(overlay);

  var confirmLink = overlay.querySelector('#lo-confirm');
  var cancelBtn = overlay.querySelector('#lo-cancel');

  function close() { overlay.classList.remove('is-open'); }

  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href$="logout.php"]') : null;
    if (!a || a.id === 'lo-confirm') return;
    e.preventDefault();
    e.stopPropagation(); // stops the old inline confirm() from running
    confirmLink.href = a.getAttribute('href');
    overlay.classList.add('is-open');
    cancelBtn.focus();
  }, true);

  cancelBtn.addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
