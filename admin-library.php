<?php

session_start();

// Admins only

if (empty($_SESSION['admin_id'])) {

    header('Location: admin-login.php');

    exit;

}

require 'db.php';

require_once 'media_types.php';

require_once 'search_helpers.php';

ensureCoverColumn($pdo);

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

    try {
        if ($action === 'add_book') {
            $title = trim($_POST['title'] ?? '');
            $authors = trim($_POST['authors'] ?? '');
            $type = $_POST['media_type'] ?? '';
            $isbn = trim($_POST['isbn'] ?? '');
            $publishedDate = trim($_POST['published_date'] ?? '');
            $shelf = trim($_POST['shelf_number'] ?? '');
            $copies = filter_var($_POST['copies_available'] ?? '', FILTER_VALIDATE_INT);
            $keywords = trim($_POST['key_word'] ?? '');
            $classification = trim($_POST['classification'] ?? '');

            if ($title === '' || $authors === '') {
                throw new RuntimeException('Title and authors are required.');
            }
            if (strlen($title) > 255 || strlen($authors) > 255) {
                throw new RuntimeException('Title and authors must be 255 characters or fewer.');
            }
            if (!isValidMediaType($type)) {
                throw new RuntimeException('Please select a valid media type.');
            }
            if (strlen($isbn) > 20 || strlen($shelf) > 20) {
                throw new RuntimeException('ISBN and shelf number must be 20 characters or fewer.');
            }
            if ($publishedDate === '' || !isValidPastDate($publishedDate)) {
                throw new RuntimeException('Enter a valid published date before today.');
            }
            if ($copies === false || $copies < 0) {
                throw new RuntimeException('Copies must be a whole number of 0 or more.');
            }
            if ($classification !== '' && !in_array($classification, $catalogClassifications, true)) {
                throw new RuntimeException('Please choose a valid classification.');
            }

            [$cover, $coverError] = saveCoverImage('cover_file', (string)($_POST['cover_url'] ?? ''));
            if ($coverError !== null) {
                throw new RuntimeException($coverError);
            }

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO books
                        (title, authors, media_type, isbn, published_date, classification,
                         key_word, shelf_number, cover_image, total_copies, copies_available)
                     VALUES
                        (:title, :authors, :type, :isbn, :published, :classification,
                         :keywords, :shelf, :cover, :total, :available)'
                );
                $stmt->execute([
                    ':title' => $title,
                    ':authors' => $authors,
                    ':type' => $type,
                    ':isbn' => $isbn !== '' ? $isbn : null,
                    ':published' => $publishedDate,
                    ':classification' => $classification !== '' ? $classification : null,
                    ':keywords' => $keywords !== '' ? $keywords : null,
                    ':shelf' => $shelf !== '' ? $shelf : null,
                    ':cover' => $cover,
                    ':total' => $copies,
                    ':available' => $copies,
                ]);
            } catch (Throwable $e) {
                if ($cover !== null) {
                    deleteLocalCover($cover);
                }
                throw $e;
            }

            $flashMessage = 'Added "' . $title . '" to the catalog.';
            $flashType = 'success';

        } elseif ($action === 'edit_book') {
            $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $authors = trim($_POST['authors'] ?? '');
            $type = $_POST['media_type'] ?? '';
            $isbn = trim($_POST['isbn'] ?? '');
            $publishedDate = trim($_POST['published_date'] ?? '');
            $shelf = trim($_POST['shelf_number'] ?? '');
            $keywords = trim($_POST['key_word'] ?? '');
            $classification = trim($_POST['classification'] ?? '');

            if (!$bookId || $bookId < 1) {
                throw new RuntimeException('Invalid book selected.');
            }
            $stmt = $pdo->prepare('SELECT id, title, cover_image FROM books WHERE id = :id');
            $stmt->execute([':id' => $bookId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new RuntimeException('That book could not be found.');
            }
            if ($title === '' || $authors === '' || strlen($title) > 255 || strlen($authors) > 255) {
                throw new RuntimeException('Enter a valid title and author(s).');
            }
            if (!isValidMediaType($type)) {
                throw new RuntimeException('Please select a valid media type.');
            }
            if (strlen($isbn) > 20 || strlen($shelf) > 20) {
                throw new RuntimeException('ISBN and shelf number must be 20 characters or fewer.');
            }
            if ($publishedDate === '' || !isValidPastDate($publishedDate)) {
                throw new RuntimeException('Enter a valid published date before today.');
            }
            if ($classification !== '' && !in_array($classification, $catalogClassifications, true)) {
                throw new RuntimeException('Please choose a valid classification.');
            }

            $currentCover = $existing['cover_image'] ?? null;
            [$newCover, $coverError] = saveCoverImage('cover_file', (string)($_POST['cover_url'] ?? ''));
            if ($coverError !== null) {
                throw new RuntimeException($coverError);
            }

            $cover = $currentCover;
            if ($newCover !== null) {
                $cover = $newCover;
            } elseif (isset($_POST['remove_cover'])) {
                $cover = null;
            }

            try {
                $stmt = $pdo->prepare(
                    'UPDATE books
                     SET title = :title,
                         authors = :authors,
                         media_type = :type,
                         isbn = :isbn,
                         published_date = :published,
                         shelf_number = :shelf,
                         key_word = :keywords,
                         classification = :classification,
                         cover_image = :cover
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':title' => $title,
                    ':authors' => $authors,
                    ':type' => $type,
                    ':isbn' => $isbn !== '' ? $isbn : null,
                    ':published' => $publishedDate,
                    ':shelf' => $shelf !== '' ? $shelf : null,
                    ':keywords' => $keywords !== '' ? $keywords : null,
                    ':classification' => $classification !== '' ? $classification : null,
                    ':cover' => $cover,
                    ':id' => $bookId,
                ]);
            } catch (Throwable $e) {
                if ($newCover !== null && $newCover !== $currentCover) {
                    deleteLocalCover($newCover);
                }
                throw $e;
            }

            if ($cover !== $currentCover) {
                deleteLocalCover($currentCover);
            }
            $flashMessage = 'Updated "' . $title . '".';
            $flashType = 'success';

        } elseif ($action === 'update_stock' || $action === 'reduce_stock') {
            $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = $action === 'update_stock'
                ? filter_var($_POST['add_copies'] ?? null, FILTER_VALIDATE_INT)
                : filter_var($_POST['remove_copies'] ?? null, FILTER_VALIDATE_INT);

            if (!$bookId || $bookId < 1) {
                throw new RuntimeException('Invalid book selected.');
            }
            if ($quantity === false || $quantity === null || $quantity < 1) {
                throw new RuntimeException('Enter a whole number of copies greater than zero.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, title, total_copies, copies_available FROM books WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $bookId]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$book) {
                $pdo->rollBack();
                throw new RuntimeException('That book could not be found.');
            }

            if ($action === 'update_stock') {
                $stmt = $pdo->prepare(
                    'UPDATE books
                     SET total_copies = total_copies + :quantity,
                         copies_available = copies_available + :quantity
                     WHERE id = :id'
                );
                $stmt->execute([':quantity' => $quantity, ':id' => $bookId]);
                $flashMessage = 'Added ' . $quantity . ' cop' . ($quantity === 1 ? 'y' : 'ies') . ' to "' . $book['title'] . '".';
            } else {
                if ($quantity > (int)$book['copies_available']) {
                    $pdo->rollBack();
                    throw new RuntimeException('You cannot remove more copies than are currently available.');
                }
                $stmt = $pdo->prepare(
                    'UPDATE books
                     SET total_copies = total_copies - :quantity,
                         copies_available = copies_available - :quantity
                     WHERE id = :id'
                );
                $stmt->execute([':quantity' => $quantity, ':id' => $bookId]);
                $flashMessage = 'Removed ' . $quantity . ' cop' . ($quantity === 1 ? 'y' : 'ies') . ' from "' . $book['title'] . '".';
            }
            $pdo->commit();
            $flashType = 'success';

        } elseif ($action === 'delete_book') {
            $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$bookId || $bookId < 1) {
                throw new RuntimeException('Invalid book selected.');
            }
            $stmt = $pdo->prepare('SELECT title, cover_image FROM books WHERE id = :id');
            $stmt->execute([':id' => $bookId]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$book) {
                throw new RuntimeException('That book could not be found.');
            }
            $stmt = $pdo->prepare('DELETE FROM books WHERE id = :id');
            $stmt->execute([':id' => $bookId]);
            deleteLocalCover($book['cover_image'] ?? null);
            $flashMessage = 'Deleted "' . $book['title'] . '" from the catalog.';
            $flashType = 'success';

        } elseif ($action === 'approve_request' || $action === 'reject_request') {
            $loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$loanId || $loanId < 1) {
                throw new RuntimeException('Invalid borrowing request.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT bb.id, bb.book_id, bb.status, b.title, b.copies_available
                 FROM borrowed_books bb
                 JOIN books b ON b.id = bb.book_id
                 WHERE bb.id = :id AND bb.status = 'pending' AND bb.returned_at IS NULL
                 FOR UPDATE OF bb, b"
            );
            $stmt->execute([':id' => $loanId]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$loan) {
                $pdo->rollBack();
                throw new RuntimeException('This request no longer exists or has already been processed.');
            }

            if ($action === 'reject_request') {
                $stmt = $pdo->prepare("UPDATE borrowed_books SET status = 'rejected' WHERE id = :id");
                $stmt->execute([':id' => $loanId]);
                $flashMessage = 'Rejected the request for "' . $loan['title'] . '".';
            } else {
                if ((int)$loan['copies_available'] < 1) {
                    $pdo->rollBack();
                    throw new RuntimeException('No copies of this book are currently available.');
                }
                $stmt = $pdo->prepare(
                    "UPDATE borrowed_books
                     SET status = 'approved',
                         approved_at = CURRENT_TIMESTAMP,
                         approved_by = :admin_id,
                         due_date = CURRENT_DATE + INTERVAL '7 days'
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':admin_id' => (int)$_SESSION['admin_id'],
                    ':id' => $loanId,
                ]);
                $stmt = $pdo->prepare('UPDATE books SET copies_available = copies_available - 1 WHERE id = :id');
                $stmt->execute([':id' => $loan['book_id']]);
                $flashMessage = 'Approved the request for "' . $loan['title'] . '".';
            }
            $pdo->commit();
            $flashType = 'success';

        } elseif ($action === 'return_book') {
            $loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$loanId || $loanId < 1) {
                throw new RuntimeException('Invalid loan selected.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT bb.id, bb.book_id, b.title
                 FROM borrowed_books bb
                 JOIN books b ON b.id = bb.book_id
                 WHERE bb.id = :id AND bb.status = 'approved' AND bb.returned_at IS NULL
                 FOR UPDATE OF bb, b"
            );
            $stmt->execute([':id' => $loanId]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$loan) {
                $pdo->rollBack();
                throw new RuntimeException('This loan was already returned or could not be found.');
            }

            $stmt = $pdo->prepare('UPDATE borrowed_books SET returned_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([':id' => $loanId]);
            $stmt = $pdo->prepare('UPDATE books SET copies_available = copies_available + 1 WHERE id = :id');
            $stmt->execute([':id' => $loan['book_id']]);
            $pdo->commit();

            $flashMessage = 'Marked "' . $loan['title'] . '" as returned.';
            $flashType = 'success';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin library action failed: ' . $e->getMessage());
        $flashMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'The operation failed. Check the PHP error log for details.';
        $flashType = 'error';
    }

    if ($flashMessage !== '') {
        $_SESSION['flash_message'] = $flashMessage;
        $_SESSION['flash_type'] = $flashType;
    }

    header('Location: ' . $formAction . (str_contains($formAction, '#') ? '' : ''));
    exit;
}

if (isset($_SESSION['flash_message'])) {

    $flashMessage = $_SESSION['flash_message'];

    $flashType    = $_SESSION['flash_type'] ?? 'success';

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);

}

// Load catalog books using the existing server-side search helpers.
$search = catalogSearchParams($_GET);
$books = searchCatalog($pdo, $search);

// Full loan history for each catalog item, including pending, rejected,
// approved, and returned loans.
$loanHistoryByBook = [];
$historyStmt = $pdo->query(
    "SELECT
        bb.id AS loan_id,
        bb.book_id,
        bb.status,
        bb.borrowed_at,
        bb.due_date,
        bb.returned_at,
        COALESCE(ud.username, u.student_number, 'Unknown user') AS borrower_username,
        u.student_number AS borrower_student_id
     FROM borrowed_books bb
     JOIN books b ON b.id = bb.book_id
     JOIN users u ON u.id = bb.user_id
     LEFT JOIN user_details ud ON ud.id = u.id
     ORDER BY bb.borrowed_at DESC, bb.id DESC"
);
foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $historyRow) {
    $loanHistoryByBook[(int)$historyRow['book_id']][] = $historyRow;
}

// Real pending requests and currently borrowed books.
$pendingStmt = $pdo->query(
    "SELECT
        bb.id AS loan_id,
        bb.book_id,
        bb.user_id,
        bb.status,
        bb.borrowed_at,
        bb.due_date,
        b.title,
        COALESCE(ud.username, u.student_number, 'Unknown user') AS borrower_username,
        u.student_number AS borrower_student_id
     FROM borrowed_books bb
     JOIN books b ON b.id = bb.book_id
     JOIN users u ON u.id = bb.user_id
     LEFT JOIN user_details ud ON ud.id = u.id
     WHERE bb.status = 'pending' AND bb.returned_at IS NULL
     ORDER BY bb.borrowed_at ASC, bb.id ASC"
);
$pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

$onLoanStmt = $pdo->query(
    "SELECT
        bb.id AS loan_id,
        bb.book_id,
        bb.user_id,
        bb.status,
        bb.borrowed_at,
        bb.due_date,
        b.title,
        COALESCE(ud.username, u.student_number, 'Unknown user') AS borrower_username,
        u.student_number AS borrower_student_id
     FROM borrowed_books bb
     JOIN books b ON b.id = bb.book_id
     JOIN users u ON u.id = bb.user_id
     LEFT JOIN user_details ud ON ud.id = u.id
     WHERE bb.status = 'approved' AND bb.returned_at IS NULL
     ORDER BY bb.due_date ASC NULLS LAST, bb.borrowed_at ASC"
);
$onLoanBooks = $onLoanStmt->fetchAll(PDO::FETCH_ASSOC);

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

  <link rel="stylesheet" href="css/sidebar.css">

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

    /* Receipt: 3.5 x 5 in, same size on screen and on paper */

    #receipt-dialog { border: 0; border-radius: 12px; padding: 16px; width: auto; max-width: calc(100% - 32px); }

    #receipt-dialog::backdrop { background: rgba(0,0,0,.5); }

    .receipt { box-sizing: border-box; width: 3.5in; max-width: 100%; height: 5in; padding: .25in; display: flex; flex-direction: column;

      font-family: "Courier New", monospace; font-size: 10pt; line-height: 1.35; color: #000; background: #fff; border: 1px dashed #888; overflow: hidden; }

    .receipt h2 { font-size: 13pt; text-align: center; margin: 0 0 2px; }

    .receipt__sub { text-align: center; font-size: 9pt; margin: 0 0 .15in; }

    .receipt dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 5px 10px; }

    .receipt dt { font-weight: 700; }

    .receipt dd { margin: 0; text-align: right; overflow-wrap: anywhere; }

    .receipt__foot { margin: auto 0 0; text-align: center; font-size: 8pt; border-top: 1px dashed #888; padding-top: 8px; }

    .receipt-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }

    @page { size: 3.5in 5in; margin: 0; }

    @media print {

      html, body { margin: 0; padding: 0; background: #fff; }

      body > *:not(#receipt-dialog) { display: none !important; }

      #receipt-dialog { position: static; inset: auto; margin: 0; padding: 0; border: 0; border-radius: 0; width: 3.5in; height: 5in; max-width: none; overflow: hidden; }

      .receipt { border: 0; break-inside: avoid; }

      .receipt-actions { display: none !important; }

    }

  </style>

</head>

<body data-page="admin-library">

  <div class="page">

    <header class="site-header">

      <div class="brand">

        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">

        <div class="brand__text">

          <h1 class="brand__title">Library Management — Admin #<?= h($_SESSION['admin_number'] ?? 'Admin') ?></h1>

          <span class="role-badge">admin</span>

        </div>

      </div>

      <nav class="nav" aria-label="Main">

        <a class="nav__link" href="admin-register.php">Register New User</a>

        <a class="nav__link" href="admin-events.php">Calendar Events</a>

        <a class="nav__link" href="suspend-user.php">Suspend / Unsuspend User</a>

        <a class="nav__link" href="admin-profile.php">Profile</a>

        <a class="nav__link" href="admin-logout.php">Log out</a>

      </nav>

    </header>

    <nav class="tabs" role="tablist" aria-label="Admin sections"><a class="tab is-active" href="#catalog" role="tab" data-tab="catalog" aria-selected="true" aria-current="page">Catalog</a><a class="tab" href="#requests" role="tab" data-tab="requests" aria-selected="false">Pending requests</a><a class="tab" href="#loan" role="tab" data-tab="loan" aria-selected="false">On loan</a></nav>

    <div class="toast-region" data-toast-region aria-live="polite">

      <?php if ($flashMessage !== ''): ?>

        <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">

          <?= h($flashMessage) ?>

          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>

        </div>

      <?php endif; ?>

    </div>

    <section id="tab-catalog" role="tabpanel" data-tab-panel>

    <form class="toolbar" id="catalog-search" role="search" method="get" action="admin-library.php" data-server-search>

      <input class="input" name="q" type="search" value="<?= h($search['q']) ?>" placeholder="Search catalog..." aria-label="Search catalog">

      <select class="select" name="type" aria-label="Media type"><?= mediaTypeFilterOptions($search['type']) ?></select>

      <select class="select" name="field" aria-label="Search field"><?= searchFieldOptions($search['field']) ?></select>

      <button class="btn btn--round" type="submit">Search</button>

      <button class="btn btn--round btn--outline" type="button" id="toggle-add" aria-expanded="false" aria-controls="add-item-form">Add new item</button>

    </form>

    <form class="panel panel--lg form-stack" id="add-item-form" method="post" action="<?= h($formAction) ?>" enctype="multipart/form-data" hidden data-native-submit>

      <input type="hidden" name="action" value="add_book">

      <div class="form-grid form-grid--2">

        <div class="field">

          <label for="f-title">Title</label>

          <input class="input" id="f-title" name="title" type="text" placeholder="Title of the item" required>

        </div>

        <div class="field">

          <label for="f-authors">Authors (comma-separated)</label>

          <input class="input" id="f-authors" name="authors" type="text" placeholder="e.g. Carl Sagan, Ann Druyan" required>

        </div>

        <div class="field">

          <label for="f-mediatype">Media type</label>

          <select class="select" id="f-mediatype" name="media_type" required>

            <option value="">Select a media type...</option>

            <?php foreach (MEDIA_TYPES as $key => $label): ?>

              <option value="<?= h($key) ?>"><?= h($label) ?></option>

            <?php endforeach; ?>

          </select>

        </div>

        <div class="field">

          <label for="f-isbn">ISBN</label>

          <input class="input" id="f-isbn" name="isbn" type="text" placeholder="e.g. 978-0-345-53943-4">

        </div>

      </div>

      <div class="form-grid form-grid--3">

        <div class="field">

          <label for="f-published">Published date</label>

          <input class="input" id="f-published" name="published_date" type="date" max="<?= h($maxPublished) ?>" required>

        </div>

        <div class="field">

          <label for="f-shelf">Shelf number</label>

          <input class="input" id="f-shelf" name="shelf_number" type="text" placeholder="e.g. A-1">

        </div>

        <div class="field">

          <label for="f-copies">Copies available</label>

          <input class="input" id="f-copies" name="copies_available" type="number" value="1" min="0" inputmode="numeric" required>

        </div>

      </div>

      <div class="form-grid form-grid--2">

        <div class="field">

          <label for="f-keywords">Key words (comma-separated)</label>

          <input class="input" id="f-keywords" name="key_word" type="text" placeholder="e.g. Computer Science, PHP, Information Technology">

        </div>

        <div class="field">

          <label for="f-classification">Classification</label>

          <select class="select" id="f-classification" name="classification">

            <?php foreach ($catalogClassifications as $category): ?>

              <option value="<?= h($category) ?>"><?= h($category) ?></option>

            <?php endforeach; ?>

          </select>

        </div>

      </div>

      <div class="field">

        <label for="f-cover">Cover Image (.jpg, .png)</label>

        <input class="input" id="f-cover" name="cover_url" type="url" placeholder="Paste image URL...">

      </div>

      <div class="actions" style="align-items:center;gap:12px">

        <input type="file" id="f-cover-file" name="cover_file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" hidden>

        <button class="btn btn--round" type="button" id="upload-cover">Upload Image</button>

        <span id="cover-file-name" aria-live="polite">No file chosen</span>

        <img id="cover-preview" alt="Cover preview" hidden style="height:64px;border-radius:6px">

      </div>

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

          <div class="book-card__cover<?= !empty($book['cover_image']) ? ' has-cover' : '' ?>" role="img" aria-label="<?= empty($book['cover_image']) ? 'Cover placeholder for ' : 'Cover of ' ?><?= h($book['title']) ?>"><?= coverImgHtml($book['cover_image'] ?? null) ?></div>

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

              <form method="POST" action="<?= h($formAction) ?>" enctype="multipart/form-data" data-native-submit>

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

                <div class="field">

                  <label for="edit-coverurl-<?= $bookId ?>">Change cover image URL</label>

                  <input class="input" id="edit-coverurl-<?= $bookId ?>" name="cover_url" type="url" value="<?= coverIsUrl($book['cover_image'] ?? null) ? h($book['cover_image']) : '' ?>" placeholder="Paste image URL...">

                </div>

                <div class="field">

                  <label for="edit-coverfile-<?= $bookId ?>">Or upload a new image (.jpg, .png, max 2 MB)</label>

                  <input class="input" id="edit-coverfile-<?= $bookId ?>" name="cover_file" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png">

                </div>

                <?php if (!empty($book['cover_image'])): ?>

                  <label class="choice"><input type="checkbox" name="remove_cover" value="1"> Remove current cover</label>

                <?php endif; ?>

                <p style="font-size:12px;opacity:.75;margin:4px 0 8px">Leave both empty to keep the current cover. An uploaded file replaces the URL.</p>

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

            <form method="post" action="<?= h($formAction) ?>" data-native-submit onsubmit="return confirm('Delete this catalog item? This will fail if it has borrowing history.');">
                <input type="hidden" name="action" value="delete_book">
                <input type="hidden" name="book_id" value="<?= $bookId ?>">
                <button class="btn btn--block btn--danger btn--sm" type="submit">Delete Item</button>
              </form>

          </div>

        </article>

      <?php endforeach; ?>

    </div>

    </section>

    <section id="tab-requests" role="tabpanel" data-tab-panel hidden>
      <div class="request-grid" id="request-grid">
        <?php if (empty($pendingRequests)): ?>
          <p>There are no pending borrowing requests.</p>
        <?php else: ?>
          <?php foreach ($pendingRequests as $request): ?>
            <article class="request-card" data-title="<?= h($request['title']) ?>">
              <h3 class="request-card__title"><?= h($request['title']) ?></h3>
              <p class="request-card__line">
                Borrower: <?= h($request['borrower_username']) ?>
                (#<?= h($request['borrower_student_id']) ?>)
              </p>
              <p class="request-card__date">Requested <?= h(formatShortDate((string)$request['borrowed_at'])) ?></p>
              <div class="btn-row">
                <form method="post" action="<?= h($formAction) ?>" data-native-submit>
                  <input type="hidden" name="action" value="approve_request">
                  <input type="hidden" name="loan_id" value="<?= (int)$request['loan_id'] ?>">
                  <button class="btn" type="submit">Approve</button>
                </form>
                <form method="post" action="<?= h($formAction) ?>" data-native-submit>
                  <input type="hidden" name="action" value="reject_request">
                  <input type="hidden" name="loan_id" value="<?= (int)$request['loan_id'] ?>">
                  <button class="btn btn--danger" type="submit">Reject</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section id="tab-loan" role="tabpanel" data-tab-panel hidden>
      <div class="request-grid" id="loan-grid">
        <?php if (empty($onLoanBooks)): ?>
          <p>There are currently no books on loan.</p>
        <?php else: ?>
          <?php foreach ($onLoanBooks as $loan): ?>
            <article class="request-card" data-title="<?= h($loan['title']) ?>">
              <h3 class="request-card__title"><?= h($loan['title']) ?></h3>
              <p class="request-card__line">
                Borrower: <?= h($loan['borrower_username']) ?>
                (#<?= h($loan['borrower_student_id']) ?>)
              </p>
              <p class="request-card__date">
                Due <?= !empty($loan['due_date']) ? h(formatShortDate((string)$loan['due_date'])) : 'Not set' ?>
              </p>
              <form method="post" action="<?= h($formAction) ?>" data-native-submit>
                <input type="hidden" name="action" value="return_book">
                <input type="hidden" name="loan_id" value="<?= (int)$loan['loan_id'] ?>">
                <button class="btn btn--block" type="submit">Confirm Returned</button>
              </form>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

  </div>

  <!-- Loan receipt (shown after a request is approved) -->

  <dialog id="receipt-dialog" aria-labelledby="receipt-heading">

    <div class="receipt" id="receipt-print">

      <h2 id="receipt-heading">Manila Science High School</h2>

      <p class="receipt__sub">Online Library · Loan Receipt</p>

      <dl>

        <dt>Receipt no.</dt><dd id="rc-no"></dd>

        <dt>Item</dt><dd id="rc-title"></dd>

        <dt>Borrower</dt><dd id="rc-borrower"></dd>

        <dt>Student ID</dt><dd id="rc-sid"></dd>

        <dt>Requested</dt><dd id="rc-requested"></dd>

        <dt>Approved</dt><dd id="rc-approved"></dd>

        <dt>Due date</dt><dd id="rc-due"></dd>

      </dl>

      <p class="receipt__foot">Approved by Admin #<?= h($_SESSION['admin_number'] ?? 'Admin') ?>. Please return on or before the due date.</p>

    </div>

    <div class="receipt-actions">

      <button type="button" class="btn btn--outline" id="close-receipt">Close</button>

      <button type="button" class="btn" id="print-receipt">Print receipt</button>

    </div>

  </dialog>

  <script>

    // "Upload Image": open the file picker, validate the choice, show a preview

    (function () {

      var file = document.getElementById('f-cover-file');

      var name = document.getElementById('cover-file-name');

      var preview = document.getElementById('cover-preview');

      var url = document.getElementById('f-cover');

      function clearFile(message) {

        file.value = '';

        name.textContent = message || 'No file chosen';

        preview.hidden = true;

        preview.removeAttribute('src');

      }

      // Capture phase so the page's other click handlers don't also react to this button

      document.addEventListener('click', function (e) {

        if (e.target.closest && e.target.closest('#upload-cover')) {

          e.stopImmediatePropagation();

          file.click();

        }

      }, true);

      file.addEventListener('change', function () {

        var f = file.files[0];

        if (!f) { clearFile(); return; }

        if (!/**.**(jpe?g|png)$/i.test(f.name) || !/^imag&#x65;**/**(jpeg|png)$/.test(f.type)) {

          clearFile('Please choose a .jpg or .png file.');

          return;

        }

        if (f.size > 2 * 1024 * 1024) {

          clearFile('That image is over the 2 MB limit.');

          return;

        }

        name.textContent = f.name;

        preview.src = URL.createObjectURL(f);

        preview.hidden = false;

        url.value = '';

      });

      // Typing a URL replaces a chosen file

      url.addEventListener('input', function () {

        if (url.value && file.files.length) clearFile();

      });

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

  <script>

    // Forms marked data-native-submit must submit to PHP, not prototype handlers in app.js.

    (function () {

      function isNativeForm(e) {

        return e.target && e.target.closest && e.target.closest('form[data-native-submit]');

      }

      document.addEventListener('submit', function (e) {

        if (isNativeForm(e)) e.stopImmediatePropagation();

      }, true);

      document.addEventListener('click', function (e) {

        var btn = e.target && e.target.closest ? e.target.closest('form[data-native-submit] button[type="submit"]') : null;

        if (btn) e.stopImmediatePropagation();

      }, true);

    })();

  </script>

  <script src="js/server-search.js?v=3"></script>

  <script src="js/app.js?v=2"></script>

  <script>

    // Catalog / Pending requests / On loan tabs (URL hash keeps the open tab)

    (function () {

      var tabs = Array.prototype.slice.call(document.querySelectorAll('.tabs [data-tab]'));

      var panels = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));

      function show(name) {

        if (!document.getElementById('tab-' + name)) name = 'catalog';

        tabs.forEach(function (t) {

          var on = t.dataset.tab === name;

          t.classList.toggle('is-active', on);

          t.setAttribute('aria-selected', on ? 'true' : 'false');

          if (on) t.setAttribute('aria-current', 'page'); else t.removeAttribute('aria-current');

        });

        panels.forEach(function (p) { p.hidden = p.id !== 'tab-' + name; });

      }

      tabs.forEach(function (t) {

        t.addEventListener('click', function (e) {

          e.preventDefault();

          show(t.dataset.tab);

          history.replaceState(null, '', location.pathname + location.search + '#' + t.dataset.tab);

        });

      });

      window.addEventListener('hashchange', function () { show(location.hash.slice(1)); });

      show(location.hash.slice(1));

    })();

  </script>

  <script>

    // Print receipt: after Approve, show a receipt the admin can print.

    // Capture phase, so the card's data is read before app.js updates/removes it.

    (function () {

      var LOAN_DAYS = 7; // loan period used for the due date on the receipt

      var dialog = document.getElementById('receipt-dialog');

      function fmt(d) {

        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

      }

      function set(id, text) { document.getElementById(id).textContent = text; }

      var pad = function (n) { return String(n).padStart(2, '0'); };

      document.addEventListener('click', function (e) {

        var btn = e.target.closest && e.target.closest('#tab-requests [data-action="approve"]');

        if (!btn) return;

        var card = btn.closest('.request-card');

        if (!card) return;

        var line = (card.querySelector('.request-card__line') || {}).textContent || '';

        var m = line.match(/Borrower:\s*(.+?)\s***(**#?([^)]+)**)**/);

        var requested = ((card.querySelector('.request-card__date') || {}).textContent || '')

          .replace(/^\s*Requested\s*/i, '').trim();

        var now = new Date();

        var due = new Date(now.getTime() + LOAN_DAYS * 86400000);

        set('rc-no', 'R-' + now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) +

          '-' + pad(now.getHours()) + pad(now.getMinutes()) + pad(now.getSeconds()));

        set('rc-title', card.dataset.title || '');

        set('rc-borrower', m ? m[1] : line);

        set('rc-sid', m ? '#' + m[2] : '');

        set('rc-requested', requested);

        set('rc-approved', fmt(now));

        set('rc-due', fmt(due));

        dialog.showModal();

      }, true);

      document.getElementById('print-receipt').addEventListener('click', function () { window.print(); });

      document.getElementById('close-receipt').addEventListener('click', function () { dialog.close(); });

    })();

  </script>

</body>

</html>