<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login(); $uid = (int)$user['id'];
$inDebts = fetch_all('SELECT d.id,d.description,d.amount,d.created_at,g.name AS group_name,du.display_name AS debtor_name,cu.display_name AS creditor_name
 FROM debts d JOIN `groups` g ON g.id=d.group_id JOIN group_members gm ON gm.group_id=g.id AND gm.user_id=? AND gm.status="active"
 JOIN users du ON du.id=d.debtor_id JOIN users cu ON cu.id=d.creditor_id
 WHERE d.approval_status="pending" AND d.created_by<>? AND (d.debtor_id=? OR d.creditor_id=?) AND g.archived_at IS NULL ORDER BY d.created_at DESC', [$uid,$uid,$uid,$uid]);
$outDebts = fetch_all('SELECT d.id,d.description,d.amount,d.created_at,g.name AS group_name FROM debts d JOIN `groups` g ON g.id=d.group_id
 WHERE d.created_by=? AND d.approval_status="pending" AND g.archived_at IS NULL ORDER BY d.created_at DESC', [$uid]);
$inRepays = fetch_all('SELECT r.id, r.amount, r.created_at, d.id AS debt_id,d.description,g.name AS group_name,u.display_name AS debtor_name
 FROM repayments r JOIN debts d ON d.id=r.debt_id JOIN `groups` g ON g.id=d.group_id JOIN group_members gm ON gm.group_id=d.group_id AND gm.user_id=? AND gm.status="active" JOIN users u ON u.id=d.debtor_id
 WHERE r.status="pending" AND d.creditor_id=? AND g.archived_at IS NULL ORDER BY r.created_at DESC', [$uid,$uid]);
$inVoids = fetch_all('SELECT v.id,d.id AS debt_id,d.description,g.name AS group_name,u.display_name AS requested_name
 FROM debt_void_requests v JOIN debts d ON d.id=v.debt_id JOIN `groups` g ON g.id=d.group_id JOIN users u ON u.id=v.requested_by
 JOIN group_members gm ON gm.group_id=d.group_id AND gm.user_id=? AND gm.status="active"
 WHERE v.status="pending" AND v.requested_by<>? AND (d.debtor_id=? OR d.creditor_id=?) AND g.archived_at IS NULL ORDER BY v.created_at DESC', [$uid,$uid,$uid,$uid]);
page_header('Requests inbox', $user);
?>
<section class="panel"><h2>Incoming debt approvals (<?= count($inDebts) ?>)</h2>
<?php if (!$inDebts): ?><p class="empty">No debt requests to approve.</p><?php endif; ?>
<?php foreach ($inDebts as $d): ?><div class="request-card"><div><strong><?= e($d['description']) ?></strong><p class="muted"><?= e($d['group_name']) ?> · <?= e($d['debtor_name']) ?> owes <?= e($d['creditor_name']) ?> · <?= e(money(db_cents((string)$d['amount']))) ?></p></div><div class="actions">
<form method="post" action="<?= e(url('handlers/debts.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-primary btn-small" name="action" value="accept">Accept</button><button class="btn btn-danger btn-small" name="action" value="reject">Reject</button></form><a href="<?= e(url('debt.php?id='.$d['id'])) ?>">Details</a></div></div><?php endforeach; ?></section>
<section class="panel"><h2>Incoming repayment confirmations (<?= count($inRepays) ?>)</h2>
<?php if (!$inRepays): ?><p class="empty">No repayment claims to confirm.</p><?php endif; ?>
<?php foreach ($inRepays as $r): ?><div class="request-card"><div><strong><?= e(money(db_cents((string)$r['amount']))) ?> from <?= e($r['debtor_name']) ?></strong><p class="muted"><?= e($r['group_name']) ?> · <?= e($r['description']) ?></p></div><div class="actions"><form method="post" action="<?= e(url('handlers/repayments.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= (int)$r['debt_id'] ?>"><input type="hidden" name="repayment_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-primary btn-small" name="action" value="confirm">Confirm</button><button class="btn btn-danger btn-small" name="action" value="reject">Reject</button></form><a href="<?= e(url('debt.php?id='.$r['debt_id'])) ?>">Details</a></div></div><?php endforeach; ?></section>
<section class="panel"><h2>Incoming cancellation requests (<?= count($inVoids) ?>)</h2>
<?php if (!$inVoids): ?><p class="empty">No cancellations awaiting your approval.</p><?php endif; ?>
<?php foreach ($inVoids as $v): ?><div class="request-card"><div><strong><?= e($v['description']) ?></strong><p class="muted"><?= e($v['group_name']) ?> · Requested by <?= e($v['requested_name']) ?></p></div><div class="actions"><form method="post" action="<?= e(url('handlers/voids.php')) ?>"><?= csrf_field() ?><input type="hidden" name="void_id" value="<?= (int)$v['id'] ?>"><button class="btn btn-primary btn-small" name="action" value="accept">Approve</button><button class="btn btn-danger btn-small" name="action" value="reject">Reject</button></form><a href="<?= e(url('debt.php?id='.$v['debt_id'])) ?>">Details</a></div></div><?php endforeach; ?></section>
<section class="panel"><h2>Your outgoing debt requests (<?= count($outDebts) ?>)</h2>
<?php if (!$outDebts): ?><p class="empty">No outgoing requests awaiting approval.</p><?php endif; ?>
<?php foreach ($outDebts as $d): ?><div class="request-card"><div><strong><?= e($d['description']) ?></strong><p class="muted"><?= e($d['group_name']) ?> · <?= e(money(db_cents((string)$d['amount']))) ?> · Awaiting the other person</p></div><a href="<?= e(url('debt.php?id='.$d['id'])) ?>">View or edit</a></div><?php endforeach; ?></section>
<?php page_footer(); ?>
