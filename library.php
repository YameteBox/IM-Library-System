<?php
session_start();
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';

$search = catalogSearchParams($_GET);
$books  = searchCatalog($pdo, $search);

// Books this student already has a pending request for
$studentId = $_SESSION['student_id'] ?? '2023-00123';
$stmt = $pdo->prepare("SELECT book_id FROM loans WHERE CAST(user_id AS TEXT) = ? AND status = 'pending' AND returned_at IS NULL");
$stmt->execute([(string) $studentId]);
$pendingBookIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Library · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="library">
  <div class="page">
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
        <a class="nav__link" href="index.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <main style="display:contents">
      <section class="hero" aria-label="Search the library">
        <h2 class="hero__eyebrow">MANILA SCIENCE HIGH SCHOOL ONLINE LIBRARY</h2>
        <p class="hero__lede">Search books, board games, audiovisual and digital media, then borrow in a few clicks.</p>
        <form class="search-bar" id="search-form" role="search" method="get" action="library.php" data-server-search>
          <input class="input" name="q" type="search" value="<?= h($search['q']) ?>" placeholder="Search..." aria-label="Search">
          <select class="select" name="type" aria-label="Media type"><?= mediaTypeFilterOptions($search['type']) ?></select>
          <select class="select" name="field" aria-label="Search field"><?= searchFieldOptions($search['field']) ?></select>
          <button class="btn" type="submit">Search</button>
        </form>
        <p class="hero__links"><a href="advanced-search.php">Advanced search</a></p>
      </section>

      <section aria-labelledby="new-h" style="display:flex;flex-direction:column;gap:12px">
        <div class="carousel-head">
          <h2 class="section-title" id="new-h">New books</h2>
          <div class="carousel-arrows">
            <button class="icon-btn" type="button" id="prev-books" aria-label="Previous">‹</button>
            <button class="icon-btn" type="button" id="next-books" aria-label="Next">›</button>
          </div>
        </div>
        <div class="carousel" id="new-books">
          <article class="mini-card"><div class="mini-card__cover" role="img" aria-label="Cover placeholder"></div><h3 class="mini-card__title">Noli Me Tángere</h3><p class="mini-card__author">José Rizal</p></article>
          <article class="mini-card"><div class="mini-card__cover" role="img" aria-label="Cover placeholder"></div><h3 class="mini-card__title">Noli Me Tángere</h3><p class="mini-card__author">José Rizal</p></article>
          <article class="mini-card"><div class="mini-card__cover" role="img" aria-label="Cover placeholder"></div><h3 class="mini-card__title">Noli Me Tángere</h3><p class="mini-card__author">José Rizal</p></article>
          <article class="mini-card"><div class="mini-card__cover" role="img" aria-label="Cover placeholder"></div><h3 class="mini-card__title">Noli Me Tángere</h3><p class="mini-card__author">José Rizal</p></article>
          <article class="mini-card"><div class="mini-card__cover" role="img" aria-label="Cover placeholder"></div><h3 class="mini-card__title">Noli Me Tángere</h3><p class="mini-card__author">José Rizal</p></article>
          <article class="mini-card"><div class="mini-card__cover" role="img" aria-label="Cover placeholder"></div><h3 class="mini-card__title">Noli Me Tángere</h3><p class="mini-card__author">José Rizal</p></article>
        </div>
      </section>

      <section aria-labelledby="cat-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="section-title" id="cat-h">Browse the catalog</h2>
        <div class="book-grid">
        <?php if (empty($books)): ?>
          <p class="sub"><?= catalogFiltersActive($search) ? 'No books match your search.' : 'No books in the catalog yet.' ?></p>
        <?php endif; ?>
        <?php foreach ($books as $book): ?>
          <?php
            $bookId     = (int) $book['id'];
            $copiesLeft = (int) $book['copies_available'];
            $typeLabel  = mediaTypeLabel($book['media_type']);
            $pillClass  = mediaTypeBadgeClass($book['media_type']) === 'success' ? 'pill--green' : 'pill--blue';
            $keywords   = array_filter(array_map('trim', explode(',', (string) ($book['key_word'] ?? ''))));
            $isPending  = in_array($bookId, $pendingBookIds, true);
            $meta       = [formatPublishedDate((string) $book['published_date'])];
            if (!empty($book['isbn']))         { $meta[] = 'ISBN ' . $book['isbn']; }
            if (!empty($book['shelf_number'])) { $meta[] = 'Shelf ' . $book['shelf_number']; }
          ?>
        <article class="book-card" data-book-id="<?= $bookId ?>" data-title="<?= h($book['title']) ?>" data-copies="<?= $copiesLeft ?>" data-type="<?= h($typeLabel) ?>">
          <div class="book-card__cover" role="img" aria-label="<?= empty($book['cover_image']) ? 'Cover placeholder for ' : 'Cover of ' ?><?= h($book['title']) ?>"<?= coverStyleAttr($book['cover_image'] ?? null) ?>></div>
          <div class="book-card__body">
            <div class="book-card__top">
              <span class="pill <?= $pillClass ?>"><?= h($typeLabel) ?></span>
              <?php if ($copiesLeft > 0): ?>
                <span class="pill pill--ok"><?= $copiesLeft ?> cop<?= $copiesLeft === 1 ? 'y' : 'ies' ?> available</span>
              <?php else: ?>
                <span class="pill pill--err">No copies available</span>
              <?php endif; ?>
            </div>
            <h3 class="book-card__title"><?= h($book['title']) ?></h3>
            <span class="book-card__author"><?= h($book['authors']) ?></span>
            <span class="book-card__meta"><?= h(implode('  ·  ', $meta)) ?></span>
            <div class="chips">
              <?php if (!empty($book['classification'])): ?><span class="chip chip--primary"><?= h($book['classification']) ?></span><?php endif; ?>
              <?php foreach ($keywords as $kw): ?><span class="chip"><?= h($kw) ?></span><?php endforeach; ?>
            </div>
          </div>
          <div class="book-card__actions">
            <button class="btn btn--block<?= $isPending ? ' is-disabled' : '' ?>" type="button" data-action="borrow"><?= $isPending ? 'Borrow request pending' : 'Request to borrow' ?></button>
            <div class="add-row">
              <select class="select" aria-label="Select list"><option value="">Select list</option><option>Summer reading</option><option>Favorites</option></select>
              <button class="btn btn--outline" type="button" data-action="add-to-list">Add to list</button>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
        </div>
      </section>
    </main>
    <footer class="site-footer">
      <div class="site-footer__links"><a href="#">About Us</a><span aria-hidden="true">·</span><a href="#">Mission and Vision</a><span aria-hidden="true">·</span><a href="#">Contact Us</a></div>
      <div>© 2026 Manila Science High School Online Library</div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
