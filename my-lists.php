<?php
session_start();
require 'db.php';
require_once 'search_helpers.php'; // h()
require_once 'lists_helpers.php';

// Must be logged in (login.php sets these session values)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId        = (int) $_SESSION['user_id'];
$studentNumber = (string) ($_SESSION['student_number'] ?? '');
$username      = (string) ($_SESSION['username'] ?? $studentNumber);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $listId = (int) ($_POST['list_id'] ?? 0);

    if ($action === 'create_list') {
        $result = createList($pdo, $userId, (string) ($_POST['name'] ?? ''));
    } elseif ($action === 'rename_list') {
        $result = renameList($pdo, $userId, $listId, (string) ($_POST['name'] ?? ''));
    } elseif ($action === 'delete_list') {
        $result = deleteList($pdo, $userId, $listId);
    } else {
        $result = listResult(false, 'Invalid request.');
    }

    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_type']    = $result['ok'] ? 'success' : 'error';
    header('Location: my-lists.php');
    exit;
}

$flashMessage = '';
$flashType    = 'success';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType    = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$lists = userListsWithCounts($pdo, $userId);
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
          <h1 class="brand__title">My Lists — Student #<?= h($studentNumber) ?></h1>
          <span class="brand__user"><?= h($username) ?></span><span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="library.php">← Back to library</a>
        <a class="nav__link" href="profile.php">Profile</a>
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
    <form class="panel" id="create-form" method="post" action="my-lists.php" data-native-submit>
      <input type="hidden" name="action" value="create_list">
      <div class="create-row">
        <input class="input" name="name" placeholder="New list name" aria-label="New list name" maxlength="<?= LIST_NAME_MAX ?>" required>
        <button class="btn" type="submit">Create list</button>
      </div>
    </form>
    <section style="display:flex;flex-direction:column;gap:16px" aria-labelledby="yl">
      <h2 class="section-title" id="yl">Your lists</h2>
      <div class="list-grid" id="list-grid">
<?php if (empty($lists)): ?>
        <p class="sub">You don't have any lists yet. Create one above, then use "Add to list" in the library.</p>
<?php endif; ?>
<?php foreach ($lists as $list): $n = (int) $list['item_count']; ?>
        <article class="list-card">
          <div class="list-card__head"><h3 class="list-card__name"><?= h($list['name']) ?></h3><span class="pill pill--grey"><?= $n ?> item<?= $n === 1 ? '' : 's' ?></span></div>
          <a class="btn btn--block" href="list-view.php?id=<?= (int) $list['id'] ?>">View / edit list</a>
          <form class="rename-row" method="post" action="my-lists.php" data-native-submit>
            <input type="hidden" name="action" value="rename_list">
            <input type="hidden" name="list_id" value="<?= (int) $list['id'] ?>">
            <input class="input" name="name" value="<?= h($list['name']) ?>" aria-label="List name" maxlength="<?= LIST_NAME_MAX ?>" required>
            <button class="btn" type="submit">Rename</button>
          </form>
          <form method="post" action="my-lists.php" data-native-submit>
            <input type="hidden" name="action" value="delete_list">
            <input type="hidden" name="list_id" value="<?= (int) $list['id'] ?>">
            <button class="btn btn--block btn--danger" type="submit" onclick="return confirm('Delete this list and its entries?');">Delete list</button>
          </form>
        </article>
<?php endforeach; ?>
      </div>
    </section>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>