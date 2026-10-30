<?php
session_start();
require 'db.php';   // provides $pdo

$errors  = [];
$success = false;
$old     = ['student' => '', 'username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student  = trim($_POST['student'] ?? '');
    $username = trim($_POST['username'] ?? ''); // keep the letter case as typed
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    $old = ['student' => $student, 'username' => $username, 'email' => $email, 'phone' => $phone];

    if ($student === '' || $username === '' || $email === '' || $password === '') {
        $errors[] = 'Please fill in all fields.';
    }
    if (strlen($student) > 20) {
        $errors[] = 'Student number must be 20 characters or fewer.';
    }
    if (strlen($username) > 30) {
        $errors[] = 'Username must be 30 characters or fewer.';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
        $errors[] = 'Please enter a valid email address.';
    }
    // Phone is optional; digits with an optional leading +, spaces and dashes allowed (max 15 chars).
    if ($phone !== '' && (strlen($phone) > 15 || !preg_match('/^\+?[0-9][0-9 \-]*$/', $phone))) {
        $errors[] = 'Please enter a valid phone number (up to 15 characters).';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            // Usernames keep their case, but "Juan" and "juan" still count as the same name
            $dup = $pdo->prepare('SELECT 1 FROM user_details WHERE LOWER(username) = LOWER(:un)');
            $dup->execute([':un' => $username]);
            if ($dup->fetchColumn()) {
                $errors[] = 'That Student ID, username or email is already registered.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Server error. Please try again.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO users (student_number, password_hash)
                 VALUES (:sn, :hash) RETURNING id'
            );
            $stmt->execute([
                ':sn'   => $student,
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $stmt->fetchColumn();

            $pdo->prepare(
                'INSERT INTO user_details (id, username, email, phone) VALUES (:id, :un, :em, :ph)'
            )->execute([
                ':id' => $userId,
                ':un' => $username,
                ':em' => $email,
                ':ph' => $phone !== '' ? $phone : null,
            ]);

            // profile row with all defaults (bio, colors, show_borrowed_books)
            $pdo->prepare('INSERT INTO user_profiles (id) VALUES (:id)')
                ->execute([':id' => $userId]);

            $pdo->commit();

            // Sign the new student in and send them to welcome.php to verify the OTP
            session_regenerate_id(true);
            $_SESSION['user_id']        = $userId;
            $_SESSION['student_number'] = $student;
            $_SESSION['username']       = $username;
            $_SESSION['otp_pending']    = true;
            unset($_SESSION['otp_verified'], $_SESSION['otp_sent']);
            header('Location: welcome.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() === '23505') {   // unique violation
                $errors[] = 'That Student ID, username or email is already registered.';
            } else {
                $errors[] = 'Server error. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .tos-box { height: 170px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 8px; padding: .75rem 1rem; font-size: .9rem; }
    .tos-box:focus { outline: 2px solid #1E3A8A; }
    .tos-box h3 { font-size: .95rem; margin: 1rem 0 .25rem; }
    .tos-box h3:first-child { margin-top: 0; }
    .tos-box p { margin: 0 0 .5rem; }
    .tos-hint { font-size: .85rem; color: #64748b; margin: .35rem 0 .5rem; }
    .tos-hint.is-done { color: #15803d; }
    .tos-accept { margin-bottom: .75rem; }
    .tos-accept input:disabled + span { opacity: .5; }
    .btn[disabled] { opacity: .5; cursor: not-allowed; }
    .form-error { color: #b91c1c; font-size: .9rem; margin: 0 0 .75rem; }
  </style>
</head>
<body data-page="register">
  <div class="auth-shell">
    <div class="auth-stage">
      <main class="auth-body">
        <section class="auth-card" aria-labelledby="t">
          <img class="auth-logo" src="assets/logo.webp" alt="Manila Science High School seal" width="84" height="84">

          <?php if (!$success): ?>
          <div id="register-view" class="form-stack">
            <h2 class="card-title" id="t">Register an account</h2>
            <p class="sub">Books books books</p>
            <div class="toast-region" data-toast-region aria-live="polite"></div>

            <form class="form-stack" id="register-form" method="post" action="register.php" novalidate>
              <?php foreach ($errors as $err): ?>
                <p class="form-error" role="alert"><?= htmlspecialchars($err) ?></p>
              <?php endforeach; ?>

              <div class="field">
                <label for="f-student">Student Number</label>
                <input class="input" id="f-student" name="student" type="text" value="<?= htmlspecialchars($old['student']) ?>" placeholder="">
              </div>
              <div class="field">
                <label for="f-username">Username</label>
                <input class="input" id="f-username" name="username" type="text" value="<?= htmlspecialchars($old['username']) ?>" placeholder="" autocomplete="username">
              </div>
              <div class="field">
                <label for="f-email">Email</label>
                <input class="input" id="f-email" name="email" type="email" value="<?= htmlspecialchars($old['email']) ?>" placeholder="">
              </div>
              <div class="field">
                <label for="f-phone">Phone number (optional)</label>
                <input class="input" id="f-phone" name="phone" type="tel" value="<?= htmlspecialchars($old['phone']) ?>" placeholder="" autocomplete="tel">
              </div>
              <div class="field">
                <label for="f-password">Password</label>
                <input class="input" id="f-password" name="password" type="password" value="" placeholder="" autocomplete="new-password">
              </div>
              <div class="field">
                <label for="f-confirm">Confirm password</label>
                <input class="input" id="f-confirm" name="confirm" type="password" value="" placeholder="" autocomplete="new-password">
              </div>

              <div class="field">
                <label id="tos-label">Terms of Service</label>
                <div class="tos-box" id="tos-box" tabindex="0" role="region" aria-labelledby="tos-label" aria-describedby="tos-hint">
                  <!-- TODO: paste your original Terms of Service text here (kept the same nine headings). -->
                  <h3>1. Your account</h3>
                  <p>You are responsible for keeping your login details private and for activity on your account.</p>
                  <h3>2. Borrowing</h3>
                  <p>Borrow requests are subject to approval by library staff and to the due dates they set.</p>
                  <h3>3. Overdue, lost or damaged items</h3>
                  <p>Return items on time and in good condition. Overdue, lost or damaged items may be handled according to library policy.</p>
                  <h3>4. Online media</h3>
                  <p>Digital materials are for your personal use only and may not be copied or redistributed.</p>
                  <h3>5. Your profile and privacy</h3>
                  <p>Your profile details are used to run the library. You can control whether your borrowed books are shown on your profile.</p>
                  <h3>6. Behavior</h3>
                  <p>Treat other users and library staff with respect.</p>
                  <h3>7. Suspension</h3>
                  <p>Accounts that break these terms may be suspended by library staff.</p>
                  <h3>8. Changes</h3>
                  <p>These terms may be updated from time to time.</p>
                  <h3>9. Acceptance</h3>
                  <p>By creating an account you agree to these terms.</p>
                </div>
                <p class="tos-hint" id="tos-hint" aria-live="polite">Scroll to the bottom of the terms to continue.</p>
                <label class="choice tos-accept">
                  <input type="checkbox" id="tos-accept" disabled>
                  <span>I have read and agree to the Terms of Service</span>
                </label>
              </div>

              <button class="btn btn--block btn--round" type="submit" id="register-submit" disabled>Create Student Account</button>
            </form>

            <p class="link-row">Have an account? <a href="login.php">Log in</a></p>
          </div>
          <?php else: ?>
          <div id="success-view" class="form-stack">
            <h2 class="card-title" id="t">User account created successfully</h2>
            <p class="sub">You can now browse the library</p>
            <a class="btn btn--block btn--round" href="register.php">Register another user</a>
            <a class="btn btn--block btn--round" href="login.php">Log In</a>
          </div>
          <?php endif; ?>
        </section>
      </main>
    </div>
  </div>

  <?php if (!$success): ?>
  <script>
    // Submit stays disabled until the terms are scrolled to the bottom and the box is ticked.
    (function () {
      var box = document.getElementById('tos-box');
      var hint = document.getElementById('tos-hint');
      var accept = document.getElementById('tos-accept');
      var submit = document.getElementById('register-submit');
      var form = document.getElementById('register-form');

      function sync() { submit.disabled = !accept.checked; }

      function checkScroll() {
        if (box.scrollTop + box.clientHeight >= box.scrollHeight - 4) {
          accept.disabled = false;
          hint.textContent = 'Thanks. Tick the box below to continue.';
          hint.classList.add('is-done');
        }
      }

      box.addEventListener('scroll', checkScroll);
      accept.addEventListener('change', sync);
      form.addEventListener('submit', function (e) {
        if (!accept.checked) { e.preventDefault(); }
      }, true);
      checkScroll();   // short content needs no scrolling
    })();
  </script>
  <?php endif; ?>
</body>
</html>