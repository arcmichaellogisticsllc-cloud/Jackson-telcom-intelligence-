<section class="login-panel">
  <p class="eyebrow">Jackson Intelligence Platform</p>
  <h1>Acquisition command access</h1>
  <p>Phase 1 focuses on subcontractor capacity, relationships, opportunities, and market intelligence.</p>
  <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/login" class="form-card">
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn">Login</button>
  </form>
  <p class="hint"><a href="/password-reset">Request password reset</a></p>
</section>
