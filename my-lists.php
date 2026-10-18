<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Lists · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="my-lists">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">My Lists — Student #2023-00123</h1>
          <span class="brand__user">maria.santos</span><span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="library.php">← Back to library</a>
        <a class="nav__link" href="profile.php">Profile</a>
        <a class="nav__link" href="login.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <form class="panel" id="create-form" novalidate>
      <div class="create-row">
        <input class="input" name="name" placeholder="New list name" aria-label="New list name">
        <button class="btn" type="submit">Create list</button>
      </div>
    </form>
    <section style="display:flex;flex-direction:column;gap:16px" aria-labelledby="yl">
      <h2 class="section-title" id="yl">Your lists</h2>
      <div class="list-grid" id="list-grid">
        <article class="list-card">
          <div class="list-card__head"><h3 class="list-card__name">Summer reading</h3><span class="pill pill--grey">3 items</span></div>
          <a class="btn btn--block" href="list-view.php">View / edit list</a>
          <div class="rename-row"><input class="input" value="Summer reading" aria-label="List name"><button class="btn" type="button" data-action="rename-list">Rename</button></div>
          <button class="btn btn--block btn--danger" type="button" data-action="delete-list">Delete list</button>
        </article>
        <article class="list-card">
          <div class="list-card__head"><h3 class="list-card__name">Favorites</h3><span class="pill pill--grey">2 items</span></div>
          <a class="btn btn--block" href="list-view.php">View / edit list</a>
          <div class="rename-row"><input class="input" value="Favorites" aria-label="List name"><button class="btn" type="button" data-action="rename-list">Rename</button></div>
          <button class="btn btn--block btn--danger" type="button" data-action="delete-list">Delete list</button>
        </article>
      </div>
    </section>
    <template id="list-template">
        <article class="list-card">
          <div class="list-card__head"><h3 class="list-card__name"></h3><span class="pill pill--grey">0 items</span></div>
          <a class="btn btn--block" href="list-view.php">View / edit list</a>
          <div class="rename-row"><input class="input" value="" aria-label="List name"><button class="btn" type="button" data-action="rename-list">Rename</button></div>
          <button class="btn btn--block btn--danger" type="button" data-action="delete-list">Delete list</button>
        </article>
    </template>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
