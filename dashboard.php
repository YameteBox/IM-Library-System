<?php
session_start();
require 'db.php';
require_once 'search_helpers.php'; // h(), formatPublishedDate()
require_once 'events_helpers.php';

if (!defined('OVERDUE_FEE_PER_DAY')) {
    define('OVERDUE_FEE_PER_DAY', 5); // pesos, same rate as profile.php
}

// Same fallback library.php uses until real login sessions are in place
$studentId = (string) ($_SESSION['student_id'] ?? '2023-00123');

ensureEventsTable($pdo);
$month     = calendarMonth($_GET['month'] ?? null);
$events    = eventsForMonth($pdo, $month);
$upcoming  = upcomingEvents($pdo, 5);

function dashDaysOverdue(string $dueDate): int
{
    $due   = new DateTime($dueDate);
    $today = new DateTime('today');
    return $today > $due ? (int) $due->diff($today)->days : 0;
}

function dashDaysText(int $n): string
{
    return $n . ' day' . ($n === 1 ? '' : 's');
}

// Approved, not-yet-returned loans (includes the overdue ones)
// TODO (backend): approved, not-yet-returned loans for $studentId, soonest due first.
// Rows: title, authors, due_date.
$borrowed = [];

$overdue = array_values(array_filter($borrowed, function ($loan) {
    return dashDaysOverdue((string) $loan['due_date']) > 0;
}));

// Requests still waiting for the librarian
// TODO (backend): pending loan requests for $studentId, newest first. Rows: title, authors, borrowed_at.
$pending = [];

// ---- Popular & recommended books ------------------------------------------
require_once 'media_types.php';

// Popular: most-borrowed items overall
// TODO (backend): the 5 most-borrowed books (approved loans).
// Rows: id, title, authors, media_type, classification, copies_available, times_borrowed.
$popular = [];

// Recommended: unborrowed items in the classifications this student borrows most,
// most popular first. Falls back to popular items they haven't borrowed yet.
// TODO (backend): the classifications this student borrows most, most-borrowed first (list of strings).
$favClasses = [];

// TODO (backend): up to 100 books with copies available that this student has never borrowed,
// most popular first. Same row shape as $popular.
$candidates = [];

$recommended = [];
foreach ($favClasses as $class) {
    foreach ($candidates as $c) {
        if (count($recommended) < 5 && $c['classification'] === $class && !isset($recommended[$c['id']])) {
            $c['reason'] = 'Because you borrow ' . $class . ' items';
            $recommended[$c['id']] = $c;
        }
    }
}
foreach ($candidates as $c) {
    if (count($recommended) < 5 && !isset($recommended[$c['id']])) {
        $c['reason'] = $favClasses ? 'Popular with other students' : 'Popular in the library';
        $recommended[$c['id']] = $c;
    }
}
$recommended = array_values($recommended);

function dashBookItem(array $b, string $extra): string
{
    $copies = (int) $b['copies_available'];
    return '<li><span class="dash-title">' . h($b['title']) . '</span>'
        . '<span class="dash-sub">' . h($b['authors']) . ' · ' . h(mediaTypeLabel($b['media_type'])) . '</span>'
        . '<span class="dash-sub">' . h($extra) . ' · ' . ($copies > 0 ? $copies . ' available' : 'No copies available') . '</span></li>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <style>
<?= calendarStyles() ?>
    .dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; align-items: start; }
    .dash-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
    .dash-list li { display: flex; flex-direction: column; gap: 2px; padding-bottom: 10px;
      border-bottom: 1px solid color-mix(in srgb, currentColor 15%, transparent); }
    .dash-list li:last-child { border-bottom: 0; padding-bottom: 0; }
    .dash-title { font-weight: 600; }
    .dash-sub { font-size: .85rem; opacity: .75; }
    .dash-late { font-size: .85rem; font-weight: 600; color: #b42318; }
    .dash-count { margin-left: 6px; }
  </style>
</head>
<body data-page="dashboard">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Dashboard — Student #<?= h($studentId) ?></h1>
          <span class="brand__user">maria.santos</span><span class="role-badge">student</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="library.php">← Back to library</a>
        <a class="nav__link" href="search-profiles.php">Search Profiles</a>
        <a class="nav__link" href="my-lists.php">My Lists</a>
        <a class="nav__link" href="profile.php">Profile</a>
        <a class="nav__link" href="login.php">Log out</a>
      </nav>
    </header>
    <div class="toast-region" data-toast-region aria-live="polite"></div>

    <section class="panel panel--lg" aria-labelledby="cal-h" style="display:flex;flex-direction:column;gap:12px">
      <h2 class="section-title" id="cal-h">Events calendar</h2>
      <?= renderCalendar($month, $events, 'dashboard.php') ?>
    </section>

    <section class="panel" aria-labelledby="up-h" style="display:flex;flex-direction:column;gap:12px">
      <h2 class="panel-title" id="up-h">Upcoming events</h2>
<?php if (empty($upcoming)): ?>
      <p class="sub">No upcoming events.</p>
<?php else: ?>
      <ul class="event-list">
<?php foreach ($upcoming as $e): $time = formatEventTime($e['event_time'] ?? null); ?>
        <li>
          <span class="dash-title"><?= h($e['title']) ?></span>
          <span class="event-list__when"><?= h(formatPublishedDate((string) $e['event_date'])) ?><?= $time !== '' ? ' · ' . h($time) : '' ?></span>
<?php if (!empty($e['description'])): ?>
          <span class="event-list__desc"><?= h($e['description']) ?></span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>

    <div class="dash-grid">
      <section class="panel" aria-labelledby="b-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="panel-title" id="b-h">Currently borrowed<span class="pill pill--grey dash-count"><?= count($borrowed) ?></span></h2>
<?php if (empty($borrowed)): ?>
        <p class="sub">You don't have any books borrowed right now.</p>
<?php else: ?>
        <ul class="dash-list">
<?php foreach ($borrowed as $loan): $late = dashDaysOverdue((string) $loan['due_date']); ?>
          <li>
            <span class="dash-title"><?= h($loan['title']) ?></span>
            <span class="dash-sub"><?= h($loan['authors']) ?></span>
            <span class="dash-sub">Due <?= h(formatPublishedDate((string) $loan['due_date'])) ?></span>
<?php if ($late > 0): ?>
            <span class="dash-late">Overdue</span>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

      <section class="panel" aria-labelledby="p-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="panel-title" id="p-h">Pending requests<span class="pill pill--grey dash-count"><?= count($pending) ?></span></h2>
<?php if (empty($pending)): ?>
        <p class="sub">No requests waiting for approval.</p>
<?php else: ?>
        <ul class="dash-list">
<?php foreach ($pending as $loan): ?>
          <li>
            <span class="dash-title"><?= h($loan['title']) ?></span>
            <span class="dash-sub"><?= h($loan['authors']) ?></span>
            <span class="dash-sub">Requested <?= h(formatPublishedDate((string) $loan['borrowed_at'])) ?> · awaiting approval</span>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

      <section class="panel" aria-labelledby="o-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="panel-title" id="o-h">Overdue books<span class="pill pill--grey dash-count"><?= count($overdue) ?></span></h2>
<?php if (empty($overdue)): ?>
        <p class="sub">No overdue books. You're all caught up!</p>
<?php else: ?>
        <ul class="dash-list">
<?php foreach ($overdue as $loan): $late = dashDaysOverdue((string) $loan['due_date']); ?>
          <li>
            <span class="dash-title"><?= h($loan['title']) ?></span>
            <span class="dash-sub"><?= h($loan['authors']) ?></span>
            <span class="dash-sub">Was due <?= h(formatPublishedDate((string) $loan['due_date'])) ?></span>
            <span class="dash-late"><?= dashDaysText($late) ?> overdue — ₱<?= number_format($late * OVERDUE_FEE_PER_DAY, 2) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>
    </div>

    <div class="dash-grid">
      <section class="panel" aria-labelledby="pop-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="panel-title" id="pop-h">Popular books</h2>
<?php if (empty($popular)): ?>
        <p class="sub">No borrowing activity yet. Popular books will show up here.</p>
<?php else: ?>
        <ul class="dash-list">
<?php foreach ($popular as $b): ?>
          <?= dashBookItem($b, 'Borrowed ' . (int) $b['times_borrowed'] . ' time' . ((int) $b['times_borrowed'] === 1 ? '' : 's')) ?>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

      <section class="panel" aria-labelledby="rec-h" style="display:flex;flex-direction:column;gap:12px">
        <h2 class="panel-title" id="rec-h">Recommended for you</h2>
<?php if (empty($recommended)): ?>
        <p class="sub">Nothing to recommend yet. Borrow a few books and check back.</p>
<?php else: ?>
        <ul class="dash-list">
<?php foreach ($recommended as $b): ?>
          <?= dashBookItem($b, $b['reason']) ?>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
        <a class="btn btn--outline btn--sm" href="library.php">Browse the library</a>
      </section>
    </div>

    <footer class="site-footer">
      <div class="site-footer__links"><a href="#">About Us</a><span aria-hidden="true">·</span><a href="#">Mission and Vision</a><span aria-hidden="true">·</span><a href="#">Contact Us</a></div>
      <div>© 2026 Manila Science High School Online Library</div>
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
