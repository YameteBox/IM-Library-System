<?php
require 'db.php';   // provides $pdo
session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: admin-welcome.php');
    exit;
}

$error = '';
$admin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin    = trim($_POST['admin'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($admin === '' || $password === '') {
        $error = 'Invalid admin ID/username or password.';
    } else {
        try {
            // Accepts either the admin number (e.g. A-001) or the username.
            $stmt = $pdo->prepare(
                'SELECT a.id, a.admin_number, a.password_hash, d.username
                   FROM admins a
                   LEFT JOIN admin_details d ON d.id = a.id
                  WHERE a.admin_number = :ident OR d.username = :uname'
            );
            $stmt->execute([':ident' => $admin, ':uname' => strtolower($admin)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($password, $row['password_hash'])) {
                $error = 'Invalid admin ID/username or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['admin_id']     = (int) $row['id'];
                $_SESSION['admin_number'] = $row['admin_number'];
                $_SESSION['admin_name']   = $row['username'] ?? $row['admin_number'];
                header('Location: admin-welcome.php');
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
  <title>Admin log in · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body data-page="admin-login">
  <div class="auth-shell">
    <div class="auth-stage">
      <main class="auth-body">
        <section class="auth-card auth-card--round" aria-labelledby="t">
          <img class="auth-logo" src="assets/logo.webp" alt="Manila Science High School seal" width="84" height="84">
          <h2 class="card-title" id="t">Welcome back</h2>
          <p class="sub">Log in with your admin ID or username.</p>
          <div class="toast-region" data-toast-region aria-live="polite"></div>

          <form class="form-stack" id="login-form" method="post" action="admin-login.php" novalidate>
            <?php if ($error !== ''): ?>
              <p role="alert" style="color:#b91c1c;margin:0 0 .75rem;font-size:.9rem;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <div class="field">
              <label for="f-admin">Admin ID</label>
              <input class="input" id="f-admin" name="admin" type="text" value="<?= htmlspecialchars($admin) ?>" placeholder="" autocomplete="username">
            </div>
            <div class="field">
              <label for="f-password">Password</label>
              <input class="input" id="f-password" name="password" type="password" value="" placeholder="" autocomplete="current-password">
            </div>
            <button class="btn btn--block btn--round" type="submit">Log In</button>
          </form>

          <p class="link-row">Are you a user? <a href="login.php">Log in</a></p>
        </section>
      </main>
    </div>
  </div>
  <!-- js/app.js is intentionally not loaded here: its prototype login would bypass the database check. -->
</body>
</html>