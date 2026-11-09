<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$uid = (int)$user['id'];
$groups = fetch_all('SELECT g.id, g.name FROM `groups` g JOIN group_members gm ON gm.group_id=g.id WHERE gm.user_id=? AND gm.status="active" ORDER BY g.name', [$uid]);
$actors = fetch_all('SELECT DISTINCT u.id, u.display_name, u.username FROM activity_logs l JOIN users u ON u.id=l.actor_id JOIN group_members gm ON gm.group_id=l.group_id AND gm.user_id=? AND gm.status="active" ORDER BY u.display_name', [$uid]);
$gid = 0;
$actorId = 0;
try {
    if (!empty($_GET['group_id'])) {
        $gid = request_id('group_id');
        group_access($gid, $uid);
    }
    if (!empty($_GET['member_id'])) {
        $actorId = request_id('member_id');
        if (!in_array($actorId, array_map(fn(array $x): int => (int)$x['id'], $actors), true)) {
            throw new DomainException('Selected member is not in your accessible history.');
        }
    }
} catch (DomainException $e) {
    http_response_code(403);
    exit('History filter access denied.');
}
$types = [
    'group_created', 'group_updated', 'group_archived', 'member_invited', 'invitation_accepted',
    'invitation_declined', 'invitation_withdrawn', 'member_removed', 'account_left_group',
    'debt_requested', 'debt_edited', 'debt_accepted', 'debt_rejected', 'debt_cancelled',
    'repayment_submitted', 'repayment_edited', 'repayment_confirmed', 'repayment_rejected', 'repayment_withdrawn',
    'void_requested', 'void_accepted', 'void_rejected', 'void_cancelled'
];
$type = (string)($_GET['type'] ?? '');
if ($type !== '' && !in_array($type, $types, true)) $type = '';
$debtStatuses = ['pending', 'accepted', 'rejected', 'cancelled', 'voided'];
$debtStatus = (string)($_GET['debt_status'] ?? '');
if ($debtStatus !== '' && !in_array($debtStatus, $debtStatuses, true)) $debtStatus = '';

function requested_date(string $key): string
{
    $input = $_GET[$key] ?? '';
    if (!is_string($input) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $input, $m)
        || !checkdate((int)($m[2] ?? 0), (int)($m[3] ?? 0), (int)($m[1] ?? 0))) {
        return '';
    }
    return $input;
}
$start = requested_date('from');
$end = requested_date('to');
$sql = 'SELECT l.*, g.name AS group_name, u.display_name AS actor_name,
        effective_debt.approval_status AS related_debt_status
    FROM activity_logs l
    JOIN `groups` g ON g.id=l.group_id
    JOIN users u ON u.id=l.actor_id
    JOIN group_members gm ON gm.group_id=l.group_id AND gm.user_id=? AND gm.status="active"
    LEFT JOIN debts direct_debt ON direct_debt.id=l.entity_id AND l.entity_type="debt"
    LEFT JOIN repayments related_payment ON related_payment.id=l.entity_id AND l.entity_type="repayment"
    LEFT JOIN debt_void_requests related_void ON related_void.id=l.entity_id AND l.entity_type="void_request"
    LEFT JOIN debts effective_debt ON effective_debt.id=COALESCE(direct_debt.id, related_payment.debt_id, related_void.debt_id)
    WHERE (l.entity_type NOT IN ("debt", "repayment", "void_request")
        OR effective_debt.debtor_id=? OR effective_debt.creditor_id=?)';
$params = [$uid, $uid, $uid];
if ($gid) { $sql .= ' AND l.group_id=?'; $params[] = $gid; }
if ($actorId) { $sql .= ' AND l.actor_id=?'; $params[] = $actorId; }
if ($type !== '') { $sql .= ' AND l.action_type=?'; $params[] = $type; }
if ($debtStatus !== '') { $sql .= ' AND effective_debt.approval_status=?'; $params[] = $debtStatus; }
if ($start !== '') { $sql .= ' AND l.created_at>=?'; $params[] = $start . ' 00:00:00'; }
if ($end !== '') { $sql .= ' AND l.created_at<DATE_ADD(?,INTERVAL 1 DAY)'; $params[] = $end; }
$sql .= ' ORDER BY l.created_at DESC, l.id DESC LIMIT 200';
$logs = fetch_all($sql, $params);
page_header('Activity history', $user);
?>
<section class="panel"><h2>Filter activity</h2>
<form class="filters" method="get" action="<?= e(url('history.php')) ?>">
<label>Group<select name="group_id"><option value="">All my groups</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$g['id']===$gid?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></label>
<label>Member who performed action<select name="member_id"><option value="">Any member</option><?php foreach ($actors as $actor): ?><option value="<?= (int)$actor['id'] ?>" <?= (int)$actor['id']===$actorId?'selected':'' ?>><?= e($actor['display_name'] . ' (@' . $actor['username'] . ')') ?></option><?php endforeach; ?></select></label>
<label>Transaction / action<select name="type"><option value="">All actions</option><?php foreach ($types as $option): ?><option value="<?= e($option) ?>" <?= $option===$type?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$option))) ?></option><?php endforeach; ?></select></label>
<label>Related debt approval status<select name="debt_status"><option value="">Any status</option><?php foreach ($debtStatuses as $status): ?><option value="<?= e($status) ?>" <?= $status===$debtStatus?'selected':'' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
<label>From date<input type="date" name="from" value="<?= e($start) ?>"></label>
<label>To date<input type="date" name="to" value="<?= e($end) ?>"></label>
<div class="actions"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-secondary" href="<?= e(url('history.php')) ?>">Clear</a></div>
</form><p class="hint">Approval-status filter uses the debt's current status. Past events remain visible even if the status later changes.</p></section>
<section class="panel"><h2>Recent events</h2><p class="muted">Showing at most 200 records, newest first. Financial history is retained.</p>
<?php if (!$logs): ?><p class="empty">No activity found for these filters.</p><?php endif; ?>
<?php foreach ($logs as $log): ?><div class="history-row"><div class="timeline-dot"></div><div class="grow"><strong><?= e(ucwords(str_replace('_', ' ', $log['action_type']))) ?></strong><?php if ($log['related_debt_status'] !== null): ?> <?= status_badge((string)$log['related_debt_status']) ?><?php endif; ?><p><?= e($log['detail']) ?></p><small class="muted"><?= e($log['group_name']) ?> · <?= e($log['actor_name']) ?> · <?= e($log['created_at']) ?></small></div></div><?php endforeach; ?>
</section>
<?php page_footer(); ?>
