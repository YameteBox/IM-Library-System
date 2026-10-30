<?php
session_start();
require 'db.php';

// Must be logged in as an admin; admin-logout.php destroys this session
if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}
$adminName   = (string) ($_SESSION['admin_name'] ?? 'librarian');
$adminNumber = (string) ($_SESSION['admin_number'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin welcome · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="admin-welcome">
  <div class="auth-shell">
    <div class="auth-stage">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library Management — Admin <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></h1>
          
        </div>
      </div>
    </header>
      <main class="auth-body">
        <section class="auth-card" aria-labelledby="t">
          <h2 class="card-title" id="t">You're logged in, <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>!</h2>
          <p class="sub">Welcome back.</p>
          <div><span class="pill pill--blue">admin</span></div>
          <div class="btn-row">
            <a class="btn btn--round" href="admin-library.php">Browse the library</a>
            <a class="btn btn--round btn--danger" href="admin-logout.php">Log out</a>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>