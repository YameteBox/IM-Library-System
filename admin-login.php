
<?php
session_start();
require 'db.php'; // Provides $pdo

// Redirect admins who are already logged in.
if (!empty($_SESSION['admin_id'])) {
    header('Location: admin-welcome.php');
    exit;
}

$error = '';
$admin = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = trim($_POST['admin'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($admin === '' || $password === '') {
        $error = 'Please enter your admin ID or username and password.';
    } else {
        try {
            // Accept either the admin number or the username.
            // Username matching is case-insensitive.
            $stmt = $pdo->prepare(
                'SELECT
                    a.id,
                    a.admin_number,
                    a.password_hash,
                    d.username
                 FROM admins a
                 LEFT JOIN admin_details d ON d.id = a.id
                 WHERE a.admin_number = :admin_id
                    OR LOWER(d.username) = LOWER(:username)
                 LIMIT 1'
            );

            $stmt->execute([
                ':admin_id' => $admin,
                ':username' => $admin
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify the supplied password against the stored hash.
            if (
                !$row ||
                empty($row['password_hash']) ||
                !password_verify($password, $row['password_hash'])
            ) {
                $error = 'Invalid admin ID/username or password.';
            } else {
                // Regenerate the session ID after successful login.
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $row['id'];
                $_SESSION['admin_number'] = $row['admin_number'];
                $_SESSION['admin_name'] = !empty($row['username'])
                    ? $row['username']
                    : $row['admin_number'];

                header('Location: admin-welcome.php');
                exit;
            }
        } catch (Throwable $e) {
            // Log the actual error for debugging without exposing it to users.
            error_log('Admin login error: ' . $e->getMessage());

            $error = 'A server error occurred. Please try again.';
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

  <link
    rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap"
  >

  <link rel="stylesheet" href="css/styles.css">
</head>

<body data-page="admin-login">
  <div class="auth-shell">
    <div class="auth-stage">
      <main class="auth-body">
        <section
          class="auth-card auth-card--round"
          aria-labelledby="t"
        >
          <img
            class="auth-logo"
            src="assets/logo.webp"
            alt="Manila Science High School seal"
            width="84"
            height="84"
          >

          <h2 class="card-title" id="t">Welcome back</h2>

          <p class="sub">
            Log in with your admin ID or username.
          </p>

          <div
            class="toast-region"
            data-toast-region
            aria-live="polite"
          ></div>

          <form
            class="form-stack"
            id="login-form"
            method="post"
            action="admin-login.php"
          >
            <?php if ($error !== ''): ?>
              <p
                role="alert"
                style="color:#b91c1c;margin:0 0 .75rem;font-size:.9rem;"
              ><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="field">
              <label for="f-admin">Admin ID or Username</label>

              <input
                class="input"
                id="f-admin"
                name="admin"
                type="text"
                value="<?= htmlspecialchars($admin, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="Enter admin ID or username"
                autocomplete="username"
                required
              >
            </div>

            <div class="field">
              <label for="f-password">Password</label>

              <input
                class="input"
                id="f-password"
                name="password"
                type="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
              >
            </div>

            <button
              class="btn btn--block btn--round"
              type="submit"
            >
              Log In
            </button>
          </form>

          <p class="link-row">
            Are you a user?
            <a href="login.php">Log in</a>
          </p>
        </section>
      </main>
    </div>
  </div>

  <!--
    js/app.js is intentionally not loaded here because its prototype
    login could bypass the database authentication.
  -->
</body>
</html>
