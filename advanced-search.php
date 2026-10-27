<?php
session_start();
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';

// Must be logged in (login.php sets these session values), same as library.php
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId        = (int) $_SESSION['user_id'];
$studentNumber = $_SESSION['student_number'] ?? '';
$username      = $_SESSION['username'] ?? $studentNumber;

$isBanned  = userIsBanned($pdo, $userId); // banned students can browse but not request to borrow

$search    = advancedSearchParams($_GET);
$submitted = !empty($_GET);
$active    = advancedFiltersActive($search);
$books     = $active ? searchCatalogAdvanced($pdo, $search) : [];

// Books this student already has a pending request for
$stmt = $pdo->prepare(
    "SELECT book_id FROM borrowed_books
      WHERE user_id = :uid AND status = 'pending' AND returned_at IS NULL"
);
$stmt->execute([':uid' => $userId]);
$pendingBookIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// This student's lists for the "Select list" dropdown
$stmt = $pdo->prepare('SELECT id, name FROM lists WHERE user_id = :uid ORDER BY name');
$stmt->execute([':uid' => $userId]);
$userLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
          <h1 class="brand__title">Library — Student #<?= h($studentNumber) ?></h1>
          <span class="brand__user"><?= h($username) ?></span><span class="role-badge">student</span>
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
    <div class="toast-region" data-toast-region aria-live="polite"></div>
<?php if ($isBanned): ?>
    <?= bannedNoticeHtml() ?>
<?php endif; ?>
    <a class="back-link" href="library.php">← Back to library</a>
    <h2 class="section-title">Advanced search</h2>
    <form class="panel panel--lg form-stack" id="advanced-form" method="get" action="advanced-search.php">
      <div class="form-grid form-grid--3">
        <div class="field">
          <label for="f-tags">Tags</label>
          <input class="input" id="f-tags" name="tags" type="text" value="<?= h($search['tags']) ?>" placeholder="Input tags or keywords, separated by commas" maxlength="200">
        </div>
        <div class="field">
          <label for="f-title">Title</label>
          <input class="input" id="f-title" name="title" type="text" value="<?= h($search['title']) ?>" placeholder="e.g. Cosmos" maxlength="255">
        </div>
        <div class="field">
          <label for="f-author">Author</label>
          <input class="input" id="f-author" name="author" type="text" value="<?= h($search['author']) ?>" placeholder="e.g. Carl Sagan" maxlength="255">
        </div>
        <div class="field">
          <label for="f-isbn">ISBN</label>
          <input class="input" id="f-isbn" name="isbn" type="text" value="<?= h($search['isbn']) ?>" placeholder="e.g. 978-0-345-53943-4" maxlength="20">
        </div>
        <div class="field">
          <label for="f-type">Media type</label>
          <select class="select" id="f-type" name="type"><?= mediaTypeFilterOptions($search['type']) ?></select>
        </div>
        <div class="field">
          <label for="f-classification">Classification</label>
          <select class="select" id="f-classification" name="classification">
            <option value="">All Classifications</option>
            <?php foreach (CATALOG_CLASSIFICATIONS as $category): ?>
              <option value="<?= h($category) ?>"<?= $search['classification'] === $category ? ' selected' : '' ?>><?= h($category) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="f-from">Published from</label>
          <input class="input" id="f-from" name="from" type="number" value="<?= h($search['from']) ?>" placeholder="Year, e.g. 1980" min="1" max="9999" inputmode="numeric">
        </div>
        <div class="field">
          <label for="f-to">Published to</label>
          <input class="input" id="f-to" name="to" type="number" value="<?= h($search['to']) ?>" placeholder="Year, e.g. 2020" min="1" max="9999" inputmode="numeric">
        </div>
        <div class="field">
          <label for="f-availability">Availability</label>
          <select class="select" id="f-availability" name="availability"><?= simpleOptions(ADVANCED_AVAILABILITY, $search['availability']) ?></select>
        </div>
      </div>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="f-shelf">Shelf location</label>
          <input class="input" id="f-shelf" name="shelf" type="text" value="<?= h($search['shelf']) ?>" placeholder="e.g. C-04" maxlength="20">
        </div>
        <div class="field">
          <label for="f-sort">Sort by</label>
          <select class="select" id="f-sort" name="sort"><?= simpleOptions(ADVANCED_SORTS, $search['sort']) ?></select>
        </div>
      </div>
      <div class="actions actions--end">
        <button class="btn btn--round" type="submit">Search</button>
        <a class="btn btn--round btn--outline" href="advanced-search.php">Reset</a>
      </div>
    </form>

<?php if ($submitted): ?>
    <section aria-labelledby="res-h" style="display:flex;flex-direction:column;gap:12px">
      <h2 class="section-title" id="res-h">Results<?= $active ? ' (' . count($books) . ')' : '' ?></h2>
      <div class="book-grid">
<?php if (!$active): ?>
        <p class="sub">Fill in at least one search field, then press Search.</p>
<?php elseif (empty($books)): ?>
        <p class="sub">No books match your search.</p>
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
            if (!empty($book['accession_number'])) { $meta[] = 'Accession ' . $book['accession_number']; }
            if (!empty($book['isbn']))         { $meta[] = 'ISBN ' . $book['isbn']; }
            if (!empty($book['shelf_number'])) { $meta[] = 'Shelf ' . $book['shelf_number']; }
?>
        <article class="book-card" data-book-id="<?= $bookId ?>" data-title="<?= h($book['title']) ?>" data-copies="<?= $copiesLeft ?>" data-type="<?= h($typeLabel) ?>">
          <div class="book-card__cover<?= !empty($book['cover_image']) ? ' has-cover' : '' ?>" role="img" aria-label="<?= empty($book['cover_image']) ? 'Cover placeholder for ' : 'Cover of ' ?><?= h($book['title']) ?>"><?= coverImgHtml($book['cover_image'] ?? null) ?></div>
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
            <?= borrowButtonHtml($isBanned, $isPending) ?>
            <div class="add-row">
              <select class="select" aria-label="Select list"><option value="">Select list</option><?php foreach ($userLists as $list): ?><option value="<?= (int) $list['id'] ?>"><?= h($list['name']) ?></option><?php endforeach; ?></select>
              <button class="btn btn--outline" type="button" data-action="add-to-list">Add to list</button>
            </div>
          </div>
        </article>
<?php endforeach; ?>
      </div>
    </section>
<?php endif; ?>

    <footer class="site-footer">
      <div class="site-footer__links"><a href="#">About Us</a><span aria-hidden="true">·</span><a href="#">Mission and Vision</a><span aria-hidden="true">·</span><a href="#">Contact Us</a></div>
      <div>© 2026 Manila Science High School Online Library</div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script>
    // The search form must really submit to PHP: keep app.js (prototype) from intercepting it.
    (function () {
      function onForm(e) { return e.target && e.target.closest && e.target.closest('#advanced-form'); }
      document.addEventListener('submit', function (e) { if (onForm(e)) e.stopImmediatePropagation(); }, true);
      document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('#advanced-form button[type="submit"]') : null;
        if (btn) e.stopImmediatePropagation();
      }, true);
    })();
  </script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
