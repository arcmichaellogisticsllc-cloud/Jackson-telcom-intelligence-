<section class="login-panel">
  <p class="eyebrow">Account Security</p>
  <h1>Change password</h1>
  <p>Set a strong operating password before continuing into the command center.</p>
  <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="panel"><strong><?= htmlspecialchars($message) ?></strong><p><a class="btn" href="/">Open Command Center</a></p></div><?php endif; ?>
  <?php if (!$message): ?>
  <form method="post" action="/change-password" class="form-card">
    <label>Current Password <input type="password" name="current_password" required autocomplete="current-password"></label>
    <label>New Password <input type="password" name="new_password" required minlength="12" autocomplete="new-password"></label>
    <label>Confirm New Password <input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label>
    <button class="btn">Save Password</button>
  </form>
  <?php endif; ?>
</section>
