<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
page_header('Your profile', $user);
?>
<div class="columns"><section class="panel"><h2>Account details</h2><p><strong>Username:</strong> @<?= e($user['username']) ?></p>
<form method="post" action="<?= e(url('handlers/auth.php')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><label>Display name <input name="display_name" value="<?= e($user['display_name']) ?>" maxlength="100" required></label><button class="btn btn-primary" type="submit">Save profile</button></form></section>
<section class="panel"><h2>Change password</h2><form method="post" action="<?= e(url('handlers/auth.php')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="password"><label>Current password <input type="password" name="old_password" required autocomplete="current-password"></label><label>New password <input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label><label>Confirm new password <input type="password" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password"></label><button class="btn btn-primary" type="submit">Change password</button></form></section></div>
<section class="panel"><h2>Deactivate account</h2><p class="muted">This disables login and keeps financial history. You must settle debts and archive groups you own first.</p>
<form method="post" action="<?= e(url('handlers/auth.php')) ?>" class="inline-form" data-confirm="Deactivate your account? This cannot be undone in the interface.">
<?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><label class="grow">Confirm password <input type="password" name="password" required autocomplete="current-password"></label><button class="btn btn-danger" type="submit">Deactivate my account</button></form></section>
<?php page_footer(); ?>
