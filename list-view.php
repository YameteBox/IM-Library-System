<?php
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';
ensureCoverColumn($pdo);

// Cover images for the books on this page, looked up by title
// TODO (backend): map book title => cover_image for the books in this list.
$coversByTitle = [];

/** The .book-card__cover for a book: its cover image, or the paper placeholder. */
function listCover(string $title, array $covers): string
{
    $cover = $covers[$title] ?? null;
    return '<div class="book-card__cover' . ($cover ? ' has-cover' : '') . '" role="img" aria-label="'
        . h(($cover ? 'Cover of ' : 'Cover placeholder for ') . $title) . '">'
        . coverImgHtml($cover) . '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>List View · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="list-view">
  <div class="page page--narrow-gap">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">List View — Summer reading</h1>
          
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="my-lists.php">← My Lists</a>
        <a class="nav__link" href="library.php">Library</a>
        <a class="nav__link" href="profile.php">Profile</a>
        <a class="nav__link" href="dashboard.php">Dashboard</a>
        <a class="nav__link" href="logout.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <h2 class="section-title">Books in this list (<span id="list-count">3</span>)</h2>
    <div class="book-grid" id="list-books">
        <article class="book-card" data-title="Noli Me Tángere" data-copies="3" data-type="Book">
          <?= listCover('Noli Me Tángere', $coversByTitle) ?>
          <div class="book-card__body">
            <div class="book-card__top"><span class="pill pill--blue">Book</span><span class="pill pill--ok">copies available</span></div>
            <h3 class="book-card__title">Book Title</h3>
            <span class="book-card__author">Author</span>
            <span class="book-card__meta">Publication Date  ·  ISBN  ·  Shelf Location</span>
            <div class="chips"><span class="chip chip--primary">Classification</span><span class="chip">tag</span></div>
          </div>
          <div class="book-card__actions">
            <button class="btn btn--block" type="button" data-action="borrow">Request to borrow</button>
            <button class="btn btn--block btn--danger" type="button" data-action="remove-from-list">Remove from list</button>
          </div>
        </article>
    </div>
    <section class="panel" style="display:flex;flex-direction:column;gap:12px;align-items:flex-start">
      <p class="sub" style="font-size:13px">Deleting a list removes it and its entries permanently.</p>
      <a class="btn btn--round btn--danger" href="my-lists.php">Delete this list</a>
    </section>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
