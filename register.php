<?php
require 'db.php';
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
</head>
<body data-page="register">
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
        <section class="auth-card" aria-labelledby="t">
          <div id="register-view" class="form-stack">
            <h2 class="card-title" id="t">Register an account</h2>
            <p class="sub">Books books books</p>
            <div class="toast-region" data-toast-region aria-live="polite"></div>
            <form class="form-stack" id="register-form" novalidate>
              <div class="field">
          <label for="f-student">Student Number</label>
          <input class="input" id="f-student" name="student" type="text" value="2023-00123" placeholder="" >
        </div>
              <div class="field">
          <label for="f-username">Username</label>
          <input class="input" id="f-username" name="username" type="text" value="maria.santos" placeholder="" autocomplete="username">
        </div>
              <div class="field">
          <label for="f-email">Email</label>
          <input class="input" id="f-email" name="email" type="email" value="maria@example.com" placeholder="" >
        </div>
              <div class="field">
          <label for="f-password">Password</label>
          <input class="input" id="f-password" name="password" type="password" value="password" placeholder="" autocomplete="new-password">
        </div>
              <div class="field">
          <label for="f-confirm">Confirm password</label>
          <input class="input" id="f-confirm" name="confirm" type="password" value="password" placeholder="" autocomplete="new-password">
        </div>

            <div class="terms-checkbox">
                <label>
                    <input type="checkbox" name="accept_guidelines" required>
                    I agree to the
                    <a href="#" class="terms-link" data-modal="guidelines-modal">
                        User Guidelines
                    </a>
                </label>

                <label>
                    <input type="checkbox" name="accept_terms" required>
                    I agree to the
                    <a href="#" class="terms-link" data-modal="terms-modal">
                        Terms of Service
                    </a>
                </label>
            </div>

            <button class="btn btn--block btn--round" type="submit">
                Create Student Account
            </button>
            </form>

          <!-- User Guidelines Popup -->
          <div class="terms-modal" id="guidelines-modal">
              <div class="terms-modal-content">
                  <button type="button" class="terms-modal-close" aria-label="Close">&times;</button>

                  <h2>User Guidelines</h2>

                  <div class="terms-modal-body">
                      <p>
                          Welcome to our website. Please read and follow these
                          guidelines when using our services.
                      </p>
                      <p>
                          [Insert your user guidelines here.]
                      </p>
                  </div>

                  <button type="button" class="btn btn--block modal-close-btn">
                      Close
                  </button>
              </div>
          </div>

          <!-- Terms of Service Popup -->
          <div class="terms-modal" id="terms-modal">
              <div class="terms-modal-content">
                  <button type="button" class="terms-modal-close" aria-label="Close">&times;</button>

                  <h2>Terms of Service</h2>

                  <div class="terms-modal-body">
                      <p>
                          By creating an account, you agree to comply with the
                          terms and conditions governing the use of this website.
                      </p>
                      <p>
                          [Insert your terms of service here.]
                      </p>
                  </div>

                  <button type="button" class="btn btn--block modal-close-btn">
                      Close
                  </button>
              </div>
          </div>

            <p class="link-row">Have an account? <a href="index.php">Log in</a></p>
          </div>
          <div id="success-view" class="form-stack" hidden>
            <h2 class="card-title">User account created successfully</h2>
            <p class="sub">You can now browse the library</p>
            <button class="btn btn--block btn--round" type="button" id="register-another">Register another user</button>
            <a class="btn btn--block btn--round" href="index.php">Log In</a>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/alerts.js"></script>
    <script src="js/app.js?v=2"></script>

  <script src="js/app.js?v=2"></script>
</body>
</html>
