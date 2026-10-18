<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .tos-box { height: 170px; overflow-y: auto; padding: 12px 14px; font-size: 13px; line-height: 1.5; text-align: left;
      border: 1px solid color-mix(in srgb, currentColor 30%, transparent); border-radius: 10px; }
    .tos-box:focus { outline: 2px solid #1E5EBA; outline-offset: 2px; }
    .tos-box h3 { font-size: 13px; margin: 12px 0 4px; }
    .tos-box h3:first-child { margin-top: 0; }
    .tos-box p { margin: 0 0 6px; }
    .tos-hint { font-size: 12px; margin: 6px 0 0; opacity: .75; }
    .tos-hint.is-done { opacity: 1; color: #1a7f4b; }
    .tos-accept { margin-top: 8px; }
    .tos-accept input:disabled + span { opacity: .55; }
    .btn[disabled] { opacity: .5; cursor: not-allowed; }
  </style>
</head>
<body data-page="register">
  <div class="auth-shell">
    <div class="auth-stage">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library Management</h1>
          
        </div>
      </div>
    </header>
      <main class="auth-body">
        <section class="auth-card" aria-labelledby="t">
          <div id="register-view" class="form-stack">
            <h2 class="card-title" id="t">Register an account</h2>
            <p class="sub">Books books books</p>
            <div class="toast-region" data-toast-region aria-live="polite"></div>
            <form class="form-stack" id="register-form" novalidate>
              <div class="field">
          <label for="f-student">Student Number</label>
          <input class="input" id="f-student" name="student" type="text" value="2023-00123" placeholder="" >
        </div>
              <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username" type="text" value="maria.santos" placeholder="" autocomplete="username">
        </div>
              <div class="field">
          <label for="f-email">Email</label>
          <input class="input" id="f-email" name="email" type="email" value="maria@example.com" placeholder="" >
        </div>
              <div class="field">
          <label for="f-password">Password</label>
          <input class="input" id="f-password" name="password" type="password" value="password" placeholder="" autocomplete="new-password">
        </div>
              <div class="field">
          <label for="f-confirm">Confirm password</label>
          <input class="input" id="f-confirm" name="confirm" type="password" value="password" placeholder="" autocomplete="new-password">
        </div>
            <div class="field">
              <label id="tos-label">Terms of Service</label>
              <div class="tos-box" id="tos-box" tabindex="0" role="region" aria-labelledby="tos-label" aria-describedby="tos-hint">
                <h3>1. Your account</h3>
                <p>Your account is for your own use as a student of Manila Science High School. Keep your password private and do not let anyone else use your account. You are responsible for everything done through it. The information you give when registering, such as your student number, must be true and your own.</p>
                <h3>2. Borrowing</h3>
                <p>Requests to borrow are reviewed by the library staff and are not final until approved. Borrowed items must be returned on or before their due date and in the condition you received them. You may not lend borrowed items to other people.</p>
                <h3>3. Overdue, lost and damaged items</h3>
                <p>Items returned late may be charged a daily fine, shown on your profile page. If you lose or damage an item, you may be asked to repair or replace it, or to pay for it, as decided by the library.</p>
                <h3>4. Online media</h3>
                <p>Digital and online items are for your personal study only. Do not copy, share or redistribute them.</p>
                <h3>5. Your profile and privacy</h3>
                <p>Your username, bio and profile colors may be seen by other students. You can choose whether other students may see the books you are currently borrowing. The library collects only the information it needs to run the service, and uses it for that purpose only, in line with applicable data privacy laws.</p>
                <h3>6. Behavior</h3>
                <p>Be respectful to others in your lists, friend requests and profile. Do not try to break into other accounts, disrupt the system, or use it for anything unlawful or unrelated to school use.</p>
                <h3>7. Suspension</h3>
                <p>The library staff may suspend or restore an account if these terms are broken, if items are overdue for a long time, or to protect the service and its users.</p>
                <h3>8. Changes</h3>
                <p>These terms may be updated from time to time. Continuing to use the library after a change means you accept the updated terms.</p>
                <h3>9. Acceptance</h3>
                <p>By creating an account you confirm that you have read and agree to these terms.</p>
              </div>
              <p class="tos-hint" id="tos-hint" aria-live="polite">Scroll to the bottom of the terms to continue.</p>
              <label class="choice tos-accept"><input type="checkbox" id="tos-accept" disabled> <span>I have read and agree to the Terms of Service</span></label>
            </div>
              <button class="btn btn--block btn--round" type="submit" id="register-submit" disabled>Create Student Account</button>
            </form>
            <p class="link-row">Have an account? <a href="login.php">Log in</a></p>
          </div>
          <div id="success-view" class="form-stack" hidden>
            <h2 class="card-title">User account created successfully</h2>
            <p class="sub">You can now browse the library</p>
            <button class="btn btn--block btn--round" type="button" id="register-another">Register another user</button>
            <a class="btn btn--block btn--round" href="login.php">Log In</a>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
  <script>
    // Terms of Service: scroll to the end -> tick the box -> register button unlocks
    (function () {
      var box = document.getElementById('tos-box');
      var hint = document.getElementById('tos-hint');
      var accept = document.getElementById('tos-accept');
      var submit = document.getElementById('register-submit');
      var form = document.getElementById('register-form');
      var reachedEnd = false;

      function atBottom() { return box.scrollTop + box.clientHeight >= box.scrollHeight - 4; }

      function check() {
        if (!reachedEnd && atBottom()) {
          reachedEnd = true;
          accept.disabled = false;
          hint.textContent = 'Thank you. You can now accept the terms.';
          hint.classList.add('is-done');
        }
      }

      function reset() {
        reachedEnd = false;
        box.scrollTop = 0;
        accept.checked = false;
        accept.disabled = true;
        submit.disabled = true;
        hint.textContent = 'Scroll to the bottom of the terms to continue.';
        hint.classList.remove('is-done');
      }

      box.addEventListener('scroll', check);
      accept.addEventListener('change', function () { submit.disabled = !accept.checked; });

      // Safety net: runs before any other submit handler on the page
      document.addEventListener('submit', function (e) {
        if (e.target === form && !(reachedEnd && accept.checked)) {
          e.preventDefault();
          e.stopImmediatePropagation();
          hint.textContent = 'Please scroll through the terms and accept them to register.';
          hint.classList.remove('is-done');
        }
      }, true);

      var another = document.getElementById('register-another');
      if (another) another.addEventListener('click', reset);
      check();
    })();
  </script>
</body>
</html>
