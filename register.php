<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
if (current_user()) redirect('dashboard.php');
page_header('Create an account');
?>
<div class="auth-wrapper"><section class="panel auth-panel">
    <p class="eyebrow">START HERE</p><h2>Join SplitCircle</h2>
    <p class="muted">Your own account is required for honest debt approvals.</p>
    <form method="post" action="<?= e(url('handlers/auth.php')) ?>" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="register">
        <label>Username <input name="username" pattern="[a-zA-Z0-9_]{3,40}" required maxlength="40" autocomplete="username" placeholder="alex_25"></label>
        <label>Display name <input name="display_name" maxlength="100" required autocomplete="name" placeholder="Alex Perera"></label>
        <label>Password <input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
        <label>Confirm password <input type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Create account</button>
    </form>
    <p class="muted">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a>.</p>
</section></div>
<?php page_footer(); ?>
