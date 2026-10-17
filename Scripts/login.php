<?php
session_start();
require 'db.php';

// --- Login request (sent as JSON by the fetch() call at the bottom) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $login    = trim($input['studentNumber'] ?? '');
    $password = $input['password'] ?? '';

    if ($login === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Enter your student number and password.']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT users.student_id, users.username, users.banned,
               students.password_hash, students.role
        FROM users
        INNER JOIN students ON users.student_id = students.student_id
        WHERE users.student_id = ? OR users.username = ?
    ');
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Student number or password is incorrect.']);
        exit;
    }

    if (in_array($user['banned'], [true, 't', 'true', '1', 1], true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'This account has been banned.']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];

    echo json_encode(['ok' => true, 'redirect' => 'welcome.php']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log In</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --frame-pad: 20px; }
html, body { margin: 0; padding: 0; min-height: 100%; }
* { box-sizing: border-box; }
body { font-family: "Public Sans", sans-serif; color: #1a2540; background: #0b2a66; }

/* ---------- BACKGROUND: navy frame + rounded tinted photo ---------- */
.bg-frame { position: fixed; inset: 0; z-index: 0; padding: var(--frame-pad); background: #0b2a66; }
.bg-photo {
  width: 100%; height: 100%; border-radius: 12px;
  background-image: linear-gradient(rgba(8, 32, 86, 0.68), rgba(8, 32, 86, 0.68)), url('MASCI_FACADE_2.jpg');
  background-size: cover; background-position: center;
}

/* ---------- LAYOUT: banner on top, login box centered below ---------- */
.stage { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; padding: calc(var(--frame-pad) + 20px); }
.stage-center { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px 0; }

/* ---------- BANNER ---------- */
.site-banner {
  display: flex; align-items: center; gap: 20px; padding: 20px 26px;
  border: 2px solid #e3a62a;
  background: linear-gradient(90deg, #3351a2 0%, #12347f 55%, #0a2766 100%);
  color: #fff;
}
.site-banner__logo { width: 60px; height: 60px; flex-shrink: 0; display: block; }
.site-banner__title { margin: 0; font-size: 22px; font-weight: 600; line-height: 1.25; }

/* ---------- LOGIN BOX ---------- */
.login-card { width: 100%; max-width: 470px; background: #fff; border: 1px solid #d5dfee; padding: 28px; }
.login-card h1 { margin: 0 0 14px; font-size: 18px; font-weight: 700; line-height: 1.3; }
.login-card .subtitle { margin: 0 0 18px; font-size: 13px; color: #5b6b82; }
.login-card label { display: block; margin: 0 0 8px; font-size: 14px; font-weight: 700; }
.login-card input {
  display: block; width: 100%; height: 36px; margin: 0 0 18px; padding: 0 12px;
  font-family: inherit; font-size: 15px; color: #1a2540; background: #fff;
  border: 1px solid #c5d0e0; border-radius: 4px; outline: none;
}
.login-card input:focus { border-color: #1e5eba; box-shadow: 0 0 0 2px rgba(30, 94, 186, 0.2); }
.log-in-btn {
  display: flex; align-items: center; justify-content: center; width: 100%; min-height: 35px;
  padding: 9px 16px; margin: 0 0 18px; background: #1e5eba; border: 0; border-radius: 4px;
  color: #fff; font-family: inherit; font-size: 14px; font-weight: 500; cursor: pointer;
}
.log-in-btn:hover { background: #1a52a3; }
.log-in-btn:disabled { opacity: 0.7; cursor: default; }
.login-card .error { margin: -6px 0 14px; font-size: 13px; color: #b3261e; }
.login-card .error:empty { display: none; }
.login-card .alt { margin: 0 0 14px; text-align: center; font-size: 13px; color: #5b6b82; }
.login-card .alt:last-child { margin-bottom: 0; }
.login-card .alt a { color: #1e5eba; text-decoration: underline; }

@media (max-width: 600px) {
  .stage { padding: calc(var(--frame-pad) + 12px); }
  .site-banner { padding: 14px 16px; gap: 12px; }
  .site-banner__logo { width: 44px; height: 44px; }
  .site-banner__title { font-size: 16px; }
}
</style>
</head>
<body>

<div class="bg-frame" aria-hidden="true"><div class="bg-photo"></div></div>

<div class="stage">
  <header class="site-banner">
    <img class="site-banner__logo" src="MASCI_LOGO.png" alt="Manila Science High School seal">
    <div class="site-banner__title">Manila Science High School Online Library</div>
  </header>

  <div class="stage-center">
    <form class="login-card" id="login-form" novalidate>
      <h1>Welcome back</h1>
      <p class="subtitle">Log in with your student number.</p>

      <label for="student-number">Student Number</label>
      <input type="text" id="student-number" name="studentNumber"
             placeholder="2023-00123" inputmode="numeric" autocomplete="username" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             autocomplete="current-password" required>

      <p class="error" id="error" role="alert"></p>

      <button type="submit" class="log-in-btn">Log In</button>

      <p class="alt">Don't have an account? <a href="register.php">Register now</a></p>
      <p class="alt">Are you an admin? <a href="admin_login.php">Log in</a></p>
    </form>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('login-form');
  const errorEl = document.getElementById('error');
  const button = form.querySelector('.log-in-btn');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorEl.textContent = '';

    const studentNumber = form.studentNumber.value.trim();
    const password = form.password.value;

    if (!studentNumber || !password) {
      errorEl.textContent = 'Enter your student number and password.';
      return;
    }

    button.disabled = true;
    button.textContent = 'Logging in…';
    let redirecting = false;

    try {
      const res = await fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ studentNumber, password })
      });
      const data = await res.json();

      if (!res.ok || !data.ok) {
        errorEl.textContent = data.error || 'Student number or password is incorrect.';
        return;
      }
      redirecting = true;
      window.location.href = data.redirect;
    } catch (err) {
      errorEl.textContent = 'Something went wrong. Please try again.';
    } finally {
      if (!redirecting) {
        button.disabled = false;
        button.textContent = 'Log In';
      }
    }
  });
})();
</script>
</body>
</html>
