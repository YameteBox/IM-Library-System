<?php
// LOGIN BOX component: the white card, its styles, and the fetch() login script.
// It posts to the page that includes it (that page must handle the POST).
?>
<style>
  .login-card, .login-card * { box-sizing: border-box; }
  .login-card { width: 100%; max-width: 470px; background: #fff; border: 1px solid #d5dfee; padding: 28px; font-family: "Public Sans", sans-serif; color: #1a2540; }
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
</style>

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
