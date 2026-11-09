<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login(); $uid = (int)$user['id'];
try { $gid = request_id('id'); $group = group_access($gid, $uid); }
catch (DomainException $e) { http_response_code(403); exit('Group access denied. <a href="' . e(url('groups.php')) . '">My groups</a>'); }
$owner = $group['role'] === 'owner'; $archived = $group['archived_at'] !== null;
$members = fetch_all('SELECT u.id,u.display_name,u.username,gm.role,gm.status FROM group_members gm JOIN users u ON u.id=gm.user_id WHERE gm.group_id=? ORDER BY FIELD(gm.status,"active","pending","declined","removed"),u.display_name', [$gid]);
$activeCount = count(array_filter($members, fn($m) => $m['status'] === 'active'));
// Only show this signed-in member's relationships, not other members' transactions.
$debts = array_values(array_filter(group_debts($gid), static fn(array $d): bool =>
    (int)$d['debtor_id'] === $uid || (int)$d['creditor_id'] === $uid));
$allBalances = member_balances($gid);
$balances = isset($allBalances[$uid]) ? [$uid => $allBalances[$uid]] : [];
$totalOutstanding = 0; $totalPaid = 0; $pending = 0; $settled = 0;
foreach ($debts as $d) {
    if ($d['approval_status'] === 'pending') $pending++;
    if ($d['approval_status'] !== 'accepted') continue;
    $totalOutstanding += $d['remaining_cents']; $totalPaid += db_cents((string)$d['total_paid']);
    if ($d['remaining_cents'] === 0) $settled++;
}
page_header($group['name'], $user);
?>
<p class="muted"><?= e($group['description'] ?? '') ?></p>
<div class="toolbar"><a class="btn btn-secondary" href="<?= e(url('groups.php')) ?>">← All groups</a>
<?php if (!$archived): ?><a class="btn btn-primary" href="<?= e(url('debt-new.php?group_id=' . $gid)) ?>">+ New debt request</a><?php else: ?><?= status_badge('archived') ?><?php endif; ?></div>
<div class="stat-grid small-stats"><article class="stat"><span class="muted">Outstanding</span><strong><?= e(money($totalOutstanding)) ?></strong></article><article class="stat"><span class="muted">Confirmed repayments</span><strong><?= e(money($totalPaid)) ?></strong></article><article class="stat"><span class="muted">Pending debts</span><strong><?= $pending ?></strong></article><article class="stat"><span class="muted">Settled debts</span><strong><?= $settled ?></strong></article></div>
<div class="columns">
<section class="panel"><h2>Members (<?= $activeCount ?> active)</h2>
<?php foreach ($members as $member): ?><div class="keyrow"><div><strong><?= e($member['display_name']) ?></strong> <span class="muted">@<?= e($member['username']) ?></span><div class="small-muted"><?= e(ucfirst($member['role'])) ?> · <?= e($member['status']) ?></div></div>
<div class="actions"><?php if ($owner && !$archived && $member['status'] === 'pending'): ?>
<form method="post" action="<?= e(url('handlers/groups.php')) ?>" data-confirm="Withdraw this invitation?"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>"><input type="hidden" name="target_user_id" value="<?= (int)$member['id'] ?>"><button class="btn btn-small btn-quiet" name="action" value="withdraw_invite">Withdraw invitation</button></form>
<?php endif; ?>
<?php if ($owner && !$archived && $member['status'] === 'active' && $member['role'] !== 'owner'): ?>
<form method="post" action="<?= e(url('handlers/groups.php')) ?>" data-confirm="Remove this member? This is allowed only if obligations are cleared."><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>"><input type="hidden" name="target_user_id" value="<?= (int)$member['id'] ?>"><button class="btn btn-small btn-quiet" name="action" value="remove_member">Remove</button></form>
<?php endif; ?><?= status_badge($member['status']) ?></div></div><?php endforeach; ?>
<?php if ($owner && !$archived): ?><hr><h3>Invite a registered friend</h3><form class="inline-form" method="post" action="<?= e(url('handlers/groups.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><input type="hidden" name="group_id" value="<?= $gid ?>"><label class="grow">Username <input name="username" required maxlength="40" placeholder="friend_username"></label><button class="btn btn-primary" type="submit">Invite</button></form><?php endif; ?>
<?php if (!$owner && !$archived): ?><form method="post" action="<?= e(url('handlers/groups.php')) ?>" data-confirm="Leave the group? You must first resolve outstanding debts."><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>"><button class="btn btn-secondary btn-small" name="action" value="leave">Leave group</button></form><?php endif; ?>
</section>
<section class="panel"><h2>My balance</h2><p class="muted">Net = owed to member − member owes.</p>
<?php foreach ($balances as $b): ?><div class="keyrow"><span><?= e($b['user']['display_name']) ?><small>Owes <?= e(money($b['owes'])) ?> · Owed <?= e(money($b['owed'])) ?></small></span><strong class="<?= $b['owed']-$b['owes'] < 0 ? 'danger':'success' ?>"><?= e(money($b['owed']-$b['owes'])) ?></strong></div><?php endforeach; ?>
</section>
</div>
<section class="panel"><div class="section-title"><h2>My debts and requests</h2><?php if (!$archived): ?><a class="btn btn-small btn-secondary" href="<?= e(url('debt-new.php?group_id=' . $gid)) ?>">New request</a><?php endif; ?></div>
<?php if (!$debts): ?><p class="empty">No debt requests in this group yet.</p><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Reason</th><th>Debtor → Creditor</th><th>Original</th><th>Outstanding</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($debts as $d): ?><tr><td><?= e($d['description']) ?></td><td><?= e($d['debtor_name']) ?> → <?= e($d['creditor_name']) ?></td><td><?= e(money(db_cents((string)$d['amount']))) ?></td><td><?= $d['approval_status'] === 'accepted' ? e(money($d['remaining_cents'])) : '—' ?></td><td><?= status_badge($d['approval_status'] === 'accepted' ? strtolower(str_replace(' ', '_', repayment_state($d))) : $d['approval_status']) ?></td><td><a href="<?= e(url('debt.php?id=' . $d['id'])) ?>">Details</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php if ($owner && !$archived): ?><section class="panel"><h2>Group administration</h2><div class="columns"><form method="post" action="<?= e(url('handlers/groups.php')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>"><input type="hidden" name="action" value="edit"><label>Group name <input required maxlength="100" name="name" value="<?= e($group['name']) ?>"></label><label>Description <textarea name="description" maxlength="500" rows="2"><?= e($group['description'] ?? '') ?></textarea></label><button class="btn btn-secondary" type="submit">Save changes</button></form>
<div><p class="hint">Archiving makes the group read-only. All financial requests must be resolved first.</p><form method="post" action="<?= e(url('handlers/groups.php')) ?>" data-confirm="Archive this group? It will become read-only."><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>"><button class="btn btn-secondary" name="action" value="archive">Archive group</button></form><p class="hint">Unused groups containing only the creator can be permanently deleted.</p><form method="post" action="<?= e(url('handlers/groups.php')) ?>" data-confirm="Permanently delete this unused group?"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= $gid ?>"><button class="btn btn-danger" name="action" value="delete_empty">Delete empty group</button></form></div></div></section><?php endif; ?>
<?php page_footer(); ?>
