<?php
session_start();
require 'db.php';

// Must be logged in; logout.php destroys this session
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId   = (int) $_SESSION['user_id'];
$username = (string) ($_SESSION['username'] ?? $_SESSION['student_number'] ?? '');

// ---------------------------------------------------------------------------
// OTP verification shell
//
// Flip OTP_ENFORCED to true once the three TODO functions below are written.
// While it is false the page behaves exactly as before (no code is asked for).
// When it is true, the student sees the code form until $_SESSION['otp_verified']
// is set, then the normal welcome card.
// ---------------------------------------------------------------------------
const OTP_ENFORCED    = true;
const OTP_LENGTH      = 6;
const OTP_RESEND_WAIT = 30; // seconds the Resend button stays disabled (front end only)

/**
 * Create and deliver a new code to the student.
 * TODO (backend): generate a random OTP_LENGTH-digit code, store only a hash of it with an expiry
 * (e.g. 10 minutes) and an attempt counter for $userId, then send it (email/SMS).
 * Return true when it was sent, false when it could not be.
 */
function otpSend(PDO $pdo, int $userId): bool
{
    return false;
}

/**
 * Check a submitted code.
 * TODO (backend): compare $code with the stored hash for $userId (hash_equals), reject expired codes,
 * count failed attempts and lock after too many, and invalidate the code once it is used.
 * Return [true, null] on success, or [false, 'message shown to the student'] on failure.
 */
function otpVerify(PDO $pdo, int $userId, string $code): array
{
    // Placeholder: accepts any well-formed code. Replace with the real check.
    return [true, null];
}

/**
 * Where the code was sent, already masked for display (e.g. "m***@example.com").
 * TODO (backend): look it up from user_details (email / phone). An empty string shows generic wording.
 */
function otpMaskedDestination(PDO $pdo, int $userId): string
{
    return '';
}

// ---- Handle the OTP form ---------------------------------------------------
$flashMessage = '';
$flashType    = 'error';
$redirectTo   = 'welcome.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && OTP_ENFORCED && empty($_SESSION['otp_verified'])) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'verify_otp') {
        $code = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));
        if (strlen($code) !== OTP_LENGTH) {
            $flashMessage = 'Enter the ' . OTP_LENGTH . '-digit code.';
        } else {
            [$ok, $err] = otpVerify($pdo, $userId, $code);
            if ($ok) {
                session_regenerate_id(true);
                $_SESSION['otp_verified'] = true;
                $redirectTo = 'library.php'; // verified: go straight to the library
            } else {
                $flashMessage = $err ?? 'That code is not valid.';
            }
        }
    } elseif ($action === 'resend_otp') {
        if (otpSend($pdo, $userId)) {
            $flashMessage = 'A new code has been sent.';
            $flashType    = 'success';
        } else {
            $flashMessage = 'We could not send a new code. Please try again.';
        }
    }

    // Post/Redirect/Get so a refresh does not resubmit the form
    if ($flashMessage !== '') {
        $_SESSION['flash_message'] = $flashMessage;
        $_SESSION['flash_type']    = $flashType;
    }
    header('Location: ' . $redirectTo);
    exit;
}

if (isset($_SESSION['flash_message'])) {
    $flashMessage = (string) $_SESSION['flash_message'];
    $flashType    = (string) ($_SESSION['flash_type'] ?? 'error');
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$needsOtp    = OTP_ENFORCED && empty($_SESSION['otp_verified']);
$destination = $needsOtp ? otpMaskedDestination($pdo, $userId) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Welcome · Manila Science High School Online Library</title>
  <link rel="icon" href="assets/logo.webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .otp-row { display: flex; gap: 8px; justify-content: center; }
    .otp-row .input { width: 3rem; height: 3.4rem; padding: 0; text-align: center; font-size: 1.5rem; font-weight: 600; }
    .otp-msg { margin: 0; font-size: .9rem; text-align: center; }
    .otp-msg--error { color: #b91c1c; }
    .otp-msg--success { color: #15803d; }
    .otp-resend { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: .9rem; }
    .otp-resend form { margin: 0; }
    .otp-resend .btn[disabled] { opacity: .5; cursor: not-allowed; }
    @media (max-width: 420px) { .otp-row .input { width: 2.5rem; height: 3rem; font-size: 1.25rem; } }
  </style>
</head>
<body data-page="welcome">
  <div class="auth-shell">
    <div class="auth-stage">
    <header class="site-header">
      <div class="brand">
        <img class="brand__logo" src="assets/logo.webp" alt="Manila Science High School seal" width="70" height="70">
        <div class="brand__text">
          <h1 class="brand__title">Library Management</h1>
        </div>
      </div>
    </header>
      <main class="auth-body">
<?php if ($needsOtp): ?>
        <section class="auth-card auth-card--round" aria-labelledby="t">
          <h2 class="card-title" id="t">Verify it's you</h2>
          <p class="sub">
            Enter the <?= (int) OTP_LENGTH ?>-digit code we sent to
            <?= $destination !== '' ? '<strong>' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '</strong>' : 'your registered contact' ?>.
          </p>

          <div class="toast-region" data-toast-region aria-live="polite"></div>
          <?php if ($flashMessage !== ''): ?>
            <p class="otp-msg otp-msg--<?= $flashType === 'success' ? 'success' : 'error' ?>" role="<?= $flashType === 'success' ? 'status' : 'alert' ?>"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>

          <form class="form-stack" id="otp-form" method="post" action="welcome.php" novalidate>
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="otp" id="otp-value" value="">
            <div class="otp-row" role="group" aria-label="One-time code">
<?php for ($i = 1; $i <= OTP_LENGTH; $i++): ?>
              <input class="input otp-digit" type="text" inputmode="numeric" pattern="[0-9]" maxlength="1"
                     autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>" aria-label="Digit <?= $i ?> of <?= (int) OTP_LENGTH ?>">
<?php endfor; ?>
            </div>
            <button class="btn btn--block btn--round" type="submit" id="otp-submit" disabled>Verify</button>
          </form>

          <div class="otp-resend">
            <span>Didn't get a code?</span>
            <form method="post" action="welcome.php">
              <input type="hidden" name="action" value="resend_otp">
              <button class="btn btn--outline btn--sm" type="submit" id="otp-resend" disabled>Resend<span id="otp-timer"></span></button>
            </form>
          </div>

          <div class="btn-row">
            <a class="btn btn--round btn--danger" href="logout.php">Log out</a>
          </div>
        </section>
<?php else: ?>
        <section class="auth-card" aria-labelledby="t">
          <h2 class="card-title" id="t">You're logged in, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</h2>
          <p class="sub">Welcome back.</p>
          <div><span class="pill pill--blue">student</span></div>
          <div class="btn-row">
            <a class="btn btn--round" href="library.php">Browse the library</a>
            <a class="btn btn--round btn--danger" href="logout.php">Log out</a>
          </div>
        </section>
<?php endif; ?>
      </main>
    </div>
  </div>

<?php if ($needsOtp): ?>
  <script>
    // These forms must really POST to PHP: keep app.js from intercepting their submits.
    document.addEventListener('submit', function (e) { e.stopImmediatePropagation(); }, true);

    (function () {
      var digits = Array.prototype.slice.call(document.querySelectorAll('.otp-digit'));
      var hidden = document.getElementById('otp-value');
      var submit = document.getElementById('otp-submit');
      var form   = document.getElementById('otp-form');

      function sync() {
        var code = digits.map(function (d) { return d.value; }).join('');
        hidden.value = code;
        submit.disabled = code.length !== digits.length;
      }
      function fill(start, text) {
        var chars = text.replace(/\D/g, '').split('');
        for (var i = 0; i < chars.length && start + i < digits.length; i++) digits[start + i].value = chars[i];
        digits[Math.min(start + chars.length, digits.length - 1)].focus();
        sync();
      }

      digits.forEach(function (d, i) {
        d.addEventListener('input', function () {
          var v = d.value.replace(/\D/g, '');
          d.value = '';
          if (v) fill(i, v); else sync();
        });
        d.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !d.value && i > 0) { digits[i - 1].value = ''; digits[i - 1].focus(); sync(); }
          else if (e.key === 'ArrowLeft' && i > 0) { digits[i - 1].focus(); }
          else if (e.key === 'ArrowRight' && i < digits.length - 1) { digits[i + 1].focus(); }
        });
        d.addEventListener('paste', function (e) {
          e.preventDefault();
          fill(i, (e.clipboardData || window.clipboardData).getData('text') || '');
        });
        d.addEventListener('focus', function () { d.select(); });
      });

      form.addEventListener('submit', function (e) {
        sync();
        if (submit.disabled) e.preventDefault();
      });
      digits[0].focus();

      // Resend cooldown (cosmetic; the backend should enforce its own limit)
      var resend = document.getElementById('otp-resend');
      var timer  = document.getElementById('otp-timer');
      var left   = <?= (int) OTP_RESEND_WAIT ?>;
      function tick() {
        if (left > 0) { resend.disabled = true; timer.textContent = ' (' + left + 's)'; left--; setTimeout(tick, 1000); }
        else { resend.disabled = false; timer.textContent = ''; }
      }
      tick();
    })();
  </script>
<?php endif; ?>
  <script src="js/app.js?v=2"></script>
</body>
</html>
