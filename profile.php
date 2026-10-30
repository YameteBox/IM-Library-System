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

// CSRF protection
if (empty($_SESSION['user_profile_csrf'])) {
    $_SESSION['user_profile_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['user_profile_csrf'];

$errors  = [];
$success = '';

// One-time message carried over from a successful save (post/redirect/get)
if (!empty($_SESSION['user_profile_flash'])) {
    $success = (string) $_SESSION['user_profile_flash'];
    unset($_SESSION['user_profile_flash']);
}

function validProfileColor($value): bool
{
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

/** Load everything shown on this page for one student. */
function loadStudent(PDO $pdo, int $userId)
{
    $stmt = $pdo->prepare(
        'SELECT u.student_number, u.password_hash,
                d.username, d.email,
                p.bio, p.color, p.use_gradient, p.gradient_color_2,
                p.text_color, p.show_borrowed_books
           FROM users u
           LEFT JOIN user_details  d ON d.id = u.id
           LEFT JOIN user_profiles p ON p.id = u.id
          WHERE u.id = :id'
    );
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Make sure this student has detail and profile rows (never overwrites existing ones)
try {
    $stmt = $pdo->prepare('SELECT student_number FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $studentNo = $stmt->fetchColumn();
    if ($studentNo === false) {
        header('Location: logout.php'); // session points at a user that no longer exists
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'INSERT INTO user_details (id, username, email)
         VALUES (:id, :username, :email)
         ON CONFLICT (id) DO NOTHING'
    );
    $stmt->execute([
        ':id'       => $userId,
        ':username' => substr((string) $studentNo, 0, 30),
        ':email'    => 'user' . $userId . '@invalid.local',
    ]);
    $stmt = $pdo->prepare('INSERT INTO user_profiles (id) VALUES (:id) ON CONFLICT (id) DO NOTHING');
    $stmt->execute([':id' => $userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Student profile setup error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load your profile. Please try again later.');
}

// ---------- Handle form submissions ----------
$posted = []; // values to re-show in the forms if a save fails
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Your session expired or the request was invalid. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'save_profile') {
                $bio         = trim((string) ($_POST['bio'] ?? ''));
                $color       = (string) ($_POST['color1'] ?? '#1E3A8A');
                $color2      = (string) ($_POST['color2'] ?? '#3B82F6');
                $textColor   = (string) ($_POST['color_text'] ?? '#FFFFFF');
                $useGradient = ($_POST['color_mode'] ?? 'solid') === 'gradient';
                $posted      = ['bio' => $bio];

                if (mb_strlen($bio) > 280) {
                    $errors[] = 'Bio must not exceed 280 characters.';
                }
                if (!validProfileColor($color)
                    || !validProfileColor($textColor)
                    || ($useGradient && !validProfileColor($color2))) {
                    $errors[] = 'Please choose valid profile colors.';
                }

                if (!$errors) {
                    $stmt = $pdo->prepare(
                        'UPDATE user_profiles
                            SET bio = :bio,
                                color = :color,
                                use_gradient = :use_gradient,
                                gradient_color_2 = :color2,
                                text_color = :text_color
                          WHERE id = :id'
                    );
                    $stmt->bindValue(':bio', $bio);
                    $stmt->bindValue(':color', $color);
                    $stmt->bindValue(':use_gradient', $useGradient ? 'true' : 'false');
                    $stmt->bindValue(':color2', $useGradient ? $color2 : null, $useGradient ? PDO::PARAM_STR : PDO::PARAM_NULL);
                    $stmt->bindValue(':text_color', $textColor);
                    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
                    $stmt->execute();

                    $_SESSION['user_profile_flash'] = 'Profile customization saved successfully.';
                    header('Location: profile.php');
                    exit;
                }

            } elseif ($action === 'save_account') {
                $newUsername     = trim((string) ($_POST['username'] ?? ''));
                $newUsername = preg_replace('/\s+/u', ' ', $newUsername) ?? $newUsername; // single spaces only
                $currentPassword = (string) ($_POST['current'] ?? '');
                $newPassword     = (string) ($_POST['new'] ?? '');
                $confirmPassword = (string) ($_POST['confirm'] ?? '');
                $showBorrowed    = isset($_POST['show_borrowed_books']);
                $posted          = ['username' => $newUsername, 'show_borrowed_books' => $showBorrowed];

                if ($newUsername === '' || mb_strlen($newUsername) > 30) {
                    $errors[] = 'Username must contain 1–30 characters.';
                } elseif (!preg_match('/^[\p{L}\p{N}_. -]+$/u', $newUsername)) {
                    $errors[] = 'Username may contain letters, numbers, spaces, underscores, periods, and hyphens only.';
                }

                $changingPassword = ($newPassword !== '' || $confirmPassword !== '');
                if ($changingPassword) {
                    if ($newPassword === '' || $confirmPassword === '') {
                        $errors[] = 'Enter and confirm your new password.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $errors[] = 'The new password and confirmation do not match.';
                    } elseif (strlen($newPassword) < 8) {
                        $errors[] = 'New password must be at least 8 characters long.';
                    } elseif (strlen($newPassword) > 4096) {
                        $errors[] = 'New password is too long.';
                    }
                }

                if (!$errors) {
                    $pdo->beginTransaction();

                    // Lock this student's row and read the stored hash + current username
                    $stmt = $pdo->prepare(
                        'SELECT u.password_hash, d.username
                           FROM users u
                           JOIN user_details d ON d.id = u.id
                          WHERE u.id = :id
                            FOR UPDATE OF u'
                    );
                    $stmt->execute([':id' => $userId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        throw new RuntimeException('Student account no longer exists.');
                    }

                    // Changing the username or password needs the current password
                    $usernameChanged = ($newUsername !== (string) $row['username']);
                    $needsPassword   = $usernameChanged || $changingPassword;

                    if ($needsPassword && $currentPassword === '') {
                        $pdo->rollBack();
                        $errors[] = 'Enter your current password to change your username or password.';
                    } elseif ($needsPassword && !password_verify($currentPassword, (string) $row['password_hash'])) {
                        $pdo->rollBack();
                        $errors[] = 'Your current password is incorrect.';
                    } else {
                        if ($usernameChanged) {
                            $stmt = $pdo->prepare('UPDATE user_details SET username = :username WHERE id = :id');
                            $stmt->execute([':username' => $newUsername, ':id' => $userId]);
                        }

                        if ($changingPassword) {
                            $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                            $stmt->execute([
                                ':hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                                ':id'   => $userId,
                            ]);
                        }

                        $stmt = $pdo->prepare('UPDATE user_profiles SET show_borrowed_books = :show WHERE id = :id');
                        $stmt->bindValue(':show', $showBorrowed ? 'true' : 'false');
                        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
                        $stmt->execute();

                        $pdo->commit();

                        if ($changingPassword) {
                            session_regenerate_id(true); // new session id after a credential change
                        }
                        $_SESSION['user_profile_flash'] = 'Account settings saved successfully.';
                        header('Location: profile.php');
                        exit;
                    }
                }

            } else {
                $errors[] = 'Invalid form action.';
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23505') {
                $errors[] = 'That username is already in use. Please choose another.';
            } else {
                error_log('Student profile update error: ' . $e->getMessage());
                $errors[] = 'Unable to save your changes. Please try again.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student profile error: ' . $e->getMessage());
            $errors[] = 'An unexpected error occurred. Please try again.';
        }
    }
}

// Current saved values (loaded after any save so the page always reflects the database)
$me = loadStudent($pdo, $userId);
if (!$me) {
    header('Location: logout.php');
    exit;
}
$studentNumber = (string) $me['student_number'];
$username      = (string) ($me['username'] ?? $studentNumber);
$email         = (string) ($me['email'] ?? '');
$bio           = (string) ($me['bio'] ?? '');
$_SESSION['student_number'] = $studentNumber; // keep other pages in step
$_SESSION['username']       = $username;

$useGradient   = filter_var($me['use_gradient'], FILTER_VALIDATE_BOOLEAN);
$profileColor  = validProfileColor($me['color'] ?? null)            ? $me['color']            : '#1E3A8A';
$gradientColor = validProfileColor($me['gradient_color_2'] ?? null) ? $me['gradient_color_2'] : '#3B82F6';
$textColor     = validProfileColor($me['text_color'] ?? null)       ? $me['text_color']       : '#FFFFFF';
$showBorrowed  = $me['show_borrowed_books'] === null
    ? true
    : filter_var($me['show_borrowed_books'], FILTER_VALIDATE_BOOLEAN);
$avatarBackground = $useGradient
    ? "linear-gradient(135deg, {$profileColor}, {$gradientColor})"
    : $profileColor;

// What the forms should show: the failed submission if there was one, else the saved values
$formBio      = $posted['bio']      ?? $bio;
$formUsername = $posted['username'] ?? $username;
$formShow     = $posted['show_borrowed_books'] ?? $showBorrowed;

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
    <div class="toast-region" data-toast-region aria-live="polite">
<?php foreach ($errors as $error): ?>
      <p role="alert" style="color:#b91c1c;margin:0 0 .75rem;"><?= h($error) ?></p>
<?php endforeach; ?>
<?php if ($success !== ''): ?>
      <p role="status" style="color:#15803d;margin:0 0 .75rem;"><?= h($success) ?></p>
<?php endif; ?>
    </div>
    <section class="panel">
      <div class="profile-head">
        <div class="avatar" aria-hidden="true" style="background:<?= h($avatarBackground) ?>;color:<?= h($textColor) ?>;"><?= h(userInitials($username)) ?></div>
        <div>
          <h2 class="profile-name"><?= h($username) ?></h2>
          <dl class="kv"><dt>Student No.</dt><dd><?= h($studentNumber) ?></dd><dt>Email</dt><dd><?= h($email) ?></dd><dt>Bio</dt><dd><?= h($bio) ?></dd></dl>
        </div>
      </div>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="cp">
      <h2 class="panel-title" id="cp">Customize profile</h2>
      <div class="toggle-row"><button class="switch" id="dark-switch" type="button" role="switch" aria-checked="false" aria-label="Dark mode"></button><span>Dark mode</span></div>

      <form method="post" action="profile.php" class="form-stack" style="gap:14px">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="save_profile">

        <div class="field"><label for="bio">Bio</label><input class="input" id="bio" name="bio" maxlength="280" value="<?= h($formBio) ?>"></div>

        <div class="choice-row">
          <label class="choice"><input type="radio" name="color_mode" value="solid" <?= !$useGradient ? 'checked' : '' ?>> Solid color</label>
          <label class="choice"><input type="radio" name="color_mode" value="gradient" <?= $useGradient ? 'checked' : '' ?>> Gradient</label>
        </div>
        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <input type="color" id="color-1" name="color1" value="<?= h($profileColor) ?>" aria-label="Profile colour" style="width:52px;height:38px;padding:2px;cursor:pointer">
          <input type="color" id="color-2" name="color2" value="<?= h($gradientColor) ?>" aria-label="Gradient second colour" style="width:52px;height:38px;padding:2px;cursor:pointer" <?= !$useGradient ? 'hidden' : '' ?>>
        </div>
        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <label for="color-text">Initials text color</label>
          <input type="color" id="color-text" name="color_text" value="<?= h($textColor) ?>" style="width:52px;height:38px;padding:2px;cursor:pointer">
        </div>

        <div><button class="btn btn--round" type="submit">Save profile</button></div>
      </form>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="as">
      <h2 class="panel-title" id="as">Account settings</h2>

      <form method="post" action="profile.php" class="form-stack" style="gap:14px">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <input type="hidden" name="action" value="save_account">

        <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username" type="text" maxlength="30" required autocomplete="username" value="<?= h($formUsername) ?>">
        </div>
        <div class="field">
          <label for="f-current">Current Password</label>
          <input class="input" id="f-current" name="current" type="password" autocomplete="current-password" placeholder="Required to change your username or password.">
        </div>
        <div class="field">
          <label for="f-new">New Password</label>
          <input class="input" id="f-new" name="new" type="password" autocomplete="new-password" minlength="8" placeholder="Leave this blank if not changing password.">
        </div>
        <div class="field">
          <label for="f-confirm">Confirm New Password</label>
          <input class="input" id="f-confirm" name="confirm" type="password" autocomplete="new-password" minlength="8">
        </div>
        <label class="choice"><input type="checkbox" name="show_borrowed_books" value="1" <?= $formShow ? 'checked' : '' ?>> Show my borrowed books to other students</label>

        <div><button class="btn btn--round" type="submit">Save Account Settings</button></div>
      </form>
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
    // Profile colour preview: one picker for solid, two for gradient
    (function () {
      var c1 = document.getElementById('color-1'), c2 = document.getElementById('color-2');
      var ct = document.getElementById('color-text');
      var avatar = document.querySelector('.profile-head .avatar');
      var radios = document.querySelectorAll('input[name="color_mode"]');
      function isGradient() { return document.querySelector('input[name="color_mode"]:checked').value === 'gradient'; }
      function preview() {
        var g = isGradient();
        c2.hidden = !g;
        avatar.style.background = g
          ? 'linear-gradient(135deg,' + c1.value + ',' + c2.value + ')'
          : c1.value;
        avatar.style.color = ct.value;
      }
      radios.forEach(function (r) { r.addEventListener('change', preview); });
      c1.addEventListener('input', preview);
      c2.addEventListener('input', preview);
      ct.addEventListener('input', preview);
      preview();
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
  <script src="js/app.js?v=2"></script>
  <script src="js/logout-modal.js"></script>
</body>
</html>