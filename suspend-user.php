<?php
require 'db.php';
require_once 'search_helpers.php';

$q        = trim((string) ($_GET['q'] ?? ''));
$users    = searchUsers($pdo, $q);
$borrowed = currentlyBorrowedByUsers($pdo, array_column($users, 'student_id'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Suspend / Unsuspend user · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="suspend-user">
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
        <a class="nav__link" href="admin-library.php">← Back to admin library</a>
        <a class="nav__link" href="admin-login.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <main class="auth-body" style="display:block">
      <section class="panel panel--lg wide-panel" id="manage-view" aria-labelledby="t">
        <div class="split">
          <div class="form-stack">
            <h2 class="card-title" id="t">Suspend/Unsuspend User</h2>
            <p class="sub">Suspend or restore a student's account by student number.</p>
            <form class="form-stack" id="suspend-form" onsubmit="return false" novalidate>
              <div class="field">
          <label for="f-student">Student Number</label>
          <input class="input" id="f-student" name="student" type="text" placeholder="202300789" >
        </div>
              <div class="field">
          <label for="f-admin">Admin ID</label>
          <input class="input" id="f-admin" name="admin" type="text" placeholder="A-001" >
        </div>
              <div class="field">
          <label for="f-password">Admin Password</label>
          <input class="input" id="f-password" name="password" type="password">
        </div>
              <div class="field">
          <label for="f-confirm">Confirm password</label>
          <input class="input" id="f-confirm" name="confirm" type="password">
        </div>
              <button class="btn btn--block btn--danger btn--round" type="button" id="do-suspend">Suspend User Account</button>
              <button class="btn btn--block btn--success btn--round" type="button" id="do-unsuspend">Unsuspend User Account</button>
            </form>
          </div>
          <aside class="split__side" aria-label="Find a user">
            <form class="split__search plain-search" id="profile-search" role="search" method="get" action="suspend-user.php" data-server-search>
              <input class="input" name="q" type="search" value="<?= h($q) ?>" placeholder="Search by student number, username, or email..." aria-label="Search users">
              <button class="btn" type="submit">Search</button>
            </form>
            <div class="split__results">
<?php if (empty($users)): ?>
      <p class="sub">No users match your search.</p>
<?php endif; ?>
<?php foreach ($users as $user): ?>
<?= renderUserCard($user, $borrowed[(string) $user['student_id']] ?? [], false) ?>
<?php endforeach; ?>
            </div>
          </aside>
        </div>
      </section>

      <section class="auth-card auth-card--round" id="result-view" aria-labelledby="rt" hidden style="margin-inline:auto">
        <h2 class="card-title" id="rt">Suspend / Unsuspend User</h2>
        <p class="sub">Suspend or restore a student's account by student number.</p>
        <div class="toast toast--success" id="result-alert" role="status">
          <span id="result-text"></span>
          <button class="toast__close" type="button" id="result-close" aria-label="Dismiss message">✕</button>
        </div>
        <p class="sub center-text">Would you like to manage another user?</p>
        <button class="btn btn--block btn--round" type="button" id="manage-another">Manage another user</button>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
