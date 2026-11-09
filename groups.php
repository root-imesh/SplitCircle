<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login(); $uid = (int)$user['id'];
$groups = fetch_all('SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id=g.id WHERE gm.user_id=? AND gm.status="active" ORDER BY g.created_at DESC', [$uid]);
$invitations = fetch_all('SELECT g.id, g.name, gm.created_at, u.display_name AS inviter FROM group_members gm JOIN `groups` g ON g.id=gm.group_id LEFT JOIN users u ON u.id=gm.invited_by WHERE gm.user_id=? AND gm.status="pending" AND g.archived_at IS NULL ORDER BY gm.created_at DESC', [$uid]);
page_header('My groups', $user);
?>
<div class="columns">
<section class="panel"><h2>Your groups</h2><p class="muted">Invite registered members; invitations require acceptance.</p>
<?php if (!$groups): ?><p class="empty">You have not joined any groups.</p><?php else: ?><div class="item-list">
<?php foreach ($groups as $g): ?><a class="item-link" href="<?= e(url('group.php?id=' . $g['id'])) ?>"><span class="group-link-details"><strong><?= e($g['name']) ?></strong><small><?= e($g['role']) ?> <?= $g['archived_at'] ? '· archived' : '' ?></small></span><span class="chevron">›</span></a><?php endforeach; ?>
</div><?php endif; ?></section>
<section class="panel"><h2>Create a group</h2>
<form class="form-grid" method="post" action="<?= e(url('handlers/groups.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="create">
<label>Group name <input name="name" maxlength="100" required placeholder="Campus Friends"></label>
<label>Description (optional) <textarea name="description" maxlength="500" rows="3" placeholder="Our shared expenses"></textarea></label>
<button class="btn btn-primary" type="submit">Create group</button></form></section>
</div>
<section class="panel"><h2>Group invitations</h2>
<?php if (!$invitations): ?><p class="empty">No pending invitations.</p><?php endif; ?>
<?php foreach ($invitations as $invite): ?><div class="request-card"><div><strong><?= e($invite['name']) ?></strong><p class="muted">Invited by <?= e($invite['inviter'] ?? 'a group member') ?></p></div>
<div class="actions"><form method="post" action="<?= e(url('handlers/groups.php')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int)$invite['id'] ?>"><button class="btn btn-primary btn-small" name="action" value="accept_invite">Accept</button></form>
<form method="post" action="<?= e(url('handlers/groups.php')) ?>"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int)$invite['id'] ?>"><button class="btn btn-secondary btn-small" name="action" value="decline_invite">Decline</button></form></div></div><?php endforeach; ?>
</section>
<?php page_footer(); ?>
