<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile (Admin) · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="admin-profile">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">My Profile — Admin #A-001</h1>
          <span class="brand__user">librarian</span><span class="role-badge">admin</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="admin-library.php">← Back to admin library</a>
        <a class="nav__link" href="admin-logout.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <section class="panel">
      <div class="profile-head">
        <div class="avatar avatar--navy" aria-hidden="true">LB</div>
        <div>
          <h2 class="profile-name">librarian</h2>
          <dl class="kv"><dt>Admin ID</dt><dd>A-001</dd></dl>
        </div>
      </div>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="cp">
      <h2 class="panel-title" id="cp">Customize profile</h2>
      <div class="toggle-row"><button class="switch" id="dark-switch" type="button" role="switch" aria-checked="false" aria-label="Dark mode"></button><span>Dark mode</span></div>
      <div class="field"><label for="bio">Bio</label><input class="input" id="bio" value="Head librarian."></div>
        <div class="choice-row">
          <label class="choice"><input type="radio" name="color-mode" value="solid" checked> Solid color</label>
          <label class="choice"><input type="radio" name="color-mode" value="gradient"> Gradient</label>
        </div>
        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <input type="color" id="color-1" name="color1" value="#1e3a8a" aria-label="Profile colour" style="width:52px;height:38px;padding:2px;cursor:pointer">
          <input type="color" id="color-2" name="color2" value="#3b82f6" aria-label="Gradient second colour" style="width:52px;height:38px;padding:2px;cursor:pointer" hidden>
        </div>
        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <label for="color-text">Initials text color</label>
          <input type="color" id="color-text" name="color_text" value="#ffffff" style="width:52px;height:38px;padding:2px;cursor:pointer">
        </div>
      <div><button class="btn btn--round" type="button" data-save>Save profile</button></div>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="as">
      <h2 class="panel-title" id="as">Account settings</h2>
      <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username" type="text" value="librarian" placeholder="" >
        </div>
      <div class="field">
          <label for="f-current">Current Password</label>
          <input class="input" id="f-current" name="current" type="password" value="password" placeholder="" >
        </div>
      <div class="field">
          <label for="f-new">New Password</label>
          <input class="input" id="f-new" name="new" type="password" value="password" placeholder="" >
        </div>
      <div class="field">
          <label for="f-confirm">Confirm New Password</label>
          <input class="input" id="f-confirm" name="confirm" type="password" value="password" placeholder="" >
        </div>
      <div><button class="btn btn--round" type="button" data-save>Save Account Settings</button></div>
    </section>
  </div>

  <script>
    // Profile colour: one picker for solid, two for gradient
    (function () {
      var c1 = document.getElementById('color-1'), c2 = document.getElementById('color-2');
      var avatar = document.querySelector('.profile-head .avatar');
      var radios = document.querySelectorAll('input[name="color-mode"]');
      function isGradient() { return document.querySelector('input[name="color-mode"]:checked').value === 'gradient'; }
      function toggle() { c2.hidden = !isGradient(); }
      function preview() {
        toggle();
        avatar.style.background = isGradient()
          ? 'linear-gradient(135deg,' + c1.value + ',' + c2.value + ')'
          : c1.value;
      }
      radios.forEach(function (r) { r.addEventListener('change', preview); });
      c1.addEventListener('input', preview);
      c2.addEventListener('input', preview);
      var ct = document.getElementById('color-text');
      ct.addEventListener('input', function () { avatar.style.color = ct.value; });
      toggle();
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
