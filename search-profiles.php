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
  <title>Search Profiles · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="search-profiles">
  <div class="page page--narrow-gap">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Search Profiles</h1>
          <span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="library.php">← Back to library</a>
        <a class="nav__link" href="my-lists.php">My Lists</a>
        <a class="nav__link" href="profile.php">Profile</a>
        <a class="nav__link" href="logout.php">Log out</a>
      </nav>
    </header>
    <form class="plain-search" id="profile-search" role="search" method="get" action="search-profiles.php" data-server-search>
      <input class="input" name="q" type="search" value="<?= h($q) ?>" placeholder="Search users by student number, username, or email..." aria-label="Search users">
      <button class="btn" type="submit">Search</button>
    </form>
    <div class="user-grid" id="user-grid">
<?php if (empty($users)): ?>
      <p class="sub">No users match your search.</p>
<?php endif; ?>
<?php foreach ($users as $user): ?>
<?= renderUserCard($user, $borrowed[(string) $user['student_id']] ?? [], true) ?>
<?php endforeach; ?>
    </div>
  </div>

  <script src="js/server-search.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
