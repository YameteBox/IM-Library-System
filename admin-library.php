<?php
session_start();
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';

/** Valid = a real calendar date strictly before today (matches the date picker's max). */
function isValidPastDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return false;
    }
    return $d < new DateTime('today');
}

function formatShortDate(string $isoDate): string
{
    $timestamp = strtotime($isoDate);
    return $timestamp ? date('M j, Y', $timestamp) : $isoDate;
}

$catalogClassifications = [
    'General Works, Computer Science, and Information',
    'Philosophy and Psychology',
    'Religion',
    'Social Sciences',
    'Language',
    'Science',
    'Technology',
    'Arts and Recreation',
    'Literature',
    'History and Geography',
];

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$formAction  = 'admin-library.php' . ($queryString !== '' ? '?' . $queryString : '');

$flashMessage = '';
$flashType    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_stock') {

        $bookId    = (int) ($_POST['book_id'] ?? 0);
        $addCopies = (int) ($_POST['add_copies'] ?? 0);

        $stmt = $pdo->prepare('SELECT title FROM books WHERE id = ?');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            $flashMessage = 'That book could not be found.';
            $flashType    = 'error';
        } elseif ($addCopies <= 0) {
            $flashMessage = 'Enter a number of copies greater than zero.';
            $flashType    = 'error';
        } else {
            $pdo->prepare(
                'UPDATE books SET copies_available = copies_available + ? WHERE id = ?'
            )->execute([$addCopies, $bookId]);

            $flashMessage = 'Added ' . $addCopies . ' cop' . ($addCopies === 1 ? 'y' : 'ies')
                . ' of "' . $book['title'] . '".';
            $flashType    = 'success';
        }

    } elseif ($action === 'reduce_stock') {

        $bookId       = (int) ($_POST['book_id'] ?? 0);
        $removeCopies = (int) ($_POST['remove_copies'] ?? 0);

        $stmt = $pdo->prepare('SELECT title, copies_available FROM books WHERE id = ?');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            $flashMessage = 'That book could not be found.';
            $flashType    = 'error';
        } elseif ($removeCopies <= 0) {
            $flashMessage = 'Enter a number of copies greater than zero.';
            $flashType    = 'error';
        } elseif ($removeCopies > (int) $book['copies_available']) {
            $flashMessage = 'Cannot remove ' . $removeCopies . ' cop' . ($removeCopies === 1 ? 'y' : 'ies')
                . ' — only ' . $book['copies_available'] . ' currently available for "' . $book['title'] . '".';
            $flashType    = 'error';
        } else {
            $pdo->prepare(
                'UPDATE books SET copies_available = copies_available - ? WHERE id = ?'
            )->execute([$removeCopies, $bookId]);

            $flashMessage = 'Removed ' . $removeCopies . ' cop' . ($removeCopies === 1 ? 'y' : 'ies')
                . ' of "' . $book['title'] . '".';
            $flashType    = 'success';
        }

    } elseif ($action === 'edit_book') {

        $bookId         = (int) ($_POST['book_id'] ?? 0);
        $title          = trim($_POST['title'] ?? '');
        $authors        = trim($_POST['authors'] ?? '');
        $key_word       = trim($_POST['key_word'] ?? '');
        $classification = trim($_POST['classification'] ?? '');
        $isbn           = trim($_POST['isbn'] ?? '');
        $publishedDate  = trim($_POST['published_date'] ?? '');
        $shelfNumber    = trim($_POST['shelf_number'] ?? '');
        $mediaType      = trim($_POST['media_type'] ?? '');

        $stmt = $pdo->prepare('SELECT id FROM books WHERE id = ?');
        $stmt->execute([$bookId]);

        if (!$stmt->fetch()) {
            $flashMessage = 'That book could not be found.';
            $flashType    = 'error';
        } elseif ($title === '' || $authors === '' || $publishedDate === '' || $mediaType === '') {
            $flashMessage = 'Title, authors, published date, and media type are required.';
            $flashType    = 'error';
        } elseif (!isValidMediaType($mediaType)) {
            $flashMessage = 'Please choose a valid media type.';
            $flashType    = 'error';
        } elseif (!isValidPastDate($publishedDate)) {
            $flashMessage = 'Published date must be a past date.';
            $flashType    = 'error';
        } else {
            $pdo->prepare(
                'UPDATE books
                 SET title = ?, authors = ?, isbn = ?, published_date = ?,
                     shelf_number = ?, key_word = ?, classification = ?, media_type = ?
                 WHERE id = ?'
            )->execute([
                $title,
                $authors,
                $isbn !== '' ? $isbn : null,
                $publishedDate,
                $shelfNumber !== '' ? $shelfNumber : null,
                $key_word,
                $classification,
                $mediaType,
                $bookId,
            ]);

            $flashMessage = 'Updated "' . $title . '".';
            $flashType    = 'success';
        }
    }

    if ($flashMessage !== '') {
        $_SESSION['flash_message'] = $flashMessage;
        $_SESSION['flash_type']    = $flashType;
    }

    header('Location: ' . $formAction);
    exit;
}

if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType    = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Catalog
$search = catalogSearchParams($_GET);
$books  = searchCatalog($pdo, $search);

// Full loan history (every status, including returned), grouped by book
$stmt = $pdo->query("
    SELECT
        loans.id AS loan_id,
        loans.book_id,
        loans.status,
        loans.borrowed_at,
        loans.due_date,
        loans.returned_at,
        users.username AS borrower_username,
        users.student_id AS borrower_student_id
    FROM loans
    INNER JOIN users ON loans.user_id = users.student_id
    ORDER BY loans.borrowed_at DESC
");
$loanHistoryByBook = [];
foreach ($stmt->fetchAll() as $historyLoan) {
    $loanHistoryByBook[$historyLoan['book_id']][] = $historyLoan;
}

$maxPublished = date('Y-m-d', strtotime('-1 day'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin library · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .book-card__panel { margin: 10px 0; padding: 12px; border: 1px solid rgba(128,128,128,.35); border-radius: 10px; }
    .book-card__panel[hidden] { display: none; }
    .book-card__panel .field { margin-bottom: 8px; }
    .book-card__panel .btn-row { margin-top: 8px; }
    .stock-form { display: flex; gap: 8px; margin-bottom: 8px; }
    .stock-form .input { flex: 1; min-width: 0; }
    .history-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .history-table th, .history-table td { text-align: left; padding: 4px 6px; border-bottom: 1px solid rgba(128,128,128,.25); vertical-align: top; }
    .history-table small { opacity: .7; }
  </style>
</head>
<body data-page="admin-library">
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
    <nav class="tabs" aria-label="Admin sections"><a class="tab is-active" href="admin-library.php" aria-current="page">Catalog</a><a class="tab" href="admin-requests.php">Pending requests</a><a class="tab" href="admin-on-loan.php">On loan</a></nav>
    <div class="toast-region" data-toast-region aria-live="polite">
      <?php if ($flashMessage !== ''): ?>
        <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
          <?= h($flashMessage) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
    </div>
    <form class="toolbar" id="catalog-search" role="search" method="get" action="admin-library.php" data-server-search>
      <input class="input" name="q" type="search" value="<?= h($search['q']) ?>" placeholder="Search catalog..." aria-label="Search catalog">
      <select class="select" name="type" aria-label="Media type"><?= mediaTypeFilterOptions($search['type']) ?></select>
      <select class="select" name="field" aria-label="Search field"><?= searchFieldOptions($search['field']) ?></select>
      <button class="btn btn--round" type="submit">Search</button>
      <button class="btn btn--round btn--outline" type="button" id="toggle-add" aria-expanded="false" aria-controls="add-item-form">Add new item</button>
    </form>

    <form class="panel panel--lg form-stack" id="add-item-form" hidden novalidate>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="f-title">Title</label>
          <input class="input" id="f-title" name="title" type="text" value="" placeholder="Title of the item" >
        </div>
        <div class="field">
          <label for="f-authors">Authors (comma-separated)</label>
          <input class="input" id="f-authors" name="authors" type="text" value="" placeholder="e.g. Carl Sagan, Ann Druyan" >
        </div>
        <div class="field">
          <label for="f-mediatype">Media type</label>
          <select class="select" id="f-mediatype" name="mediatype"><option value="">Select a media type...</option><option value="Book">Book</option><option value="Board Game">Board Game</option><option value="Audiovisual">Audiovisual</option><option value="Digital Media">Digital Media</option></select>
        </div>
        <div class="field">
          <label for="f-isbn">ISBN</label>
          <input class="input" id="f-isbn" name="isbn" type="text" value="" placeholder="e.g. 978-0-345-53943-4" >
        </div>
      </div>
      <div class="form-grid form-grid--3">
        <div class="field">
          <label for="f-published">Published date</label>
          <input class="input" id="f-published" name="published" type="date" value="" max="<?= h($maxPublished) ?>">
        </div>
        <div class="field">
          <label for="f-shelf">Shelf number</label>
          <input class="input" id="f-shelf" name="shelf" type="text" value="" placeholder="e.g. A-1" >
        </div>
        <div class="field">
          <label for="f-copies">Copies available</label>
          <input class="input" id="f-copies" name="copies" type="number" value="1" placeholder="" min="0" inputmode="numeric">
        </div>
      </div>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="f-keywords">Key words (comma-separated)</label>
          <input class="input" id="f-keywords" name="keywords" type="text" value="" placeholder="e.g. Computer Science, PHP, Information Technology" >
        </div>
        <div class="field">
          <label for="f-classification">Classification</label>
          <select class="select" id="f-classification" name="classification"><option>General Works, Computer Science, and Information</option><option>Philosophy and Psychology</option><option>Religion</option><option>Social Sciences</option><option>Language</option><option>Science</option><option>Technology</option><option>Arts and Recreation</option><option>Literature</option><option>History and Geography</option></select>
        </div>
      </div>
      <div class="field">
          <label for="f-cover">Cover Image (.jpg, .png)</label>
          <input class="input" id="f-cover" name="cover" type="url" value="" placeholder="Paste image URL..." >
        </div>
      <div class="actions"><button class="btn btn--round" type="button">Upload Image</button></div>
      <div><button class="btn btn--round" type="submit">Add Item</button></div>
    </form>

    <div class="book-grid" id="catalog">
      <?php if (empty($books)): ?>
        <p><?= catalogFiltersActive($search) ? 'No books match your search.' : 'No books in the catalog yet.' ?></p>
      <?php endif; ?>
      <?php foreach ($books as $book): ?>
        <?php
          $bookId      = (int) $book['id'];
          $copiesLeft  = (int) $book['copies_available'];
          $typeLabel   = mediaTypeLabel($book['media_type']);
          $pillClass   = mediaTypeBadgeClass($book['media_type']) === 'success' ? 'pill--green' : 'pill--blue';
          $keywords    = array_filter(array_map('trim', explode(',', (string) ($book['key_word'] ?? ''))));
          $bookHistory = $loanHistoryByBook[$book['id']] ?? [];
          $meta        = [formatPublishedDate((string) $book['published_date'])];
          if (!empty($book['isbn']))         { $meta[] = 'ISBN ' . $book['isbn']; }
          if (!empty($book['shelf_number'])) { $meta[] = 'Shelf ' . $book['shelf_number']; }
        ?>
        <article class="book-card" data-title="<?= h($book['title']) ?>" data-copies="<?= $copiesLeft ?>" data-type="<?= h($typeLabel) ?>">
          <div class="book-card__cover" role="img" aria-label="Cover placeholder for <?= h($book['title']) ?>"></div>
          <div class="book-card__body">
            <div class="book-card__top"><span class="pill <?= $pillClass ?>"><?= h($typeLabel) ?></span></div>
            <h3 class="book-card__title"><?= h($book['title']) ?></h3>
            <span class="book-card__author"><?= h($book['authors']) ?></span>
            <span class="book-card__meta"><?= h(implode('  ·  ', $meta)) ?></span>
            <div class="chips">
              <?php if (!empty($book['classification'])): ?><span class="chip chip--primary"><?= h($book['classification']) ?></span><?php endif; ?>
              <?php foreach ($keywords as $kw): ?><span class="chip"><?= h($kw) ?></span><?php endforeach; ?>
            </div>
            <span class="book-card__meta"><?= $copiesLeft > 0 ? $copiesLeft . ' cop' . ($copiesLeft === 1 ? 'y' : 'ies') . ' available' : 'No copies left' ?></span>
          </div>
          <div class="book-card__actions">
            <div class="btn-row">
              <button class="btn btn--outline btn--sm" type="button" data-panel-target="edit-<?= $bookId ?>" aria-expanded="false" aria-controls="edit-<?= $bookId ?>">Edit Info</button>
              <button class="btn btn--outline btn--sm" type="button" data-panel-target="stock-<?= $bookId ?>" aria-expanded="false" aria-controls="stock-<?= $bookId ?>">Edit Stock</button>
              <button class="btn btn--outline btn--sm" type="button" data-panel-target="history-<?= $bookId ?>" aria-expanded="false" aria-controls="history-<?= $bookId ?>">View Loan History</button>
            </div>

            <!-- Edit info -->
            <div class="book-card__panel" id="edit-<?= $bookId ?>" hidden>
              <form method="POST" action="<?= h($formAction) ?>">
                <input type="hidden" name="action" value="edit_book">
                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                <div class="field">
                  <label for="edit-title-<?= $bookId ?>">Title</label>
                  <input class="input" id="edit-title-<?= $bookId ?>" name="title" type="text" value="<?= h($book['title']) ?>" required>
                </div>
                <div class="field">
                  <label for="edit-authors-<?= $bookId ?>">Authors (comma-separated)</label>
                  <input class="input" id="edit-authors-<?= $bookId ?>" name="authors" type="text" value="<?= h($book['authors']) ?>" required>
                </div>
                <div class="field">
                  <label for="edit-media-<?= $bookId ?>">Media type</label>
                  <select class="select" id="edit-media-<?= $bookId ?>" name="media_type" required>
                    <?php foreach (MEDIA_TYPES as $key => $label): ?>
                      <option value="<?= h($key) ?>" <?= $book['media_type'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="edit-isbn-<?= $bookId ?>">ISBN</label>
                  <input class="input" id="edit-isbn-<?= $bookId ?>" name="isbn" type="text" value="<?= h($book['isbn'] ?? '') ?>">
                </div>
                <div class="field">
                  <label for="edit-published-<?= $bookId ?>">Published date</label>
                  <input class="input" id="edit-published-<?= $bookId ?>" name="published_date" type="date" max="<?= h($maxPublished) ?>" value="<?= h($book['published_date']) ?>" required>
                </div>
                <div class="field">
                  <label for="edit-shelf-<?= $bookId ?>">Shelf number</label>
                  <input class="input" id="edit-shelf-<?= $bookId ?>" name="shelf_number" type="text" value="<?= h($book['shelf_number'] ?? '') ?>">
                </div>
                <div class="field">
                  <label for="edit-keywords-<?= $bookId ?>">Key words (comma-separated)</label>
                  <input class="input" id="edit-keywords-<?= $bookId ?>" name="key_word" type="text" value="<?= h($book['key_word'] ?? '') ?>">
                </div>
                <div class="field">
                  <label for="edit-class-<?= $bookId ?>">Classification</label>
                  <select class="select" id="edit-class-<?= $bookId ?>" name="classification">
                    <?php foreach ($catalogClassifications as $category): ?>
                      <option value="<?= h($category) ?>" <?= ($book['classification'] ?? '') === $category ? 'selected' : '' ?>><?= h($category) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button class="btn btn--round btn--sm" type="submit">Save Changes</button>
              </form>
            </div>

            <!-- Edit stock -->
            <div class="book-card__panel" id="stock-<?= $bookId ?>" hidden>
              <form method="POST" action="<?= h($formAction) ?>" class="stock-form">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                <input class="input" type="number" name="add_copies" min="1" placeholder="Add copies" aria-label="Copies to add" required>
                <button class="btn btn--sm" type="submit">Add Stock</button>
              </form>
              <form method="POST" action="<?= h($formAction) ?>" class="stock-form">
                <input type="hidden" name="action" value="reduce_stock">
                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                <input class="input" type="number" name="remove_copies" min="1" max="<?= $copiesLeft ?>" placeholder="Remove copies" aria-label="Copies to remove" required>
                <button class="btn btn--danger btn--sm" type="submit">Remove Stock</button>
              </form>
            </div>

            <!-- Loan history -->
            <div class="book-card__panel" id="history-<?= $bookId ?>" hidden>
              <?php if (empty($bookHistory)): ?>
                <p>No loan history for this item yet.</p>
              <?php else: ?>
                <div style="overflow-x:auto">
                  <table class="history-table">
                    <thead>
                      <tr><th>Borrower</th><th>Status</th><th>Borrowed</th><th>Due</th><th>Returned</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ($bookHistory as $historyLoan): ?>
                        <?php $displayStatus = $historyLoan['returned_at'] ? 'returned' : $historyLoan['status']; ?>
                        <tr>
                          <td><?= h($historyLoan['borrower_username']) ?><br><small>#<?= h($historyLoan['borrower_student_id']) ?></small></td>
                          <td><?= h(ucfirst($displayStatus)) ?></td>
                          <td><?= h(formatShortDate($historyLoan['borrowed_at'])) ?></td>
                          <td><?= h(formatShortDate($historyLoan['due_date'])) ?></td>
                          <td><?= $historyLoan['returned_at'] ? h(formatShortDate($historyLoan['returned_at'])) : '—' ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <button class="btn btn--block btn--danger btn--sm" type="button" data-action="delete-item">Delete Item</button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    // Block future dates even though the form is novalidate
    (function () {
      var form = document.getElementById('add-item-form');
      var d = document.getElementById('f-published');
      form.addEventListener('submit', function (e) {
        if (d.value && d.value > d.max) {
          e.preventDefault(); e.stopImmediatePropagation();
          d.setCustomValidity('Please choose a past date.');
          d.reportValidity();
          d.setCustomValidity('');
        }
      }, true);
    })();
  </script>
  <script>
    // Edit Info / Edit Stock / View Loan History: one panel open per card
    document.querySelectorAll('[data-panel-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var card = btn.closest('.book-card');
        var target = document.getElementById(btn.dataset.panelTarget);
        var opening = target.hidden;
        card.querySelectorAll('.book-card__panel').forEach(function (p) { p.hidden = true; });
        card.querySelectorAll('[data-panel-target]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        if (opening) { target.hidden = false; btn.setAttribute('aria-expanded', 'true'); }
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
