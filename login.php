<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
if (current_user()) redirect('dashboard.php');
page_header('Welcome back');
?>
<div class="auth-wrapper"><section class="panel auth-panel">
    <p class="eyebrow">SMART GROUP DEBT TRACKING</p>
    <h2>Sign in to SplitCircle</h2>
    <p class="muted">Keep the numbers clear and the friendships strong.</p>
    <form method="post" action="<?= e(url('handlers/auth.php')) ?>" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="login">
        <label>Username <input name="username" required maxlength="40" autocomplete="username"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-primary" type="submit">Sign in</button>
    </form>
    <p class="muted">New to SplitCircle? <a href="<?= e(url('register.php')) ?>">Create your account</a>.</p>
</section></div>
<?php page_footer(); ?>
