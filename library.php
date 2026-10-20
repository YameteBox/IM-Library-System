<?php
session_start();
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';

$search = catalogSearchParams($_GET);
$books  = searchCatalog($pdo, $search);

// Books this student already has a pending request for
$studentId = $_SESSION['student_id'] ?? '2023-00123';
// TODO (backend): ids of the books this student has a pending request for.
$pendingBookIds = [];
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
        <a class="nav__link" href="dashboard.php">Dashboard</a>
        <a class="nav__link" href="login.php">Log out</a>
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
        <div class="carousel" id="new-books" role="region" aria-roledescription="carousel" aria-label="New books" tabindex="0">
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
