<?php
session_start();
require 'db.php';

// --- Login request (the login_box component posts JSON back to this page) ---
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
html, body { margin: 0; padding: 0; min-height: 100%; }
* { box-sizing: border-box; }
body { font-family: "Public Sans", sans-serif; color: #1a2540; background: #0b2a66; }

/* Layout only: the components style themselves */
.stage { position: relative; z-index: 1; min-height: 100vh; display: flex; flex-direction: column; padding: calc(var(--frame-pad, 20px) + 20px); }
.stage-center { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px 0; }
@media (max-width: 600px) { .stage { padding: calc(var(--frame-pad, 20px) + 12px); } }
</style>
</head>
<body>

<?php include 'components/background.php'; ?>

<div class="stage">
  <?php include 'components/banner.php'; ?>

  <div class="stage-center">
    <?php include 'components/login_box.php'; ?>
  </div>
</div>

</body>
</html>
