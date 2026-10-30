<?php
session_start();
require 'db.php';
require_once 'media_types.php';
require_once 'search_helpers.php';
require_once 'lists_helpers.php';
ensureCoverColumn($pdo);

// Must be logged in (login.php sets these session values)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];

$listId = (int) ($_GET['id'] ?? $_POST['list_id'] ?? 0);
$list   = findUserList($pdo, $userId, $listId);
if (!$list) {
    $_SESSION['flash_message'] = 'That list could not be found.';
    $_SESSION['flash_type']    = 'error';
    header('Location: my-lists.php');
    exit;
}
$self = 'list-view.php?id=' . $listId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $goto   = $self;

    if ($action === 'remove_book') {
        $result = removeBookFromList($pdo, $userId, $listId, (int) ($_POST['book_id'] ?? 0));
    } elseif ($action === 'delete_list') {
        $result = deleteList($pdo, $userId, $listId);
        if ($result['ok']) {
            $goto = 'my-lists.php';
        }
    } else {
        $result = listResult(false, 'Invalid request.');
    }

    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_type']    = $result['ok'] ? 'success' : 'error';
    header('Location: ' . $goto);
    exit;
}

$flashMessage = '';
$flashType    = 'success';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType    = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$books    = listBooks($pdo, $listId);
$isBanned = userIsBanned($pdo, $userId);

// Books this student already has a pending request for
$stmt = $pdo->prepare(
    "SELECT book_id FROM borrowed_books
      WHERE user_id = :uid AND status = 'pending' AND returned_at IS NULL"
);
$stmt->execute([':uid' => $userId]);
$pendingBookIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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
          <h1 class="brand__title">List View — <?= h($list['name']) ?></h1>
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
    <div class="toast-region" data-toast-region aria-live="polite">
      <?php if ($flashMessage !== ''): ?>
        <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
          <?= h($flashMessage) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($isBanned): ?><?= bannedNoticeHtml() ?><?php endif; ?>
    <h2 class="section-title">Books in this list (<span id="list-count"><?= count($books) ?></span>)</h2>
    <div class="book-grid" id="list-books">
<?php if (empty($books)): ?>
      <p class="sub">This list is empty. Use "Add to list" on a book in the library.</p>
<?php endif; ?>
<?php foreach ($books as $book):
        $bookId     = (int) $book['id'];
        $copiesLeft = (int) $book['copies_available'];
        $typeLabel  = mediaTypeLabel($book['media_type']);
        $pillClass  = mediaTypePillClass($book['media_type']);
        $keywords   = array_filter(array_map('trim', explode(',', (string) ($book['key_word'] ?? ''))));
        $isPending  = in_array($bookId, $pendingBookIds, true);
        $meta       = [formatPublishedDate((string) $book['published_date'])];
        if (!empty($book['accession_number'])) { $meta[] = 'Accession ' . $book['accession_number']; }
        if (!empty($book['isbn']))             { $meta[] = 'ISBN ' . $book['isbn']; }
        if (!empty($book['shelf_number']))     { $meta[] = 'Shelf ' . $book['shelf_number']; }
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
            <form method="post" action="<?= h($self) ?>" data-native-submit>
              <input type="hidden" name="action" value="remove_book">
              <input type="hidden" name="book_id" value="<?= $bookId ?>">
              <button class="btn btn--block btn--danger" type="submit">Remove from list</button>
            </form>
          </div>
        </article>
<?php endforeach; ?>
    </div>
    <section class="panel" style="display:flex;flex-direction:column;gap:12px;align-items:flex-start">
      <p class="sub" style="font-size:13px">Deleting a list removes it and its entries permanently.</p>
      <form method="post" action="<?= h($self) ?>" data-native-submit>
        <input type="hidden" name="action" value="delete_list">
        <button class="btn btn--round btn--danger" type="submit" onclick="return confirm('Delete this list and its entries?');">Delete this list</button>
      </form>
    </section>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/borrow.js"></script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>