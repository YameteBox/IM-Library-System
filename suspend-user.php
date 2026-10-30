<?php
session_start();

// Admins only (same check as admin-library.php)
if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require 'db.php';
require_once 'search_helpers.php';
require_once 'account_helpers.php';
$adminAcct = adminAccount($pdo, (int) $_SESSION['admin_id']); // latest username from the database
if (!$adminAcct) {
    header('Location: admin-logout.php'); // session points at an admin that no longer exists
    exit;
}

$self = 'suspend-user.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = (string) ($_POST['action'] ?? '');
    $student  = trim((string) ($_POST['student'] ?? ''));
    $adminNo  = trim((string) ($_POST['admin'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');
    $flash    = '';
    $type     = 'error';

    try {
        if (!in_array($action, ['suspend', 'unsuspend'], true)) {
            $flash = 'Invalid request.';
        } elseif ($student === '' || $adminNo === '' || $password === '' || $confirm === '') {
            $flash = 'Please fill in every field.';
        } elseif (!hash_equals($password, $confirm)) {
            $flash = 'The passwords do not match.';
        } else {
            // The person must re-enter THEIR OWN admin ID and password
            $stmt = $pdo->prepare('SELECT admin_number, password_hash FROM admins WHERE id = :id');
            $stmt->execute([':id' => (int) $_SESSION['admin_id']]);
            $me = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$me
                || strcasecmp($adminNo, (string) $me['admin_number']) !== 0
                || !password_verify($password, (string) $me['password_hash'])) {
                $flash = 'Your admin ID or password is incorrect.';
            } else {
                $stmt = $pdo->prepare(
                    'SELECT u.id, u.banned, d.username
                       FROM users u
                       LEFT JOIN user_details d ON d.id = u.id
                      WHERE u.student_number = :sn'
                );
                $stmt->execute([':sn' => $student]);
                $target = $stmt->fetch(PDO::FETCH_ASSOC);
                $name   = (string) (($target['username'] ?? '') !== '' ? $target['username'] : $student);

                if (!$target) {
                    $flash = 'No student found with student number ' . $student . '.';
                } elseif ($action === 'suspend') {
                    if ($target['banned']) {
                        $flash = $name . ' is already suspended.';
                    } else {
                        $pdo->prepare(
                            'UPDATE users SET banned = true, banned_at = NOW(), banned_by = :admin WHERE id = :id'
                        )->execute([':admin' => (int) $_SESSION['admin_id'], ':id' => (int) $target['id']]);
                        $flash = $name . ' (' . $student . ') has been suspended.';
                        $type  = 'success';
                    }
                } else {
                    if (!$target['banned']) {
                        $flash = $name . ' is not suspended.';
                    } else {
                        $pdo->prepare(
                            'UPDATE users SET banned = false, banned_at = NULL, banned_by = NULL WHERE id = :id'
                        )->execute([':id' => (int) $target['id']]);
                        $flash = $name . ' (' . $student . ') has been unsuspended.';
                        $type  = 'success';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log('suspend-user: ' . $e->getMessage());
        $flash = 'Something went wrong. Please try again.';
    }

    $_SESSION['flash_message'] = $flash;
    $_SESSION['flash_type']    = $type;
    if ($type !== 'success') {
        $_SESSION['suspend_prefill'] = $student; // keep what they typed
    }
    $q = trim((string) ($_POST['q'] ?? ''));
    header('Location: ' . $self . ($q !== '' ? '?q=' . rawurlencode($q) : ''));
    exit;
}

$flashMessage = '';
$flashType    = 'success';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message'];
    $flashType    = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
$prefill = (string) ($_SESSION['suspend_prefill'] ?? '');
unset($_SESSION['suspend_prefill']);

$q        = trim((string) ($_GET['q'] ?? ''));
$users    = searchUsers($pdo, $q);
$borrowed = currentlyBorrowedByUsers($pdo, array_column($users, 'student_id'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Suspend / Unsuspend user · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="suspend-user">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library Management — Admin <?= h($adminAcct['username']) ?></h1>
          <span class="role-badge">admin</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="admin-library.php">← Back to admin library</a>
        <a class="nav__link" href="admin-logout.php">Log out</a>
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
    <main class="auth-body" style="display:block">
      <section class="panel panel--lg wide-panel" id="manage-view" aria-labelledby="t">
        <div class="split">
          <div class="form-stack">
            <h2 class="card-title" id="t">Suspend/Unsuspend User</h2>
            <p class="sub">Suspend or restore a student's account by student number.</p>
            <form class="form-stack" id="suspend-form" method="post" action="suspend-user.php" data-native-submit>
              <input type="hidden" name="q" value="<?= h($q) ?>">
              <div class="field">
          <label for="f-student">Student Number</label>
          <input class="input" id="f-student" name="student" type="text" value="<?= h($prefill) ?>" placeholder="2024-00100" required>
        </div>
              <div class="field">
          <label for="f-admin">Admin ID</label>
          <input class="input" id="f-admin" name="admin" type="text" placeholder="A-001" autocomplete="off" required>
        </div>
              <div class="field">
          <label for="f-password">Admin Password</label>
          <input class="input" id="f-password" name="password" type="password" autocomplete="current-password" required>
        </div>
              <div class="field">
          <label for="f-confirm">Confirm password</label>
          <input class="input" id="f-confirm" name="confirm" type="password" autocomplete="current-password" required>
        </div>
              <button class="btn btn--block btn--danger btn--round" type="submit" name="action" value="suspend" id="do-suspend" onclick="return confirm('Suspend this student?');">Suspend User Account</button>
              <button class="btn btn--block btn--success btn--round" type="submit" name="action" value="unsuspend" id="do-unsuspend">Unsuspend User Account</button>
            </form>
          </div>
          <aside class="split__side" aria-label="Find a user">
            <form class="split__search plain-search" id="profile-search" role="search" method="get" action="suspend-user.php" data-server-search>
              <input class="input" name="q" type="search" value="<?= h($q) ?>" placeholder="Search by student number, username, or email..." aria-label="Search users">
              <button class="btn" type="submit">Search</button>
            </form>
            <div class="split__results">
<?php if (empty($users)): ?>
      <p class="sub">No users match your search.</p>
<?php endif; ?>
<?php foreach ($users as $user): ?>
<?= renderUserCard($user, $borrowed[(string) $user['student_id']] ?? [], false) ?>
<?php endforeach; ?>
            </div>
          </aside>
        </div>
      </section>

      <section class="auth-card auth-card--round" id="result-view" aria-labelledby="rt" hidden style="margin-inline:auto">
        <h2 class="card-title" id="rt">Suspend / Unsuspend User</h2>
        <p class="sub">Suspend or restore a student's account by student number.</p>
        <div class="toast toast--success" id="result-alert" role="status">
          <span id="result-text"></span>
          <button class="toast__close" type="button" id="result-close" aria-label="Dismiss message">✕</button>
        </div>
        <p class="sub center-text">Would you like to manage another user?</p>
        <button class="btn btn--block btn--round" type="button" id="manage-another">Manage another user</button>
      </section>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/server-search.js"></script>
  <script>
    // This form must really POST to PHP: keep app.js (prototype) from intercepting it.
    (function () {
      function onForm(e) { return e.target && e.target.closest && e.target.closest('#suspend-form'); }
      document.addEventListener('submit', function (e) { if (onForm(e)) e.stopImmediatePropagation(); }, true);
      document.addEventListener('click', function (e) { if (onForm(e)) e.stopImmediatePropagation(); }, true);
      // Click a user in the search results to fill in the student number
      document.addEventListener('click', function (e) {
        var card = e.target && e.target.closest ? e.target.closest('.user-card[data-student-id]') : null;
        var input = document.getElementById('f-student');
        if (card && input) { input.value = card.getAttribute('data-student-id'); input.focus(); }
      });
    })();
  </script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>