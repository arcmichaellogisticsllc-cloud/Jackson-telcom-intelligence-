<section class="login-panel">
  <p class="eyebrow">Account recovery</p>
  <h1>Request password reset</h1>
  <p>Production requires a configured mailer. Local/dev mode writes the token to `storage/logs/password_resets.log`.</p>
  <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if (!empty($devToken)): ?>
    <div class="alert">Dev reset token: <code><?= htmlspecialchars($devToken) ?></code></div>
  <?php endif; ?>
  <form method="post" action="/password-reset" class="form-card">
    <label>Email <input type="email" name="email" required></label>
    <button class="btn">Create Reset Token</button>
  </form>
  <p class="hint"><a href="/login">Back to login</a></p>
</section>
