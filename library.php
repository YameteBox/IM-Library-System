<?php
session_start();
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';
require_once 'notifications_helpers.php';
require_once 'account_helpers.php';

// Must be logged in (login.php sets these session values)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId        = (int) $_SESSION['user_id'];
$account       = studentAccount($pdo, $userId); // latest username from the database
if (!$account) {
    header('Location: logout.php'); // session points at a user that no longer exists
    exit;
}
$studentNumber = $account['student_number'];
$username      = $account['username'];

$isBanned = userIsBanned($pdo, $userId); // banned students can browse but not request to borrow

$search = catalogSearchParams($_GET);

// Advanced search (the drop-down under the search bar) submits to this same page with adv=1
$isAdvanced = isset($_GET['adv']);
$adv        = advancedSearchParams($_GET);
$advActive  = $isAdvanced && advancedFiltersActive($adv);
$books      = $advActive ? searchCatalogAdvanced($pdo, $adv) : searchCatalog($pdo, $search);

// Books this student already has a pending request for
$stmt = $pdo->prepare(
    "SELECT book_id FROM borrowed_books
      WHERE user_id = :uid AND status = 'pending' AND returned_at IS NULL"
);
$stmt->execute([':uid' => $userId]);
$pendingBookIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// Newest books for the carousel
$newBooks = $pdo->query(
    'SELECT id, title, authors, cover_image FROM books ORDER BY added_on DESC, id DESC LIMIT 10'
)->fetchAll(PDO::FETCH_ASSOC);

// This student's lists for the "Select list" dropdown
$stmt = $pdo->prepare('SELECT id, name FROM lists WHERE user_id = :uid ORDER BY name');
$stmt->execute([':uid' => $userId]);
$userLists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Notifications for the bell, and the books this student asked to be told about
$notifications  = notificationData($pdo, $userId);
$watchedBookIds = $notifications['watched'];
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
  <link rel="stylesheet" href="css/sidebar.css">
  <style>
    /* New books: the whole card is clickable (stretched title link) and runs a search for that book */
    .mini-card { position: relative; cursor: pointer; }
    .mini-card__link { color: inherit; text-decoration: none; }
    .mini-card__link::after { content: ""; position: absolute; inset: 0; }
    .mini-card:hover .mini-card__link, .mini-card__link:focus-visible { text-decoration: underline; }
<?= notificationStyles() ?>
    /* Advanced search button: filled + animated */
    #toggle-advanced { background: #1E5EBA; color: #fff; border: 1px solid #1E5EBA; border-radius: 999px; font-weight: 600;
      transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease; }
    #toggle-advanced:hover { background: #17489a; border-color: #17489a; transform: translateY(-2px); box-shadow: 0 6px 14px rgba(30,94,186,.35); }
    #toggle-advanced:active { transform: translateY(0) scale(.97); box-shadow: none; }
    #toggle-advanced:focus-visible { outline: 3px solid rgba(30,94,186,.4); outline-offset: 2px; }
    #toggle-advanced #advanced-caret { display: inline-block; transition: transform .25s ease; }
    #toggle-advanced:hover #advanced-caret { transform: translateY(2px); }
    #advanced-form:not([hidden]) { animation: advSlide .3s ease both; }
    @keyframes advSlide { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    @media (prefers-reduced-motion: reduce) {
      #toggle-advanced, #toggle-advanced #advanced-caret { transition: none; }
      #advanced-form:not([hidden]) { animation: none; }
    }
  </style>
</head>
<body data-page="library">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library — Student #<?= h($studentNumber) ?></h1>
          <span class="brand__user"><?= h($username) ?></span><span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
<?= notificationBellHtml($notifications['items']) ?>
        <a class="nav__link" href="my-lists.php">My Lists</a>
        <a class="nav__link" href="profile.php">Profile</a>
        <a class="nav__link" href="dashboard.php">Dashboard</a>
        <a class="nav__link" href="logout.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <?php if ($isBanned): ?><?= bannedNoticeHtml() ?><?php endif; ?>
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
        <p class="hero__links"><button class="btn btn--outline btn--sm" type="button" id="toggle-advanced" aria-expanded="<?= $isAdvanced ? 'true' : 'false' ?>" aria-controls="advanced-form">Advanced search <span aria-hidden="true" id="advanced-caret"><?= $isAdvanced ? '▴' : '▾' ?></span></button></p>
      </section>

      <form class="panel panel--lg form-stack" id="advanced-form" method="get" action="library.php"<?= $isAdvanced ? '' : ' hidden' ?>>
        <input type="hidden" name="adv" value="1">
        <div class="form-grid form-grid--3">
          <div class="field">
            <label for="f-tags">Tags</label>
            <input class="input" id="f-tags" name="tags" type="text" value="<?= h($adv['tags']) ?>" placeholder="Input tags or keywords, separated by commas" maxlength="200">
          </div>
          <div class="field">
            <label for="f-title">Title</label>
            <input class="input" id="f-title" name="title" type="text" value="<?= h($adv['title']) ?>" placeholder="e.g. Cosmos" maxlength="255">
          </div>
          <div class="field">
            <label for="f-author">Author</label>
            <input class="input" id="f-author" name="author" type="text" value="<?= h($adv['author']) ?>" placeholder="e.g. Carl Sagan" maxlength="255">
          </div>
          <div class="field">
            <label for="f-isbn">ISBN</label>
            <input class="input" id="f-isbn" name="isbn" type="text" value="<?= h($adv['isbn']) ?>" placeholder="e.g. 978-0-345-53943-4" maxlength="20">
          </div>
          <div class="field">
            <label for="f-adv-type">Media type</label>
            <select class="select" id="f-adv-type" name="type"><?= mediaTypeFilterOptions($adv['type']) ?></select>
          </div>
          <div class="field">
            <label for="f-classification">Classification</label>
            <select class="select" id="f-classification" name="classification">
              <option value="">All Classifications</option>
              <?php foreach (CATALOG_CLASSIFICATIONS as $category): ?>
                <option value="<?= h($category) ?>"<?= $adv['classification'] === $category ? ' selected' : '' ?>><?= h($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="f-from">Published from</label>
            <input class="input" id="f-from" name="from" type="number" value="<?= h($adv['from']) ?>" placeholder="Year, e.g. 1980" min="1" max="9999" inputmode="numeric">
          </div>
          <div class="field">
            <label for="f-to">Published to</label>
            <input class="input" id="f-to" name="to" type="number" value="<?= h($adv['to']) ?>" placeholder="Year, e.g. 2020" min="1" max="9999" inputmode="numeric">
          </div>
          <div class="field">
            <label for="f-availability">Availability</label>
            <select class="select" id="f-availability" name="availability"><?= simpleOptions(ADVANCED_AVAILABILITY, $adv['availability']) ?></select>
          </div>
        </div>
        <div class="form-grid form-grid--2">
          <div class="field">
            <label for="f-shelf">Shelf location</label>
            <input class="input" id="f-shelf" name="shelf" type="text" value="<?= h($adv['shelf']) ?>" placeholder="e.g. C-04" maxlength="20">
          </div>
          <div class="field">
            <label for="f-sort">Sort by</label>
            <select class="select" id="f-sort" name="sort"><?= simpleOptions(ADVANCED_SORTS, $adv['sort']) ?></select>
          </div>
        </div>
        <div class="actions actions--end">
          <button class="btn btn--round" type="submit">Search</button>
          <a class="btn btn--round btn--outline" href="library.php">Reset</a>
        </div>
      </form>

      <section aria-labelledby="new-h" style="display:flex;flex-direction:column;gap:12px">
        <div class="carousel-head">
          <h2 class="section-title" id="new-h">Newly added</h2>
          <div class="carousel-arrows">
            <button class="icon-btn" type="button" id="prev-books" aria-label="Previous">‹</button>
            <button class="icon-btn" type="button" id="next-books" aria-label="Next">›</button>
          </div>
        </div>
        <div class="carousel" id="new-books" role="region" aria-roledescription="carousel" aria-label="New books" tabindex="0">
          <?php foreach ($newBooks as $newBook): ?>
          <?php $newBookSearch = 'library.php?' . http_build_query(['q' => $newBook['title'], 'field' => 'title', 'type' => 'all']); ?>
          <article class="mini-card"><div class="mini-card__cover<?= !empty($newBook['cover_image']) ? ' has-cover' : '' ?>" role="img" aria-label="<?= empty($newBook['cover_image']) ? 'Cover placeholder for ' : 'Cover of ' ?><?= h($newBook['title']) ?>"><?= coverImgHtml($newBook['cover_image'] ?? null) ?></div><h3 class="mini-card__title"><a class="mini-card__link" href="<?= h($newBookSearch) ?>" title="Search for this book"><?= h($newBook['title']) ?></a></h3><p class="mini-card__author"><?= h($newBook['authors']) ?></p></article>
          <?php endforeach; ?>
        </div>
      </section>

      <section aria-labelledby="cat-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="section-title" id="cat-h">Browse the catalog</h2>
        <div class="book-grid">
        <?php if (empty($books)): ?>
          <p class="sub"><?= ($advActive || catalogFiltersActive($search)) ? 'No books match your search.' : 'No books in the catalog yet.' ?></p>
        <?php endif; ?>
        <?php foreach ($books as $book): ?>
          <?php
            $bookId     = (int) $book['id'];
            $copiesLeft = (int) $book['copies_available'];
            $typeLabel  = mediaTypeLabel($book['media_type']);
            $pillClass  = mediaTypePillClass($book['media_type']);
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
            <?php if ($copiesLeft <= 0 && !$isBanned): ?><?= watchButtonHtml($bookId, in_array($bookId, $watchedBookIds, true)) ?><?php endif; ?>
            <div class="add-row">
              <select class="select" aria-label="Select list"><option value="">Select list</option><?php foreach ($userLists as $list): ?><option value="<?= (int) $list['id'] ?>"><?= h($list['name']) ?></option><?php endforeach; ?></select>
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

  <!-- Chatbot launcher and window: visual only, no messaging yet -->
  <section class="chatbot-window" id="chatbot-window" role="dialog" aria-labelledby="chatbot-title" hidden>
    <div class="chatbot-window__head">
      <div>
        <h2 class="chatbot-window__title" id="chatbot-title">Library assistant</h2>
        <p class="chatbot-window__sub">Ask about books, loans and more</p>
      </div>
      <button class="chatbot-window__x" type="button" id="chatbot-close" aria-label="Close chatbot">✕</button>
    </div>
    <div class="chatbot-window__body" aria-live="polite">
      <div class="chatbot-msg chatbot-msg--bot">Hi! I'm the library assistant. Chat is coming soon.</div>
    </div>
    <div class="chatbot-window__foot">
      <input class="chatbot-window__input" type="text" placeholder="Type a message..." aria-label="Message" disabled>
      <button class="chatbot-window__send" type="button" disabled>Send</button>
    </div>
  </section>

  <button class="chatbot-fab" type="button" id="chatbot-toggle" aria-label="Open chatbot" title="Chat with the library assistant" aria-expanded="false" aria-controls="chatbot-window">
    <svg class="chatbot-fab__open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.8 8.8 0 0 1-3.6-.8L3 21l1.9-5.1A8.3 8.3 0 0 1 3 11.5 8.5 8.5 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5z"/>
      <circle cx="8.5" cy="11.5" r=".6" fill="currentColor"/>
      <circle cx="12" cy="11.5" r=".6" fill="currentColor"/>
      <circle cx="15.5" cy="11.5" r=".6" fill="currentColor"/>
    </svg>
    <svg class="chatbot-fab__close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
      <path d="M6 6l12 12M18 6L6 18"/>
    </svg>
  </button>
  <script>
    // New books carousel: arrows scroll one page at a time
    (function () {
      var track = document.getElementById('new-books');
      var prev  = document.getElementById('prev-books');
      var next  = document.getElementById('next-books');
      if (!track || !prev || !next) return;

      function pageWidth() {
        var card = track.querySelector('.mini-card');
        if (!card) return track.clientWidth;
        var style = getComputedStyle(track);
        var gap   = parseFloat(style.columnGap || style.gap) || 0;
        var unit  = card.offsetWidth + gap;
        return unit * Math.max(1, Math.floor((track.clientWidth + gap) / unit));
      }
      function update() {
        var max = track.scrollWidth - track.clientWidth;
        prev.hidden = next.hidden = max <= 1;
        prev.disabled = track.scrollLeft <= 1;
        next.disabled = track.scrollLeft >= max - 1;
      }
      function go(dir) { track.scrollBy({ left: dir * pageWidth() }); }

      // Capture phase so these buttons only do this, even if another script also binds them
      document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('#prev-books, #next-books');
        if (!btn) return;
        e.stopImmediatePropagation();
        go(btn.id === 'prev-books' ? -1 : 1);
      }, true);

      track.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update);
      update();
    })();
  </script>
  <script>
    // Advanced search drop-down. The form must really submit to PHP, so keep app.js
    // (prototype) from intercepting its submit/click.
    (function () {
      var btn   = document.getElementById('toggle-advanced');
      var form  = document.getElementById('advanced-form');
      var caret = document.getElementById('advanced-caret');
      if (!btn || !form) return;

      document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('#toggle-advanced')) {
          e.stopImmediatePropagation();
          var open = form.hidden;
          form.hidden = !open;
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
          caret.textContent = open ? '▴' : '▾';
          if (open) { var first = form.querySelector('input'); if (first) first.focus(); }
        } else if (t.closest('#advanced-form button[type="submit"]')) {
          e.stopImmediatePropagation();
        }
      }, true);
      document.addEventListener('submit', function (e) {
        if (e.target && e.target.closest && e.target.closest('#advanced-form')) e.stopImmediatePropagation();
      }, true);
    })();
  </script>
  <script>
    // Chatbot window: open/close only
    (function () {
      var fab = document.getElementById('chatbot-toggle');
      var win = document.getElementById('chatbot-window');
      var closeBtn = document.getElementById('chatbot-close');
      function setOpen(open) {
        win.hidden = !open;
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        fab.setAttribute('aria-label', open ? 'Close chatbot' : 'Open chatbot');
      }
      fab.addEventListener('click', function () { setOpen(win.hidden); });
      closeBtn.addEventListener('click', function () { setOpen(false); fab.focus(); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !win.hidden) { setOpen(false); fab.focus(); }
      });
    })();
  </script>

  <script>
<?= notificationScript() ?>
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/borrow.js"></script>
  <script src="js/add-to-list.js"></script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>