<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="login">
  <div class="auth-shell">
    <div class="auth-stage">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Manila Science High School Online Library</h1>
          
        </div>
      </div>
    </header>
      <main class="auth-body">
        <section class="auth-card" aria-labelledby="t">
          <h2 class="card-title" id="t">Welcome back</h2>
          <p class="sub">Log in with your student number.</p>
          <div class="toast-region" data-toast-region aria-live="polite"></div>
          <form class="form-stack" id="login-form" novalidate>
            <div class="field">
          <label for="f-student">Student Number</label>
          <input class="input" id="f-student" name="student" type="text" value="2023-00123" placeholder="" autocomplete="username">
        </div>
            <div class="field">
          <label for="f-password">Password</label>
          <input class="input" id="f-password" name="password" type="password" value="password" placeholder="" autocomplete="current-password">
        </div>
            <button class="btn btn--block btn--round" type="submit">Log In</button>
          </form>
          <div class="link-stack">
            <p class="link-row">Don't have an account? <a href="register.php">Register now</a></p>
            <p class="link-row">Are you an admin? <a href="admin-login.php">Log in</a></p>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
