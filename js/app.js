/* ==========================================================
   Manila Science High School Online Library
   Light-weight prototype behaviour. No backend: every action
   mirrors the interactions defined in the Penpot prototype.
   ========================================================== */
(function () {
  "use strict";

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var page = document.body.getAttribute("data-page");

  /* ---------- Saved preferences (dark mode) ---------- */
  function readPref(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
  function writePref(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ } }
  if (readPref("plm-dark") === "1") document.body.classList.add("dark");

  /* ---------- Toasts ---------- */
  function toast(message, type, delay) {
    var region = $("[data-toast-region]");
    if (!region) return;
    var el = document.createElement("div");
    el.className = "toast toast--" + (type || "success");
    el.setAttribute("role", type === "error" ? "alert" : "status");
    var text = document.createElement("span");
    text.textContent = message;
    var close = document.createElement("button");
    close.type = "button";
    close.className = "toast__close";
    close.setAttribute("aria-label", "Dismiss message");
    close.textContent = "\u2715";
    close.addEventListener("click", function () { el.remove(); });
    el.appendChild(text);
    el.appendChild(close);
    region.innerHTML = "";
    region.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, delay || 5000);
  }
  var ok = function (m) { toast(m, "success"); };
  var err = function (m) { toast(m, "error"); };

  function value(form, name) {
    var f = form.elements[name];
    return f ? f.value.trim() : "";
  }

  /* ---------- Selects: grey placeholder until a value is picked ---------- */
  $$("select.select").forEach(function (s) {
    var sync = function () { s.classList.toggle("has-value", s.selectedIndex > 0); };
    s.addEventListener("change", sync);
    sync();
  });

  /* ---------- Dark mode switch + other switches ---------- */
  $$(".switch").forEach(function (sw) {
    sw.addEventListener("click", function () {
      var on = sw.getAttribute("aria-checked") !== "true";
      sw.setAttribute("aria-checked", String(on));
      if (sw.id === "dark-switch") {
        document.body.classList.toggle("dark", on);
        writePref("plm-dark", on ? "1" : "0");
      }
    });
    if (sw.id === "dark-switch" && document.body.classList.contains("dark")) {
      sw.setAttribute("aria-checked", "true");
    }
  });

  /* ---------- Helpers for filtering card collections ---------- */
  function filterCards(selector, query, typeValue) {
    var q = (query || "").toLowerCase();
    var t = (typeValue || "").toLowerCase();
    $$(selector).forEach(function (card) {
      var hay = (card.getAttribute("data-search") || card.textContent).toLowerCase();
      var type = (card.getAttribute("data-type") || "").toLowerCase();
      var show = (!q || hay.indexOf(q) !== -1) && (!t || type === t);
      card.hidden = !show;
    });
  }

  var KNOWN_STUDENTS = ["2023-00123", "2023-00456", "2023-00789", "2024-00100"];

  /* ==========================================================
     Page behaviours
     ========================================================== */

  var handlers = {};

  /* --- Student login --- */
  handlers.login = function () {
    $("#login-form").addEventListener("submit", function (e) {
      e.preventDefault();
      var id = value(this, "student");
      var pw = this.elements.password.value;
      if (!id || !pw) return err("Invalid username/student ID or password.");
      if (id.toLowerCase() === "banned") return err("User has been banned.");
      window.location.href = "welcome.php";
    });
  };

  /* --- Student registration --- */
  handlers.register = function () {
    var form = $("#register-form");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (this.elements.password.value !== this.elements.confirm.value) {
        return err("Passwords do not match.");
      }
      $("#register-view").hidden = true;
      $("#success-view").hidden = false;
    });
    $("#register-another").addEventListener("click", function () {
      form.reset();
      $("#success-view").hidden = true;
      $("#register-view").hidden = false;
    });
  };

  /* --- Admin login --- */
  handlers["admin-login"] = function () {
    $("#login-form").addEventListener("submit", function (e) {
      e.preventDefault();
      if (!value(this, "admin") || !this.elements.password.value) {
        return err("Invalid username/admin ID or password.");
      }
      window.location.href = "admin-welcome.php";
    });
  };

  /* --- Library --- */
  handlers.library = function () {
    $("#search-form").addEventListener("submit", function (e) {
      e.preventDefault();
      filterCards(".book-card", value(this, "q"), this.elements.type.selectedIndex > 0 ? this.elements.type.value : "");
    });
    var carousel = $("#new-books");
    $("#prev-books").addEventListener("click", function () { carousel.scrollBy({ left: -400, behavior: "smooth" }); });
    $("#next-books").addEventListener("click", function () { carousel.scrollBy({ left: 400, behavior: "smooth" }); });
  };

  /* --- Advanced search --- */
  handlers["advanced-search"] = function () {
    var form = $("#advanced-form");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      window.location.href = "library.php";
    });
    form.addEventListener("reset", function () {
      setTimeout(function () { $$("select.select", form).forEach(function (s) { s.classList.remove("has-value"); }); }, 0);
    });
  };

  /* --- My lists --- */
  handlers["my-lists"] = function () {
    var grid = $("#list-grid");
    $("#create-form").addEventListener("submit", function (e) {
      e.preventDefault();
      var name = value(this, "name");
      if (!name) return err("List name cannot be empty.");
      var tpl = $("#list-template").content.cloneNode(true);
      var card = tpl.querySelector(".list-card");
      card.querySelector(".list-card__name").textContent = name;
      card.querySelector("input").value = name;
      grid.prepend(tpl);
      this.reset();
      ok("List \u201C" + name + "\u201D created.");
    });
    grid.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-action]");
      if (!btn) return;
      var card = btn.closest(".list-card");
      if (btn.dataset.action === "rename-list") {
        var field = card.querySelector("input");
        if (!field.value.trim()) return err("List name cannot be empty.");
        card.querySelector(".list-card__name").textContent = field.value.trim();
        ok("List renamed to \u201C" + field.value.trim() + "\u201D.");
      }
      if (btn.dataset.action === "delete-list") {
        var n = card.querySelector(".list-card__name").textContent;
        card.remove();
        ok("List \u201C" + n + "\u201D deleted.");
      }
    });
  };

  /* --- List view --- */
  handlers["list-view"] = function () {
    var count = $("#list-count");
    $("#list-books").addEventListener("click", function (e) {
      var btn = e.target.closest("[data-action]");
      if (!btn) return;
      var card = btn.closest(".book-card");
      if (btn.dataset.action === "remove-from-list") {
        card.remove();
        count.textContent = $$("#list-books .book-card").length;
        ok("Book removed from the list.");
      }
      if (btn.dataset.action === "borrow") {
        ok("You borrowed \u201C" + card.dataset.title + "\u201D");
        btn.textContent = "Borrow request pending";
        btn.classList.add("is-disabled");
      }
    });
  };

  /* --- Profile (student + admin) --- */
  function profilePage() {
    $$("[data-save]").forEach(function (btn) {
      btn.addEventListener("click", function () { ok("Profile updated."); });
    });
    var bio = $("#bio");
    var swatch = $("#swatch");
    $$("input[name=color-mode]").forEach(function (r) {
      r.addEventListener("change", function () {
        if (swatch) swatch.style.background = r.value === "gradient"
          ? "linear-gradient(135deg, #1e5eba, #7a3cc9)" : "";
      });
    });
    $$("[data-borrow-tab]").forEach(function (tab) {
      tab.addEventListener("click", function () {
        $$("[data-borrow-tab]").forEach(function (t) { t.classList.toggle("is-active", t === tab); });
        var overdueOnly = tab.dataset.borrowTab === "overdue";
        $$("#borrow-list .borrow-row").forEach(function (row) {
          row.hidden = overdueOnly && !row.hasAttribute("data-overdue");
        });
      });
    });
    void bio;
  }
  handlers.profile = profilePage;
  handlers["admin-profile"] = profilePage;

  /* --- Search profiles --- */
  handlers["search-profiles"] = function () {
    $("#profile-search").addEventListener("submit", function (e) {
      e.preventDefault();
      filterCards(".user-card", value(this, "q"));
    });
    $("#user-grid").addEventListener("click", function (e) {
      var btn = e.target.closest("[data-action]");
      if (!btn) return;
      if (btn.dataset.action === "friend") {
        btn.textContent = "Friend added";
        btn.classList.add("is-disabled");
      }
      if (btn.dataset.action === "block") {
        var card = btn.closest(".user-card");
        card.hidden = true;
      }
    });
  };

  /* --- Admin library (catalog + add item) --- */
  handlers["admin-library"] = function () {
    var form = $("#add-item-form");
    $("#toggle-add").addEventListener("click", function () {
      var open = form.hidden;
      form.hidden = !open;
      this.setAttribute("aria-expanded", String(open));
      if (open) $("input", form).focus();
    });
    $("#catalog-search").addEventListener("submit", function (e) {
      e.preventDefault();
      filterCards("#catalog .book-card", value(this, "q"), this.elements.type.selectedIndex > 0 ? this.elements.type.value : "");
    });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var title = value(this, "title");
      var copies = parseInt(this.elements.copies.value, 10);
      if (!title) return err("Enter a title for the item.");
      if (!(copies > 0)) return err("Enter a number of copies greater than zero.");
      ok("Added \u201C" + title + "\u201D to the catalog.");
      this.reset();
      this.elements.copies.value = 1;
    });
    $("#catalog").addEventListener("click", function (e) {
      var btn = e.target.closest("[data-action]");
      if (!btn) return;
      var card = btn.closest(".book-card");
      if (btn.dataset.action === "delete-item") {
        var title = card.dataset.title;
        card.remove();
        ok("Removed \u201C" + title + "\u201D from the catalog.");
      }
    });
  };

  /* --- Admin requests / on loan --- */
  function requestPage(verbs) {
    return function () {
      $("#request-grid").addEventListener("click", function (e) {
        var btn = e.target.closest("[data-action]");
        if (!btn) return;
        var card = btn.closest(".request-card");
        var title = card.dataset.title;
        var msg = verbs[btn.dataset.action];
        if (!msg) return;
        card.remove();
        ok(msg.replace("{t}", title));
      });
    };
  }
  handlers["admin-requests"] = requestPage({
    approve: "Approved the borrow request for \u201C{t}\u201D.",
    reject: "Rejected the borrow request for \u201C{t}\u201D."
  });
  handlers["admin-on-loan"] = requestPage({ "return": "Marked \u201C{t}\u201D as returned." });

  /* --- Register a student (admin) --- */
  handlers["register-student"] = function () {
    var form = $("#register-form");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (this.elements.password.value !== this.elements.confirm.value) {
        return err("Passwords do not match.");
      }
      var id = value(this, "student");
      var user = value(this, "username").toLowerCase();
      if (id === "2023-00123" || user === "maria.santos") {
        return err("That Student ID or username is already registered.");
      }
      $("#register-view").hidden = true;
      $("#success-view").hidden = false;
    });
    $("#register-another").addEventListener("click", function () {
      form.reset();
      $("#success-view").hidden = true;
      $("#register-view").hidden = false;
    });
  };

  /* --- Suspend / unsuspend --- */
  handlers["suspend-user"] = function () {
    var form = $("#suspend-form");
    function run(action) {
      var id = value(form, "student");
      if (KNOWN_STUDENTS.indexOf(id) === -1) return err("Student ID does not exist.");
      $("#result-text").textContent = "Student #" + id + " has been " + action + ".";
      $("#manage-view").hidden = true;
      $("#result-view").hidden = false;
    }
    $("#do-suspend").addEventListener("click", function () { run("suspended"); });
    $("#do-unsuspend").addEventListener("click", function () { run("unsuspended"); });
    $("#result-close").addEventListener("click", function () { $("#result-alert").hidden = true; });
    $("#manage-another").addEventListener("click", function () {
      $("#result-alert").hidden = false;
      $("#result-view").hidden = true;
      $("#manage-view").hidden = false;
    });
    $("#profile-search").addEventListener("submit", function (e) {
      e.preventDefault();
      filterCards(".user-card", value(this, "q"));
    });
  };

  /* ---------- Shared: borrow / add-to-list buttons on library cards ---------- */
  function wireCatalogButtons() {
    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-action]");
      if (!btn) return;
      var card = btn.closest(".book-card");
      if (!card) return;
      if (btn.dataset.action === "borrow" && page === "library") {
        if (btn.classList.contains("is-disabled")) return;
        if (card.dataset.copies === "0") {
          return err("Sorry, there are no copies of \u201C" + card.dataset.title + "\u201D available right now.");
        }
        ok("You borrowed \u201C" + card.dataset.title + "\u201D");
        btn.textContent = "Borrow request pending";
        btn.classList.add("is-disabled");
      }
      if (btn.dataset.action === "add-to-list") {
        var select = card.querySelector("select");
        if (!select || select.selectedIndex < 1) return err("Select a list first.");
        ok("\u201C" + card.dataset.title + "\u201D added to \u201C" + select.value + "\u201D.");
      }
    });
  }
  if (page === "library") wireCatalogButtons();

  if (handlers[page]) handlers[page]();
})();
