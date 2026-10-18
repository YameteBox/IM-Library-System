/* Forms handled by PHP instead of app.js:
   - data-server-search: GET search forms. Their submit/input/change/keyup events
     are kept away from app.js's client-side filtering.
   - data-native-submit: forms that must really POST (e.g. with a file upload).
     Only their submit event is kept away from app.js.
   In both cases the browser's default submission is left alone.
   Load this before app.js. */
(function () {
  'use strict';

  function closestForm(e, selector) {
    var t = e.target;
    return t && t.closest ? t.closest(selector) : null;
  }

  ['submit', 'input', 'change', 'keyup'].forEach(function (type) {
    document.addEventListener(type, function (e) {
      if (closestForm(e, 'form[data-server-search]')) e.stopImmediatePropagation();
    }, true);
  });

  document.addEventListener('submit', function (e) {
    if (closestForm(e, 'form[data-native-submit]')) e.stopImmediatePropagation();
  }, true);
})();
