<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login(); $uid = (int)$user['id'];
try {
    $id = request_id('id');
    $debt = debt_row($id);
    $group = group_access((int)$debt['group_id'], $uid);
    if ($uid !== (int)$debt['debtor_id'] && $uid !== (int)$debt['creditor_id']) {
        throw new DomainException('Only the debt participants may view its details.');
    }
}
catch (DomainException $e) { http_response_code(403); exit('Debt not found or access denied. <a href="' . e(url('groups.php')) . '">My groups</a>'); }
$archived = $group['archived_at'] !== null;
$creator = $uid === (int)$debt['created_by'];
$isDebtor = $uid === (int)$debt['debtor_id'];
$isCreditor = $uid === (int)$debt['creditor_id'];
$counterparty = ($isDebtor || $isCreditor) && !$creator;
$repayments = fetch_all('SELECT r.*, u.display_name AS submitted_name FROM repayments r JOIN users u ON u.id=r.submitted_by WHERE debt_id=? ORDER BY r.created_at DESC, r.id DESC', [$id]);
$voidRequests = fetch_all('SELECT v.*, u.display_name AS requester_name FROM debt_void_requests v JOIN users u ON u.id=v.requested_by WHERE debt_id=? ORDER BY v.created_at DESC, v.id DESC', [$id]);
page_header('Debt details', $user);
?>
<div class="toolbar"><a class="btn btn-secondary" href="<?= e(url('group.php?id='.$debt['group_id'])) ?>">← <?= e($group['name']) ?></a><?= status_badge($debt['approval_status']) ?><?= $debt['approval_status'] === 'accepted' ? status_badge(strtolower(str_replace(' ','_',repayment_state($debt)))) : '' ?></div>
<section class="panel"><div class="section-title"><div><p class="eyebrow">DEBT #<?= (int)$id ?></p><h2><?= e($debt['description']) ?></h2></div><div class="large-money"><?= e(money(db_cents((string)$debt['amount']))) ?></div></div>
<div class="columns"><div class="keyrow"><span>Debtor</span><strong><?= e($debt['debtor_name']) ?></strong></div><div class="keyrow"><span>Creditor</span><strong><?= e($debt['creditor_name']) ?></strong></div></div>
<div class="stat-grid small-stats"><article class="stat"><span class="muted">Approved amount</span><strong><?= e(money(db_cents((string)$debt['amount']))) ?></strong></article><article class="stat"><span class="muted">Confirmed repayments</span><strong><?= e(money(db_cents((string)$debt['total_paid']))) ?></strong></article><article class="stat"><span class="muted">Remaining</span><strong><?= $debt['approval_status'] === 'accepted' ? e(money($debt['remaining_cents'])) : 'Not active' ?></strong></article><article class="stat"><span class="muted">Created</span><strong class="date-text"><?= e($debt['created_at']) ?></strong></article></div>
<?php if ($debt['approval_status'] !== 'accepted'): ?><p class="hint">Only accepted debts affect outstanding balances. Cancelled, rejected, and voided debts remain in the history but do not affect balances.</p><?php endif; ?></section>
<?php if (!$archived && $debt['approval_status'] === 'pending'): ?>
<section class="panel"><h2>Debt approval</h2>
<?php if ($creator): ?><p class="muted">Waiting for the other participant. You can edit or withdraw this pending request.</p>
<form method="post" action="<?= e(url('handlers/debts.php')) ?>" class="form-grid max-form"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><input type="hidden" name="action" value="edit_pending"><label>Amount (LKR)<input type="number" name="amount" required min="0.01" step="0.01" max="9999999999.99" value="<?= e($debt['amount']) ?>"></label><label>Reason<input name="description" maxlength="255" required value="<?= e($debt['description']) ?>"></label><button class="btn btn-secondary" type="submit">Update pending request</button></form>
<form method="post" action="<?= e(url('handlers/debts.php')) ?>" data-confirm="Withdraw this debt request?"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><button class="btn btn-danger" name="action" value="cancel_pending">Cancel request</button></form>
<?php elseif ($counterparty): ?><p class="muted">This request needs your decision. It will become active only if you accept.</p><div class="actions">
<form method="post" action="<?= e(url('handlers/debts.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><button class="btn btn-primary" name="action" value="accept">Accept debt</button></form>
<form method="post" action="<?= e(url('handlers/debts.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><button class="btn btn-danger" name="action" value="reject">Reject debt</button></form></div>
<?php else: ?><p class="muted">Waiting for the involved members to decide.</p><?php endif; ?>
</section><?php endif; ?>
<?php if ($debt['approval_status'] === 'accepted'): ?>
<div class="columns"><section class="panel"><h2>Repay this debt</h2>
<?php if ($archived): ?><p class="muted">Archived groups are read-only.</p>
<?php elseif ($debt['remaining_cents'] <= 0): ?><p class="success">This debt is fully settled.</p>
<?php elseif ($isDebtor): ?><p class="muted">Submit the amount you actually paid to the creditor. Their confirmation is required.</p>
<form class="form-grid" method="post" action="<?= e(url('handlers/repayments.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="submit"><input type="hidden" name="debt_id" value="<?= $id ?>"><label>Repayment (LKR)<input type="number" name="amount" min="0.01" max="<?= e(cents_sql($debt['remaining_cents'])) ?>" step="0.01" required placeholder="750.00"></label><button class="btn btn-primary" type="submit">Submit repayment claim</button></form>
<?php elseif ($isCreditor): ?><p class="muted">Only the debtor can submit a repayment claim. You can confirm it below.</p>
<?php else: ?><p class="muted">Only the involved members can submit or confirm repayments.</p><?php endif; ?></section>
<section class="panel"><h2>Cancel an accepted debt</h2><p class="muted">An unpaid accepted debt can be voided only with the other participant's approval. Debts with confirmed repayments cannot be voided in this MVP.</p>
<?php if (!$archived && ($isDebtor || $isCreditor) && db_cents((string)$debt['total_paid']) === 0): ?>
<form method="post" action="<?= e(url('handlers/debts.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><button class="btn btn-secondary" name="action" value="request_void">Request mutual cancellation</button></form>
<?php endif; ?></section></div>
<section class="panel"><h2>Repayment history</h2>
<?php if (!$repayments): ?><p class="empty">No repayment claims yet.</p><?php endif; ?>
<?php foreach ($repayments as $p): ?><div class="request-card"><div><strong><?= e(money(db_cents((string)$p['amount']))) ?></strong> <?= status_badge($p['status']) ?><p class="muted">Submitted by <?= e($p['submitted_name']) ?> · <?= e($p['created_at']) ?></p></div>
<?php if (!$archived && $p['status'] === 'pending' && $isCreditor): ?><div class="actions"><form method="post" action="<?= e(url('handlers/repayments.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><input type="hidden" name="repayment_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-primary btn-small" name="action" value="confirm">Confirm</button></form><form method="post" action="<?= e(url('handlers/repayments.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><input type="hidden" name="repayment_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-danger btn-small" name="action" value="reject">Reject</button></form></div>
<?php elseif (!$archived && $p['status'] === 'pending' && $isDebtor): ?><div class="stacked-actions"><form method="post" action="<?= e(url('handlers/repayments.php')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><input type="hidden" name="repayment_id" value="<?= (int)$p['id'] ?>"><input type="number" name="amount" value="<?= e($p['amount']) ?>" min="0.01" max="9999999999.99" step="0.01" required aria-label="Correct repayment amount"><button class="btn btn-small btn-secondary" name="action" value="edit_pending">Edit</button></form><form method="post" action="<?= e(url('handlers/repayments.php')) ?>"><?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $id ?>"><input type="hidden" name="repayment_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-quiet btn-small" name="action" value="withdraw">Withdraw</button></form></div><?php endif; ?>
</div><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($voidRequests): ?>
<section class="panel"><h2>Cancellation history</h2>
<?php if (!$voidRequests): ?><p class="empty">No cancellation requests.</p><?php endif; ?>
<?php foreach ($voidRequests as $v): ?><div class="request-card"><div><strong>Cancellation requested by <?= e($v['requester_name']) ?></strong> <?= status_badge($v['status']) ?><p class="muted"><?= e($v['created_at']) ?></p></div>
<?php if (!$archived && $v['status'] === 'pending' && ($isDebtor || $isCreditor)): ?><div class="actions"><form method="post" action="<?= e(url('handlers/voids.php')) ?>"><?= csrf_field() ?><input type="hidden" name="void_id" value="<?= (int)$v['id'] ?>"><?php if ($uid === (int)$v['requested_by']): ?><button class="btn btn-secondary btn-small" name="action" value="cancel">Withdraw</button><?php else: ?><button class="btn btn-primary btn-small" name="action" value="accept">Approve</button><button class="btn btn-danger btn-small" name="action" value="reject">Reject</button><?php endif; ?></form></div><?php endif; ?>
</div><?php endforeach; ?></section>
<?php endif; ?>
<?php page_footer(); ?>
