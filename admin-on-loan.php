<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>On loan · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="admin-on-loan">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library Management — Admin #A-001</h1>
          <span class="role-badge">admin</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="register-student.php">Register New User</a>
        <a class="nav__link" href="suspend-user.php">Suspend / Unsuspend User</a>
        <a class="nav__link" href="admin-profile.php">Profile</a>
        <a class="nav__link" href="admin-login.php">Log out</a>
      </nav>
    </header>
    <nav class="tabs" aria-label="Admin sections"><a class="tab" href="admin-library.php">Catalog</a><a class="tab" href="admin-requests.php">Pending requests</a><a class="tab is-active" href="admin-on-loan.php" aria-current="page">On loan</a></nav>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <div class="request-grid" id="request-grid">
      <article class="request-card" data-title="Cosmos">
        <h3 class="request-card__title">Cosmos</h3>
        <p class="request-card__line">Borrower: juan.delacruz (#2023-00456)</p>
        <p class="request-card__date">Due October 22, 2026</p>
        <button class="btn btn--block" type="button" data-action="return">Confirm Returned</button>
      </article>
      <article class="request-card" data-title="El Filibusterismo">
        <h3 class="request-card__title">El Filibusterismo</h3>
        <p class="request-card__line">Borrower: ana.reyes (#2023-00210)</p>
        <p class="request-card__date">Due October 23, 2026</p>
        <button class="btn btn--block" type="button" data-action="return">Confirm Returned</button>
      </article>
      <article class="request-card" data-title="Catan">
        <h3 class="request-card__title">Catan</h3>
        <p class="request-card__line">Borrower: miguel.cruz (#2023-00388)</p>
        <p class="request-card__date">Due October 25, 2026</p>
        <button class="btn btn--block" type="button" data-action="return">Confirm Returned</button>
      </article>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
