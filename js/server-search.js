/* Search forms marked data-server-search are answered by PHP (GET request).
   Stop the page script's client-side handlers for them, but leave the browser's
   default form submission alone. Load this before app.js. */
(function () {
  'use strict';
  function intercept(e) {
    var t = e.target;
    var form = t && t.closest ? t.closest('form[data-server-search]') : null;
    if (form) e.stopImmediatePropagation();
  }
  ['submit', 'input', 'change', 'keyup'].forEach(function (type) {
    document.addEventListener(type, intercept, true);
  });
})();
