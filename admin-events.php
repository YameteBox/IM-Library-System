<?php
session_start();

// Admins only (same check as admin-library.php), now that this page writes to the database
if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

require 'db.php';
require_once 'search_helpers.php'; // h(), formatPublishedDate()
require_once 'events_helpers.php';
ensureEventsTable($pdo);

$month = calendarMonth($_GET['month'] ?? $_POST['month'] ?? null);
$self  = 'admin-events.php?month=' . $month->format('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = (string) ($_POST['action'] ?? '');
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $flash   = '';
    $type    = 'error';
    $goto    = $self;

    if ($action === 'add_event' || $action === 'edit_event') {
        [$data, $error] = validateEventInput($_POST);

        if ($error !== null) {
            $flash = $error;
            // Keep the admin on the form they were filling in
            if ($action === 'edit_event' && $eventId > 0) {
                $goto = $self . '&edit=' . $eventId;
            }
        } elseif ($action === 'add_event') {
            try {
                addEvent($pdo, $data);
                $flash = 'Added "' . $data['title'] . '".';
                $type  = 'success';
                // Show the month the new event landed in
                $goto = 'admin-events.php?month=' . substr($data['event_date'], 0, 7);
            } catch (PDOException $e) {
                $flash = 'Something went wrong adding the event. Please try again.';
            }
        } else {
            try {
                $updated = updateEvent($pdo, $eventId, $data);
                if (!$updated) {
                    $flash = 'That event could not be found.';
                } else {
                    $flash = 'Updated "' . $data['title'] . '".';
                    $type  = 'success';
                    $goto  = 'admin-events.php?month=' . substr($data['event_date'], 0, 7);
                }
            } catch (PDOException $e) {
                $flash = 'Something went wrong updating the event. Please try again.';
            }
        }
    } elseif ($action === 'delete_event') {
        try {
            $deleted = deleteEvent($pdo, $eventId);
            if (!$deleted) {
                $flash = 'That event could not be found.';
            } else {
                $flash = 'Event removed.';
                $type  = 'success';
            }
        } catch (PDOException $e) {
            $flash = 'Something went wrong removing the event. Please try again.';
        }
    }

    if ($flash !== '') {
        $_SESSION['flash_message'] = $flash;
        $_SESSION['flash_type']    = $type;
    }
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

// Event being edited (if any)
$editing = null;
if (isset($_GET['edit'])) {
    $editing = findEvent($pdo, (int) $_GET['edit']);
}

$events = eventsForMonth($pdo, $month);

$formDate = $editing ? (string) $editing['event_date'] : $month->format('Y-m') . '-01';
$formTime = $editing && $editing['event_time'] ? substr((string) $editing['event_time'], 0, 5) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calendar events · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <style>
<?= calendarStyles() ?>
    .event-admin-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .event-admin-row form { margin: 0; }
    .event-admin-row__text { flex: 1; min-width: 180px; display: flex; flex-direction: column; gap: 2px; }
    textarea.input { min-height: 80px; resize: vertical; font: inherit; }
  </style>
</head>
<body data-page="admin-events">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Calendar events — Admin #A-001</h1>
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

    <section class="panel panel--lg" aria-labelledby="cal-h" style="display:flex;flex-direction:column;gap:12px">
      <h2 class="section-title" id="cal-h">Events calendar</h2>
      <p class="sub">Select an event on the calendar to edit it. Students see changes on their dashboard right away.</p>
      <?= renderCalendar($month, $events, 'admin-events.php', 'admin-events.php') ?>
    </section>

    <form class="panel panel--lg form-stack" id="event-form" method="post" action="<?= h($self) ?>" data-native-submit>
      <h2 class="panel-title"><?= $editing ? 'Edit event' : 'Add an event' ?></h2>
      <input type="hidden" name="action" value="<?= $editing ? 'edit_event' : 'add_event' ?>">
      <input type="hidden" name="month" value="<?= h($month->format('Y-m')) ?>">
<?php if ($editing): ?>
      <input type="hidden" name="event_id" value="<?= (int) $editing['id'] ?>">
<?php endif; ?>
      <div class="field">
        <label for="ev-title">Title</label>
        <input class="input" id="ev-title" name="title" type="text" maxlength="<?= EVENT_TITLE_MAX ?>" value="<?= h($editing['title'] ?? '') ?>" required>
      </div>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label for="ev-date">Date</label>
          <input class="input" id="ev-date" name="event_date" type="date" value="<?= h($formDate) ?>" required>
        </div>
        <div class="field">
          <label for="ev-time">Time (optional)</label>
          <input class="input" id="ev-time" name="event_time" type="time" value="<?= h($formTime) ?>">
        </div>
      </div>
      <div class="field">
        <label for="ev-desc">Description (optional)</label>
        <textarea class="input" id="ev-desc" name="description" maxlength="<?= EVENT_DESC_MAX ?>"><?= h($editing['description'] ?? '') ?></textarea>
      </div>
      <div class="actions">
        <button class="btn btn--round" type="submit"><?= $editing ? 'Save changes' : 'Add event' ?></button>
<?php if ($editing): ?>
        <a class="btn btn--round btn--outline" href="<?= h($self) ?>">Cancel</a>
<?php endif; ?>
      </div>
    </form>

    <section class="panel panel--lg" aria-labelledby="list-h" style="display:flex;flex-direction:column;gap:12px">
      <h2 class="panel-title" id="list-h">Events in <?= h($month->format('F Y')) ?></h2>
<?php if (empty($events)): ?>
      <p class="sub">No events this month.</p>
<?php else: ?>
      <ul class="event-list">
<?php foreach ($events as $e): $time = formatEventTime($e['event_time'] ?? null); ?>
        <li class="event-admin-row">
          <div class="event-admin-row__text">
            <span class="dash-title"><strong><?= h($e['title']) ?></strong></span>
            <span class="event-list__when"><?= h(formatPublishedDate((string) $e['event_date'])) ?><?= $time !== '' ? ' · ' . h($time) : '' ?></span>
<?php if (!empty($e['description'])): ?>
            <span class="event-list__desc"><?= h($e['description']) ?></span>
<?php endif; ?>
          </div>
          <a class="btn btn--outline btn--sm" href="<?= h($self . '&edit=' . (int) $e['id']) ?>#event-form">Edit</a>
          <form method="post" action="<?= h($self) ?>" data-native-submit onsubmit="return confirm('Remove this event?');">
            <input type="hidden" name="action" value="delete_event">
            <input type="hidden" name="month" value="<?= h($month->format('Y-m')) ?>">
            <input type="hidden" name="event_id" value="<?= (int) $e['id'] ?>">
            <button class="btn btn--danger btn--sm" type="submit">Delete</button>
          </form>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script>
    // These forms must really POST to PHP: keep app.js from intercepting their submits.
    document.addEventListener('submit', function (e) { e.stopImmediatePropagation(); }, true);
  </script>
  <script src="js/app.js?v=2"></script>
</body>
</html>
