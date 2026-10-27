<?php
require 'db.php';   // provides $pdo
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: welcome.php');
    exit;
}

$error   = '';
$student = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student  = trim($_POST['student'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($student === '' || $password === '') {
        $error = 'Invalid username/student ID or password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT u.id, u.student_number, u.password_hash, d.username
                   FROM users u
                   LEFT JOIN user_details d ON d.id = u.id
                  WHERE u.student_number = :sn'
            );
            $stmt->execute([':sn' => $student]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid username/student ID or password.';
            } else {
                // Banned students can still log in; they just can't request to borrow
                // (see userIsBanned() in search_helpers.php).
                session_regenerate_id(true);
                $_SESSION['user_id']        = (int) $user['id'];
                $_SESSION['student_number'] = $user['student_number'];
                $_SESSION['username']       = $user['username'] ?? $user['student_number'];
                header('Location: welcome.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Server error. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="login">
  <div class="auth-shell">
    <div class="auth-stage">
      <main class="auth-body">
        <section class="auth-card" aria-labelledby="t">
          <img class="auth-logo" src="assets/logo.webp" alt="Manila Science High School seal" width="84" height="84">
          <h2 class="card-title" id="t">Welcome back</h2>
          <p class="sub">Log in with your student number.</p>
          <div class="toast-region" data-toast-region aria-live="polite"></div>

          <form class="form-stack" id="login-form" method="post" action="login.php" novalidate>
            <?php if ($error !== ''): ?>
              <p role="alert" style="color:#b91c1c;margin:0 0 .75rem;font-size:.9rem;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <div class="field">
              <label for="f-student">Student Number</label>
              <input class="input" id="f-student" name="student" type="text" value="<?= htmlspecialchars($student) ?>" placeholder="" autocomplete="username">
            </div>
            <div class="field">
              <label for="f-password">Password</label>
              <input class="input" id="f-password" name="password" type="password" value="" placeholder="" autocomplete="current-password">
            </div>
            <button class="btn btn--block btn--round" type="submit">Log In</button>
          </form>

          <div class="link-stack">
            <p class="link-row">Don't have an account? <a href="register.php">Register now</a></p>
            <p class="link-row">Are you an admin? <a href="admin-login.php">Log in</a></p>
          </div>
        </section>
      </main>
    </div>
  </div>
  <!-- js/app.js is intentionally not loaded here: its prototype login would bypass the database check. -->
</body>
</html>