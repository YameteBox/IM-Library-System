<?php
session_start();
require 'db.php';

// Admin-only page
if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

$errors = [];
$success = '';
$profileSaved = false;
$accountSaved = false;

// CSRF protection
if (empty($_SESSION['admin_profile_csrf'])) {
    $_SESSION['admin_profile_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['admin_profile_csrf'];

// Default values
$admin = [
    'id' => $adminId,
    'admin_number' => $_SESSION['admin_number'] ?? '',
    'username' => $_SESSION['admin_name'] ?? '',
    'email' => '',
    'bio' => '',
    'color' => '#1E3A8A',
    'use_gradient' => false,
    'gradient_color_2' => '#3B82F6',
    'text_color' => '#FFFFFF'
];

function profileEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validProfileColor($value): bool
{
    return is_string($value)
        && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

try {
    // Ensure the admin has detail and profile records.
    // These inserts do not overwrite existing settings.
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO admin_details (id, username, email)
         VALUES (
             :id,
             :username,
             :email
         )
         ON CONFLICT (id) DO NOTHING'
    );

    // These fallback values are only used if the detail row is missing.
    $fallbackUsername = $_SESSION['admin_name'] ?? ('admin' . $adminId);
    $fallbackEmail = 'admin' . $adminId . '@invalid.local';

    $stmt->execute([
        ':id' => $adminId,
        ':username' => substr($fallbackUsername, 0, 30),
        ':email' => $fallbackEmail
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO admin_profiles (id)
         VALUES (:id)
         ON CONFLICT (id) DO NOTHING'
    );
    $stmt->execute([':id' => $adminId]);

    $pdo->commit();

    // Verify the logged-in administrator still exists and load the profile.
    $stmt = $pdo->prepare(
        'SELECT
            a.id,
            a.admin_number,
            a.password_hash,
            d.username,
            d.email,
            p.bio,
            p.color,
            p.use_gradient,
            p.gradient_color_2,
            p.text_color
         FROM admins a
         JOIN admin_details d ON d.id = a.id
         JOIN admin_profiles p ON p.id = a.id
         WHERE a.id = :id'
    );

    $stmt->execute([':id' => $adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        session_destroy();
        header('Location: admin-login.php');
        exit;
    }

    $admin = $row;
    $_SESSION['admin_number'] = $admin['admin_number'];
    $_SESSION['admin_name'] = $admin['username'];

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Admin profile load error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load the admin profile. Please check the database configuration.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken)
        || !hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Your session expired or the request was invalid. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'save_profile') {
                $bio = trim($_POST['bio'] ?? '');
                $color = $_POST['color1'] ?? '#1E3A8A';
                $color2 = $_POST['color2'] ?? '#3B82F6';
                $textColor = $_POST['color_text'] ?? '#FFFFFF';
                $useGradient = ($_POST['color_mode'] ?? 'solid') === 'gradient';

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
                        'UPDATE admin_profiles
                         SET bio = :bio,
                             color = :color,
                             use_gradient = :use_gradient,
                             gradient_color_2 = :color2,
                             text_color = :text_color
                         WHERE id = :id'
                    );

                    $stmt->execute([
                        ':bio' => $bio,
                        ':color' => $color,
                        ':use_gradient' => $useGradient,
                        ':color2' => $useGradient ? $color2 : null,
                        ':text_color' => $textColor,
                        ':id' => $adminId
                    ]);

                    $success = 'Profile customization saved successfully.';
                    $profileSaved = true;
                }

            } elseif ($action === 'save_account') {
                $username = trim($_POST['username'] ?? '');
                $currentPassword = $_POST['current'] ?? '';
                $newPassword = $_POST['new'] ?? '';
                $confirmPassword = $_POST['confirm'] ?? '';

                if ($username === '' || mb_strlen($username) > 30) {
                    $errors[] = 'Username must contain 1–30 characters.';
                } elseif (!preg_match('/^[\p{L}\p{N}_.-]+$/u', $username)) {
                    $errors[] = 'Username may contain letters, numbers, underscores, periods, and hyphens only.';
                }

                // An empty new password means the username is being changed only.
                $changingPassword = ($newPassword !== ''
                    || $confirmPassword !== ''
                    || $currentPassword !== '');

                if ($changingPassword) {
                    if ($currentPassword === '') {
                        $errors[] = 'Enter your current password to change your password.';
                    }

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

                    // Lock and verify this admin's record before changing anything.
                    $stmt = $pdo->prepare(
                        'SELECT password_hash
                         FROM admins
                         WHERE id = :id
                         FOR UPDATE'
                    );
                    $stmt->execute([':id' => $adminId]);
                    $passwordRow = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$passwordRow) {
                        throw new RuntimeException('Admin account no longer exists.');
                    }

                    if ($changingPassword
                        && !password_verify(
                            $currentPassword,
                            $passwordRow['password_hash']
                        )) {
                        $pdo->rollBack();
                        $errors[] = 'Your current password is incorrect.';
                    } else {
                        // Update username. The unique constraint protects against duplicates.
                        $stmt = $pdo->prepare(
                            'UPDATE admin_details
                             SET username = :username
                             WHERE id = :id'
                        );
                        $stmt->execute([
                            ':username' => $username,
                            ':id' => $adminId
                        ]);

                        if ($changingPassword) {
                            $newHash = password_hash(
                                $newPassword,
                                PASSWORD_DEFAULT
                            );

                            $stmt = $pdo->prepare(
                                'UPDATE admins
                                 SET password_hash = :password_hash
                                 WHERE id = :id'
                            );
                            $stmt->execute([
                                ':password_hash' => $newHash,
                                ':id' => $adminId
                            ]);
                        }

                        $pdo->commit();

                        $_SESSION['admin_name'] = $username;
                        $success = 'Account settings saved successfully.';
                        $accountSaved = true;

                        // Clear submitted passwords on the next request.
                        $_POST = [];
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
                error_log('Admin profile update error: ' . $e->getMessage());
                $errors[] = 'Unable to save your changes. Please try again.';
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Admin profile error: ' . $e->getMessage());
            $errors[] = 'An unexpected error occurred. Please try again.';
        }
    }

    // Reload current saved values, so the page reflects database changes.
    try {
        $stmt = $pdo->prepare(
            'SELECT
                a.id,
                a.admin_number,
                a.password_hash,
                d.username,
                d.email,
                p.bio,
                p.color,
                p.use_gradient,
                p.gradient_color_2,
                p.text_color
             FROM admins a
             JOIN admin_details d ON d.id = a.id
             JOIN admin_profiles p ON p.id = a.id
             WHERE a.id = :id'
        );
        $stmt->execute([':id' => $adminId]);
        $updatedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($updatedAdmin) {
            $admin = $updatedAdmin;
            $_SESSION['admin_number'] = $admin['admin_number'];
            $_SESSION['admin_name'] = $admin['username'];
        }
    } catch (Throwable $e) {
        error_log('Admin profile refresh error: ' . $e->getMessage());
    }
}

$initials = '';
$parts = preg_split('/[\s._-]+/', trim($admin['username'] ?? ''));

foreach ($parts as $part) {
    if ($part !== '') {
        $initials .= mb_substr($part, 0, 1);
    }
    if (mb_strlen($initials) >= 2) {
        break;
    }
}

if ($initials === '') {
    $initials = 'AD';
}

$useGradient = filter_var($admin['use_gradient'], FILTER_VALIDATE_BOOLEAN);
$profileColor = validProfileColor($admin['color'] ?? null)
    ? $admin['color'] : '#1E3A8A';
$gradientColor = validProfileColor($admin['gradient_color_2'] ?? null)
    ? $admin['gradient_color_2'] : '#3B82F6';
$textColor = validProfileColor($admin['text_color'] ?? null)
    ? $admin['text_color'] : '#FFFFFF';

$avatarBackground = $useGradient
    ? "linear-gradient(135deg, {$profileColor}, {$gradientColor})"
    : $profileColor;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile (Admin) · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/sidebar.css">
</head>
<body data-page="admin-profile">
  <div class="page">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">My Profile — Admin <?= profileEscape($admin['admin_number']) ?></h1>
          <span class="brand__user"><?= profileEscape($admin['username']) ?></span>
          <span class="role-badge">admin</span>
        </div>
      </div>
      <nav class="nav" aria-label="Main">
        <a class="nav__link" href="admin-library.php">← Back to admin library</a>
        <a class="nav__link" href="admin-logout.php">Log out</a>
      </nav>
    </header>

    <div class="toast-region" data-toast-region aria-live="polite">
      <?php foreach ($errors as $error): ?>
        <p role="alert" style="color:#b91c1c;margin:0 0 .75rem;">
          <?= profileEscape($error) ?>
        </p>
      <?php endforeach; ?>

      <?php if ($success !== ''): ?>
        <p role="status" style="color:#15803d;margin:0 0 .75rem;">
          <?= profileEscape($success) ?>
        </p>
      <?php endif; ?>
    </div>

    <section class="panel">
      <div class="profile-head">
        <div
          class="avatar avatar--navy"
          aria-hidden="true"
          style="background:<?= profileEscape($avatarBackground) ?>;color:<?= profileEscape($textColor) ?>;"
        ><?= profileEscape($initials) ?></div>
        <div>
          <h2 class="profile-name"><?= profileEscape($admin['username']) ?></h2>
          <dl class="kv">
            <dt>Admin ID</dt>
            <dd><?= profileEscape($admin['admin_number']) ?></dd>
          </dl>
        </div>
      </div>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="cp">
      <h2 class="panel-title" id="cp">Customize profile</h2>

      <div class="toggle-row">
        <button class="switch" id="dark-switch" type="button" role="switch"
          aria-checked="false" aria-label="Dark mode"></button>
        <span>Dark mode</span>
      </div>

      <form method="post" action="admin-profile.php" class="form-stack" style="gap:14px">
        <input type="hidden" name="csrf_token" value="<?= profileEscape($csrfToken) ?>">
        <input type="hidden" name="action" value="save_profile">

        <div class="field">
          <label for="bio">Bio</label>
          <input class="input" id="bio" name="bio" maxlength="280"
            value="<?= profileEscape($admin['bio']) ?>">
        </div>

        <div class="choice-row">
          <label class="choice">
            <input type="radio" name="color_mode" value="solid" <?= !$useGradient ? 'checked' : '' ?>>
            Solid color
          </label>
          <label class="choice">
            <input type="radio" name="color_mode" value="gradient" <?= $useGradient ? 'checked' : '' ?>>
            Gradient
          </label>
        </div>

        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <input type="color" id="color-1" name="color1"
            value="<?= profileEscape($profileColor) ?>"
            aria-label="Profile colour"
            style="width:52px;height:38px;padding:2px;cursor:pointer">

          <input type="color" id="color-2" name="color2"
            value="<?= profileEscape($gradientColor) ?>"
            aria-label="Gradient second colour"
            style="width:52px;height:38px;padding:2px;cursor:pointer"
            <?= !$useGradient ? 'hidden' : '' ?>>
        </div>

        <div class="color-row" style="display:flex;gap:12px;align-items:center">
          <label for="color-text">Initials text color</label>
          <input type="color" id="color-text" name="color_text"
            value="<?= profileEscape($textColor) ?>"
            style="width:52px;height:38px;padding:2px;cursor:pointer">
        </div>

        <div>
          <button class="btn btn--round" type="submit">Save profile</button>
        </div>
      </form>
    </section>

    <section class="panel panel--lg form-stack" style="gap:14px" aria-labelledby="as">
      <h2 class="panel-title" id="as">Account settings</h2>

      <form method="post" action="admin-profile.php" class="form-stack" style="gap:14px">
        <input type="hidden" name="csrf_token" value="<?= profileEscape($csrfToken) ?>">
        <input type="hidden" name="action" value="save_account">

        <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username"
            type="text" maxlength="30" required autocomplete="username"
            value="<?= profileEscape(
                isset($_POST['username']) && !$accountSaved
                    ? $_POST['username']
                    : $admin['username']
            ) ?>">
        </div>

        <div class="field">
          <label for="f-current">Current Password</label>
          <input class="input" id="f-current" name="current"
            type="password" autocomplete="current-password">
        </div>

        <div class="field">
          <label for="f-new">New Password</label>
          <input class="input" id="f-new" name="new"
            type="password" autocomplete="new-password" minlength="8">
        </div>

        <div class="field">
          <label for="f-confirm">Confirm New Password</label>
          <input class="input" id="f-confirm" name="confirm"
            type="password" autocomplete="new-password" minlength="8">
        </div>

        <p class="sub">Leave all password fields empty if you only want to change your username.</p>

        <div>
          <button class="btn btn--round" type="submit">Save Account Settings</button>
        </div>
      </form>
    </section>
  </div>

  <script>
    // Profile colour preview
    (function () {
      var c1 = document.getElementById('color-1');
      var c2 = document.getElementById('color-2');
      var ct = document.getElementById('color-text');
      var avatar = document.querySelector('.profile-head .avatar');
      var radios = document.querySelectorAll('input[name="color_mode"]');

      function isGradient() {
        return document.querySelector('input[name="color_mode"]:checked').value === 'gradient';
      }

      function preview() {
        var gradient = isGradient();
        c2.hidden = !gradient;
        avatar.style.background = gradient
          ? 'linear-gradient(135deg,' + c1.value + ',' + c2.value + ')'
          : c1.value;
        avatar.style.color = ct.value;
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', preview);
      });

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