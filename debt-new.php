<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login(); $uid = (int)$user['id'];
try { $gid = request_id('group_id'); $group = group_access($gid, $uid, false); }
catch (DomainException $e) { http_response_code(403); exit('Group access denied.'); }
$members = fetch_all('SELECT u.id, u.display_name, u.username FROM users u JOIN group_members gm ON gm.user_id=u.id WHERE gm.group_id=? AND gm.status="active" ORDER BY u.display_name', [$gid]);
page_header('New debt request', $user);
?>
<div class="panel max-panel"><h2><?= e($group['name']) ?></h2><p class="muted">The other participant must accept before this becomes an active debt. You must be one of the two participants.</p>
<form method="post" action="<?= e(url('handlers/debts.php')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="group_id" value="<?= $gid ?>">
<label>Who owes money? (Debtor)<select name="debtor_id" required><?php foreach ($members as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$member['id']===$uid ? 'selected' : '' ?>><?= e($member['display_name'] . ' (@' . $member['username'] . ')') ?></option><?php endforeach; ?></select></label>
<label>Who should receive money? (Creditor)<select name="creditor_id" required><?php foreach ($members as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$member['id']!==$uid ? 'selected' : '' ?>><?= e($member['display_name'] . ' (@' . $member['username'] . ')') ?></option><?php endforeach; ?></select></label>
<label>Amount (LKR)<input type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" placeholder="2000.00" required></label>
<label>Reason <input name="description" required maxlength="255" placeholder="Shared dinner"></label>
<div class="actions"><button class="btn btn-primary" type="submit">Send approval request</button><a class="btn btn-secondary" href="<?= e(url('group.php?id='.$gid)) ?>">Cancel</a></div></form></div>
<?php page_footer(); ?>
