// js/add-to-list.js: sends "Add to list" clicks on library.php to lists.php.
// Load this BEFORE js/app.js so app.js's pretend add is skipped.
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

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-action="add-to-list"]');
    if (!btn) return;
    e.stopImmediatePropagation();
    e.preventDefault();

    var card = btn.closest('[data-book-id]');
    var select = btn.closest('.add-row') && btn.closest('.add-row').querySelector('select');
    if (!card || !select) return;
    if (!select.value) { toast('Select a list first.', false); return; }

    btn.disabled = true;
    fetch('lists.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ list_id: select.value, book_id: card.dataset.bookId }),
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { toast(data.message, data.ok); })
      .catch(function () { toast('Could not reach the server. Please try again.', false); })
      .then(function () { btn.disabled = false; });
  }, true);
})();
