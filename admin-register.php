<?php
session_start();

// Admins only (same check as admin-library.php)
if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require 'db.php';
require_once 'account_helpers.php';
$adminAcct = adminAccount($pdo, (int) $_SESSION['admin_id']); // latest username from the database
if (!$adminAcct) {
    header('Location: admin-logout.php'); // session points at an admin that no longer exists
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register a new user · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="register-student">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library Management — Admin <?= htmlspecialchars($adminAcct['username'], ENT_QUOTES, 'UTF-8') ?></h1>
          <span class="role-badge">admin</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="admin-library.php">← Back to admin library</a>
        <a class="nav__link" href="admin-logout.php">Log out</a>
      </nav>
    </header>
    <main class="auth-body">
      <section class="auth-card auth-card--round" aria-labelledby="t">
        <img class="auth-logo" src="assets/logo.webp" alt="Manila Science High School seal" width="84" height="84">
        <div id="register-view" class="form-stack">
          <h2 class="card-title" id="t">Register a New User</h2>
          <p class="sub">Create a user account on their behalf.</p>
          <div class="toast-region" data-toast-region aria-live="polite"></div>
          <form class="form-stack" id="register-form" novalidate>
            <div class="field">
          <label for="f-student">Student Number</label>
          <input class="input" id="f-student" name="student" type="text" placeholder="2024-00100" >
        </div>
            <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username" type="text" placeholder="Student Name" >
        </div>
            <div class="field">
          <label for="f-email">Email</label>
          <input class="input" id="f-email" name="email" type="email">
        </div>
            <div class="field">
          <label for="f-password">Temporary Password</label>
          <input class="input" id="f-password" name="password" type="password">
        </div>
            <div class="field">
          <label for="f-confirm">Confirm password</label>
          <input class="input" id="f-confirm" name="confirm" type="password">
        </div>
            <button class="btn btn--block btn--round" type="submit">Create Student Account</button>
          </form>
        </div>
        <div id="success-view" class="form-stack" hidden>
          <h2 class="card-title">User account created successfully</h2>
          <p class="sub">Create a user account on their behalf.</p>
          <button class="btn btn--block btn--round" type="button" id="register-another">Register another user</button>
        </div>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>