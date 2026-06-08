<section class="login-panel">
  <p class="eyebrow">Account recovery</p>
  <h1>Set new password</h1>
  <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/password-reset/confirm" class="form-card">
    <label>Reset Token <input name="token" required value="<?= htmlspecialchars($token ?? '') ?>"></label>
    <label>New Password <input type="password" name="password" required minlength="10"></label>
    <button class="btn">Reset Password</button>
  </form>
  <p class="hint"><a href="/password-reset">Request a new token</a></p>
</section>
