<?php
require 'db.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Advanced search · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="advanced-search">
  <div class="page page--narrow-gap">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library — Student #2023-00123</h1>
          <span class="brand__user">maria.santos</span><span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="search-profiles.php">Search Profiles</a>
        <a class="nav__link" href="my-lists.php">My Lists</a>
        <a class="nav__link" href="profile.php">Profile</a>
        <a class="nav__link" href="dashboard.php">Dashboard</a>
        <a class="nav__link" href="logout.php">Log out</a>
      </nav>
    </header>
    <a class="back-link" href="library.php">← Back to library</a>
    <h2 class="section-title">Advanced search</h2>
    <form class="panel panel--lg form-stack" id="advanced-form">
      <div class="form-grid form-grid--3">
        <div class="field">
          <label for="f-tags">Tags</label>
          <input class="input" id="f-tags" name="tags" type="text" value="" placeholder="Input tags or keywords" >
        </div>
        <div class="field">
          <label for="f-title">Title</label>
          <input class="input" id="f-title" name="title" type="text" value="" placeholder="e.g. Cosmos" >
        </div>
        <div class="field">
          <label for="f-author">Author</label>
          <input class="input" id="f-author" name="author" type="text" value="" placeholder="e.g. Carl Sagan" >
        </div>
        <div class="field">
          <label for="f-isbn">ISBN</label>
          <input class="input" id="f-isbn" name="isbn" type="text" value="" placeholder="e.g. 978-0-345-53943-4" >
        </div>
        <div class="field">
          <label for="f-type">Media type</label>
          <select class="select" id="f-type" name="type"><option value="">All Media Types</option><option value="Book">Book</option><option value="Board Game">Board Game</option><option value="Audiovisual">Audiovisual</option><option value="Digital Media">Digital Media</option></select>
        </div>
        <div class="field">
          <label for="f-classification">Classification</label>
          <select class="select" id="f-classification" name="classification"><option value="">All Classifications</option><option>General Works, Computer Science, and Information</option><option>Philosophy and Psychology</option><option>Religion</option><option>Social Sciences</option><option>Language</option><option>Science</option><option>Technology</option><option>Arts and Recreation</option><option>Literature</option><option>History and Geography</option></select>
        </div>
        <div class="field">
          <label for="f-from">Published from</label>
          <input class="input" id="f-from" name="from" type="number" value="" placeholder="Year, e.g. 1980" min="0" inputmode="numeric">
        </div>
        <div class="field">
          <label for="f-to">Published to</label>
          <input class="input" id="f-to" name="to" type="number" value="" placeholder="Year, e.g. 2020" min="0" inputmode="numeric">
        </div>
        <div class="field">
          <label for="f-availability">Availability</label>
          <select class="select" id="f-availability" name="availability"><option value="">Any availability</option><option value="Available now">Available now</option><option value="Unavailable">Unavailable</option></select>
        </div>
      </div>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="f-shelf">Shelf location</label>
          <input class="input" id="f-shelf" name="shelf" type="text" value="" placeholder="e.g. C-04" >
        </div>
        <div class="field">
          <label for="f-sort">Sort by</label>
          <select class="select" id="f-sort" name="sort"><option value="">Relevance</option><option value="Title">Title</option><option value="Author">Author</option><option value="Newest first">Newest first</option></select>
        </div>
      </div>
      <div class="actions actions--end">
        <button class="btn btn--round" type="submit">Search</button>
        <button class="btn btn--round btn--outline" type="reset">Reset</button>
      </div>
    </form>
    <footer class="site-footer">
      <div class="site-footer__links"><a href="#">About Us</a><span aria-hidden="true">·</span><a href="#">Mission and Vision</a><span aria-hidden="true">·</span><a href="#">Contact Us</a></div>
      <div>© 2026 Manila Science High School Online Library</div>
    </footer>
  </div>

  <script src="js/app.js?v=2"></script>
</body>
</html>
