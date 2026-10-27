// js/borrow.js: sends "Request to borrow" clicks to borrow.php.
// Load this BEFORE js/app.js so it runs first and app.js's pretend borrow is skipped.
(function () {
  function toast(message, ok) {
    var region = document.querySelector('[data-toast-region]');
    if (!region) { alert(message); return; }
    var div = document.createElement('div');
    div.className = 'alert alert-' + (ok ? 'success' : 'danger') + ' alert-dismissible fade show';
    div.setAttribute('role', 'alert');
    div.textContent = message;
    region.appendChild(div);
    setTimeout(function () { div.remove(); }, 5000);
  }

  // Capture phase + stopImmediatePropagation, same pattern the other pages use
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-action="borrow"]');
    if (!btn) return;
    e.stopImmediatePropagation();
    e.preventDefault();

    if (btn.disabled || btn.classList.contains('is-disabled')) return;

    var card = btn.closest('[data-book-id]');
    if (!card) return;

    btn.disabled = true;
    var body = new URLSearchParams({ book_id: card.dataset.bookId });

    fetch('borrow.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body,
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        toast(data.message, data.ok);
        if (data.ok) {
          btn.textContent = 'Borrow request pending';
          btn.classList.add('is-disabled');
        } else {
          btn.disabled = false;
        }
      })
      .catch(function () {
        toast('Could not reach the server. Please try again.', false);
        btn.disabled = false;
      });
  }, true);
})();
