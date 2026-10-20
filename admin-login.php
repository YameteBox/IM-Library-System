<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin log in · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="admin-login">
  <div class="auth-shell">
    <div class="auth-stage">
      <main class="auth-body">
        <section class="auth-card auth-card--round" aria-labelledby="t">
          <img class="auth-logo" src="assets/logo.webp" alt="Manila Science High School seal" width="84" height="84">
          <h2 class="card-title" id="t">Welcome back</h2>
          <p class="sub">Log in with your admin ID or username.</p>
          <div class="toast-region" data-toast-region aria-live="polite"></div>
          <form class="form-stack" id="login-form" novalidate>
            <div class="field">
          <label for="f-admin">Admin ID</label>
          <input class="input" id="f-admin" name="admin" type="text" value="A-001" placeholder="" autocomplete="username">
        </div>
            <div class="field">
          <label for="f-password">Password</label>
          <input class="input" id="f-password" name="password" type="password" value="password" placeholder="" autocomplete="current-password">
        </div>
            <button class="btn btn--block btn--round" type="submit">Log In</button>
          </form>
          <p class="link-row">Are you a user? <a href="login.php">Log in</a></p>
        </section>
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
