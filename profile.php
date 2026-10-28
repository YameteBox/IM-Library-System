<?php
session_start();
require 'db.php';
require_once 'search_helpers.php'; // h(), formatPublishedDate()

const OVERDUE_FEE_PER_DAY = 5; // pesos

// Must be logged in (login.php sets these session values); users.id is an integer
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];

// This student's details for the header, avatar and forms
$stmt = $pdo->prepare(
    'SELECT u.student_number, d.username, d.email, p.bio
       FROM users u
       LEFT JOIN user_details  d ON d.id = u.id
       LEFT JOIN user_profiles p ON p.id = u.id
      WHERE u.id = :id'
);
$stmt->execute([':id' => $userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    header('Location: logout.php'); // session points at a user that no longer exists
    exit;
}
$studentNumber = (string) $me['student_number'];
$username      = (string) ($me['username'] ?? $studentNumber);
$email         = (string) ($me['email'] ?? '');
$bio           = (string) ($me['bio'] ?? '');

/** Days past the due date x the daily fee (0 when not overdue). */
function loanOverdueFee(string $dueDate): float
{
    $due   = new DateTime($dueDate);
    $today = new DateTime('today');
    if ($today <= $due) {
        return 0.0;
    }
    return (int) $due->diff($today)->days * OVERDUE_FEE_PER_DAY;
}

/**
 * Numbers behind the due-date progress bar: how far along the loan is,
 * days left (negative once overdue) and a status bucket for the colour.
 */
function loanTrackerData(?string $borrowedAt, string $dueDate): array
{
    $due   = new DateTime($dueDate);
    $today = new DateTime('today');
    $start = new DateTime($borrowedAt ?: $dueDate);
    $start->setTime(0, 0);

    $totalDays   = max((int) $start->diff($due)->days, 1); // guard against same-day loans
    $elapsedDays = $today < $start ? 0 : (int) $start->diff($today)->days;
    $percent     = min(100, max(0, (int) round(($elapsedDays / $totalDays) * 100)));
    $daysLeft    = (int) $today->diff($due)->format('%r%a');

    $status = $daysLeft < 0 ? 'overdue' : ($daysLeft <= 2 ? 'due-soon' : 'on-track');

    return ['percent' => $percent, 'days_left' => $daysLeft, 'status' => $status];
}

function loanDaysText(int $n): string
{
    return abs($n) . ' day' . (abs($n) === 1 ? '' : 's');
}

// Approved, not-yet-returned loans for this student, soonest due first
$stmt = $pdo->prepare(
    "SELECT bk.title, bk.authors, bb.due_date, bb.borrowed_at
       FROM borrowed_books bb
       JOIN books bk ON bk.id = bb.book_id
      WHERE bb.user_id = :uid AND bb.status = 'approved' AND bb.returned_at IS NULL
      ORDER BY bb.due_date, bb.id"
);
$stmt->execute([':uid' => $userId]);
$activeLoans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Placeholder books so the tabs have something to show while there are no real loans.
// Set to false (or delete this block) once real data is in the database.
const SHOW_PLACEHOLDER_LOANS = false;
if (SHOW_PLACEHOLDER_LOANS && empty($activeLoans)) {
    // [title, authors, days until due (negative = overdue)]
    $samples = [
        ['Cosmos',                  'Carl Sagan',       4],
        ['Noli Me Tángere',         'José Rizal',       1],
        ['Catan',                   'Klaus Teuber',    -6],
        ['A Brief History of Time', 'Stephen Hawking', -2],
    ];
    foreach ($samples as [$title, $authors, $daysUntilDue]) {
        $due = new DateTime('today');
        $due->modify(($daysUntilDue >= 0 ? '+' : '') . $daysUntilDue . ' days');
        $borrowed = clone $due;
        $borrowed->modify('-14 days');
        $activeLoans[] = [
            'title'       => $title,
            'authors'     => $authors,
            'due_date'    => $due->format('Y-m-d'),
            'borrowed_at' => $borrowed->format('Y-m-d'),
        ];
    }
}
$overdueLoans = array_values(array_filter($activeLoans, function ($loan) {
    return loanOverdueFee((string) $loan['due_date']) > 0;
}));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="profile">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">My Profile — Student #<?= h($studentNumber) ?></h1>
          <span class="brand__user"><?= h($username) ?></span><span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="library.php">← Back to library</a>
        <a class="nav__link" href="my-lists.php">My Lists</a>
        <a class="nav__link" href="dashboard.php">Dashboard</a>
        <a class="nav__link" href="logout.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>
    <section class="panel">
      <div class="profile-head">
        <div class="avatar " aria-hidden="true"><?= h(userInitials($username)) ?></div>
        <div>
          <h2 class="profile-name"><?= h($username) ?></h2>
          <dl class="kv"><dt>Student No.</dt><dd><?= h($studentNumber) ?></dd><dt>Email</dt><dd><?= h($email) ?></dd><dt>Bio</dt><dd><?= h($bio) ?></dd></dl>
        </div>
      </div>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="cp">
      <h2 class="panel-title" id="cp">Customize profile</h2>
      <div class="toggle-row"><button class="switch" id="dark-switch" type="button" role="switch" aria-checked="false" aria-label="Dark mode"></button><span>Dark mode</span></div>
      <div class="field"><label for="bio">Bio</label><input class="input" id="bio" value="<?= h($bio) ?>"></div>
        <div class="choice-row">
          <label class="choice"><input type="radio" name="color-mode" value="solid" checked> Solid color</label>
          <label class="choice"><input type="radio" name="color-mode" value="gradient"> Gradient</label>
        </div>
        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <input type="color" id="color-1" name="color1" value="#1e3a8a" aria-label="Profile colour" style="width:52px;height:38px;padding:2px;cursor:pointer">
          <input type="color" id="color-2" name="color2" value="#3b82f6" aria-label="Gradient second colour" style="width:52px;height:38px;padding:2px;cursor:pointer" hidden>
        </div>
        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <label for="color-text">Initials text color</label>
          <input type="color" id="color-text" name="color_text" value="#ffffff" style="width:52px;height:38px;padding:2px;cursor:pointer">
        </div>
      <div><button class="btn btn--round" type="button" data-save>Save profile</button></div>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="as">
      <h2 class="panel-title" id="as">Account settings</h2>
      <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username" type="text" value="<?= h($username) ?>" placeholder="" >
        </div>
      <div class="field">
          <label for="f-current">Current Password</label>
          <input class="input" id="f-current" name="current" type="password" placeholder="Enter password to save account changes.">
        </div>
      <div class="field">
          <label for="f-new">New Password</label>
          <input class="input" id="f-new" name="new" type="password" placeholder="Leave this blank if not changing password.">
        </div>
      <div class="field">
          <label for="f-confirm">Confirm New Password</label>
          <input class="input" id="f-confirm" name="confirm" type="password" >
        </div>
        <label class="choice"><input type="checkbox" checked> Show my borrowed books to other students</label>
      <div><button class="btn btn--round" type="button" data-save>Save Account Settings</button></div>
    </section>
    <section class="stack" aria-labelledby="bh">
      <h2 class="sr-only" id="bh" style="position:absolute;left:-9999px">Borrowed books</h2>
      <div class="tabs loan-tabs" role="tablist">
        <button class="tab is-active" type="button" role="tab" id="loan-tab-current" aria-selected="true" aria-controls="loan-panel-current" data-loan-tab="current">Borrowed books<span class="tab-count"><?= count($activeLoans) ?></span></button>
        <button class="tab" type="button" role="tab" id="loan-tab-overdue" aria-selected="false" aria-controls="loan-panel-overdue" data-loan-tab="overdue">Overdue books<span class="tab-count"><?= count($overdueLoans) ?></span></button>
      </div>

      <div class="loan-list" id="loan-panel-current" role="tabpanel" aria-labelledby="loan-tab-current">
<?php if (empty($activeLoans)): ?>
        <p class="panel loan-empty">You don't have any books borrowed right now.</p>
<?php else: foreach ($activeLoans as $loan):
        $t         = loanTrackerData($loan['borrowed_at'] ?? null, (string) $loan['due_date']);
        $fee       = loanOverdueFee((string) $loan['due_date']);
        $authors   = implode(', ', array_map('trim', explode(',', (string) $loan['authors'])));
?>
        <div class="panel loan-card">
          <div>
            <div class="loan-title"><?= h($loan['title']) ?></div>
            <div class="loan-author"><?= h($authors) ?></div>
          </div>
          <div>
            <div class="loan-meta">
              <span>Due <?= h(formatPublishedDate((string) $loan['due_date'])) ?></span>
              <?php if ($fee > 0): ?><span class="loan-badge">Overdue — ₱<?= number_format($fee, 2) ?></span><?php endif; ?>
            </div>
            <div class="loan-bar" role="progressbar" aria-label="Loan progress" aria-valuenow="<?= $t['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="loan-bar__fill loan-bar__fill--<?= h($t['status']) ?>" style="width:<?= $t['percent'] ?>%"></div>
            </div>
            <div class="loan-status loan-status--<?= h($t['status']) ?>">
              <?php if ($t['days_left'] < 0): ?><?= loanDaysText($t['days_left']) ?> overdue
              <?php elseif ($t['days_left'] === 0): ?>Due today
              <?php else: ?><?= loanDaysText($t['days_left']) ?> left<?php endif; ?>
            </div>
          </div>
        </div>
<?php endforeach; endif; ?>
      </div>

      <div class="loan-list" id="loan-panel-overdue" role="tabpanel" aria-labelledby="loan-tab-overdue" hidden>
<?php if (empty($overdueLoans)): ?>
        <p class="panel loan-empty">No overdue books. You're all caught up!</p>
<?php else: foreach ($overdueLoans as $loan):
        $fee     = loanOverdueFee((string) $loan['due_date']);
        $t       = loanTrackerData($loan['borrowed_at'] ?? null, (string) $loan['due_date']);
        $authors = implode(', ', array_map('trim', explode(',', (string) $loan['authors'])));
?>
        <div class="panel loan-card">
          <div>
            <div class="loan-title"><?= h($loan['title']) ?></div>
            <div class="loan-author"><?= h($authors) ?></div>
          </div>
          <div class="loan-side--end">
            <div class="loan-meta">
              <span>Due <?= h(formatPublishedDate((string) $loan['due_date'])) ?></span>
              <span class="loan-badge">Overdue — ₱<?= number_format($fee, 2) ?></span>
            </div>
            <div class="loan-status loan-status--overdue"><?= loanDaysText($t['days_left']) ?> overdue (₱<?= OVERDUE_FEE_PER_DAY ?>/day)</div>
          </div>
        </div>
<?php endforeach; endif; ?>
      </div>
    </section>
  </div>

  <script>
    // Borrowed / overdue tabs
    (function () {
      var tabs = document.querySelectorAll('[data-loan-tab]');
      function show(name) {
        tabs.forEach(function (t) {
          var on = t.getAttribute('data-loan-tab') === name;
          t.classList.toggle('is-active', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
          document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
      }
      tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.getAttribute('data-loan-tab')); });
      });
    })();
  </script>
  <script>
    // Profile colour: one picker for solid, two for gradient
    (function () {
      var c1 = document.getElementById('color-1'), c2 = document.getElementById('color-2');
      var avatar = document.querySelector('.profile-head .avatar');
      var radios = document.querySelectorAll('input[name="color-mode"]');
      function isGradient() { return document.querySelector('input[name="color-mode"]:checked').value === 'gradient'; }
      function toggle() { c2.hidden = !isGradient(); }
      function preview() {
        toggle();
        avatar.style.background = isGradient()
          ? 'linear-gradient(135deg,' + c1.value + ',' + c2.value + ')'
          : c1.value;
      }
      radios.forEach(function (r) { r.addEventListener('change', preview); });
      c1.addEventListener('input', preview);
      c2.addEventListener('input', preview);
      var ct = document.getElementById('color-text');
      ct.addEventListener('input', function () { avatar.style.color = ct.value; });
      toggle();
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>