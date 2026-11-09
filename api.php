<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_reply(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}
function state_payload(int $actor): array {
    $user = current_user();
    if (!$user) throw new DomainException('Please log in again.');
    $groupRows = fetch_all('SELECT g.id, g.name, g.description, g.created_by
        FROM `groups` g JOIN group_members gm ON gm.group_id=g.id
        WHERE gm.user_id=? AND gm.status="active" AND g.archived_at IS NULL
        ORDER BY g.created_at DESC, g.id DESC', [$actor]);
    $groupIds = array_map(static fn($g)=>(int)$g['id'], $groupRows);
    $groups = []; $people = [];
    $people[(string)$actor] = ['name'=>$user['display_name'], 'username'=>$user['username']];
    foreach ($groupRows as $g) {
        $members = fetch_all('SELECT u.id, u.display_name, u.username FROM group_members gm
            JOIN users u ON u.id=gm.user_id WHERE gm.group_id=? AND gm.status="active"
            ORDER BY gm.joined_at, gm.id', [(int)$g['id']]);
        $ids=[];
        foreach ($members as $m) {
            $id=(string)$m['id']; $ids[]=$id;
            $people[$id]=['name'=>$m['display_name'], 'username'=>$m['username']];
        }
        $groups[] = ['id'=>(int)$g['id'], 'name'=>$g['name'], 'description'=>$g['description'] ?: '',
            'owner'=>(string)$g['created_by'], 'icon'=>'graduation-cap', 'members'=>$ids];
    }
    $debts=[]; $repayments=[]; $activity=[];
    if ($groupIds) {
        $q = implode(',', array_fill(0, count($groupIds), '?'));
        $rows = fetch_all('SELECT d.*, COALESCE(p.total_paid,0.00) confirmed,
            du.display_name debtor_name,du.username debtor_username,
            cu.display_name creditor_name,cu.username creditor_username,
            au.display_name creator_name,au.username creator_username
            FROM debts d
            JOIN users du ON du.id=d.debtor_id JOIN users cu ON cu.id=d.creditor_id
            JOIN users au ON au.id=d.created_by
            LEFT JOIN (SELECT debt_id,SUM(amount) total_paid FROM repayments WHERE status="confirmed" GROUP BY debt_id) p ON p.debt_id=d.id
            WHERE d.group_id IN (' . $q . ') AND (d.debtor_id=? OR d.creditor_id=?)
            ORDER BY d.created_at DESC,d.id DESC', array_merge($groupIds, [$actor, $actor]));
        foreach ($rows as $r) {
            $people[(string)$r['debtor_id']] = ['name'=>$r['debtor_name'], 'username'=>$r['debtor_username']];
            $people[(string)$r['creditor_id']] = ['name'=>$r['creditor_name'], 'username'=>$r['creditor_username']];
            $people[(string)$r['created_by']] = ['name'=>$r['creator_name'], 'username'=>$r['creator_username']];
            $debts[] = ['id'=>(int)$r['id'], 'group'=>(int)$r['group_id'], 'debtor'=>(string)$r['debtor_id'],
                'creditor'=>(string)$r['creditor_id'], 'createdBy'=>(string)$r['created_by'],
                'amount'=>(float)$r['amount'], 'confirmed'=>(float)$r['confirmed'],
                'status'=>$r['approval_status'], 'description'=>$r['description']];
        }
        $paymentRows = fetch_all('SELECT p.* FROM repayments p JOIN debts d ON d.id=p.debt_id
            WHERE d.group_id IN (' . $q . ') AND (d.debtor_id=? OR d.creditor_id=?)
            ORDER BY p.created_at DESC,p.id DESC', array_merge($groupIds, [$actor, $actor]));
        foreach ($paymentRows as $r) {
            $repayments[]=['id'=>(int)$r['id'], 'debtId'=>(int)$r['debt_id'], 'submittedBy'=>(string)$r['submitted_by'],
                'amount'=>(float)$r['amount'], 'status'=>$r['status'], 'note'=>$r['note'] ?? ''];
        }
        // Keep non-financial group activity visible, but financial events only for debts involving this user.
        $logRows=fetch_all('SELECT l.*, g.name group_name, u.display_name actor_name, u.username actor_username
            FROM activity_logs l JOIN `groups` g ON g.id=l.group_id JOIN users u ON u.id=l.actor_id
            LEFT JOIN debts log_debt ON l.entity_type="debt" AND log_debt.id=l.entity_id
            LEFT JOIN repayments log_payment ON l.entity_type="repayment" AND log_payment.id=l.entity_id
            LEFT JOIN debt_void_requests log_void ON l.entity_type="void_request" AND log_void.id=l.entity_id
            LEFT JOIN debts related_debt ON related_debt.id=COALESCE(log_debt.id,log_payment.debt_id,log_void.debt_id)
            WHERE l.group_id IN (' . $q . ')
              AND (l.entity_type NOT IN ("debt","repayment","void_request")
                   OR related_debt.debtor_id=? OR related_debt.creditor_id=?)
            ORDER BY l.created_at DESC,l.id DESC LIMIT 80', array_merge($groupIds, [$actor, $actor]));
        foreach ($logRows as $r) {
            $people[(string)$r['actor_id']] = ['name'=>$r['actor_name'], 'username'=>$r['actor_username']];
            $activity[]=['id'=>(int)$r['id'], 'who'=>(string)$r['actor_id'], 'group'=>(int)$r['group_id'],
                'title'=>$r['actor_name'].' · '.str_replace('_',' ',$r['action_type']),
                'sub'=>$r['group_name'].' · '.$r['detail'], 'amount'=>0, 'kind'=>''];
        }
    }
    $invites=fetch_all('SELECT gm.group_id,g.name, u.display_name invited_by FROM group_members gm
        JOIN `groups` g ON g.id=gm.group_id LEFT JOIN users u ON u.id=gm.invited_by
        WHERE gm.user_id=? AND gm.status="pending" AND g.archived_at IS NULL
        ORDER BY gm.created_at DESC', [$actor]);
    return ['signedInAs'=>(string)$actor,'people'=>$people,'groups'=>$groups,'debts'=>$debts,
        'repayments'=>$repayments,'activity'=>$activity,'invitations'=>$invites,
        'account'=>['id'=>(string)$actor, 'username'=>$user['username'],'display_name'=>$user['display_name']]];
}

try {
    $user = current_user();
    if (!$user) json_reply(['ok'=>false, 'error'=>'Session expired. Please log in.'],401);
    $actor=(int)$user['id'];
    $action=(string)($_GET['action'] ?? $_POST['action'] ?? '');
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        if ($action==='state') json_reply(['ok'=>true,'state'=>state_payload($actor)]);
        json_reply(['ok'=>false,'error'=>'Unknown endpoint'],404);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') json_reply(['ok'=>false,'error'=>'Use POST for changes.'],405);
    if (!hash_equals(csrf_token(), (string)($_POST['csrf_token']??''))) {
        json_reply(['ok'=>false,'error'=>'Security token expired. Reload the page.'],403);
    }
    $msg='Saved.';
    if ($action==='logout') {
        $_SESSION=[]; session_regenerate_id(true); json_reply(['ok'=>true,'message'=>'Logged out']);
    }
    if ($action==='profile_update') {
        $display=required_text('display_name',100,'Display name');
        run_sql('UPDATE users SET display_name=? WHERE id=?',[$display,$actor]);
        json_reply(['ok'=>true,'message'=>'Profile updated.']);
    }
    if ($action==='password_change') {
        $old=(string)($_POST['old_password']??'');
        $new=(string)($_POST['password']??'');
        $confirm=(string)($_POST['confirm_password']??'');
        $row=fetch_one('SELECT password_hash FROM users WHERE id=?',[$actor]);
        if (!$row || !password_verify($old,$row['password_hash'])) throw new DomainException('Current password is incorrect.');
        if (strlen($new)<8 || strlen($new)>72 || $new!==$confirm) throw new DomainException('New password needs 8–72 characters and matching confirmation.');
        run_sql('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$actor]);
        session_regenerate_id(true);
        json_reply(['ok'=>true,'message'=>'Password changed.']);
    }
    if ($action==='group_create') {
        $name=required_text('name',100,'Group name'); $desc=optional_text('description',500,'Description');
        db()->beginTransaction();
        run_sql('INSERT INTO `groups` (name,description,created_by) VALUES (?,?,?)',[$name,$desc,$actor]);
        $groupId=(int)db()->lastInsertId();
        run_sql('INSERT INTO group_members(group_id,user_id,role,status,joined_at) VALUES (?,? ,"owner","active", NOW())',[$groupId,$actor]);
        log_action($groupId,$actor,'group_created','group',$groupId,'Group created');
        $msg='Group created.';
    } elseif (in_array($action,['group_invite','invite_accept','invite_decline','member_remove','group_edit','group_delete'],true)) {
        $groupId=request_id('group_id');
        db()->beginTransaction();
        $g=lock_group($groupId);
        if ($action==='invite_accept'||$action==='invite_decline') {
            $inv=fetch_one('SELECT * FROM group_members WHERE group_id=? AND user_id=? AND status="pending" FOR UPDATE',[$groupId,$actor]);
            if (!$inv) throw new DomainException('No pending invitation for your account.');
            $status=$action==='invite_accept'?'active':'declined';
            run_sql('UPDATE group_members SET status=?,joined_at=IF(?="active",NOW(),NULL) WHERE id=?',[$status,$status,$inv['id']]);
            log_action($groupId,$actor,'invitation_'.$status,'user',$actor,'Invitation '.$status);
            $msg=$status==='active'?'Invitation accepted.':'Invitation declined.';
        } else {
            $access=group_access($groupId,$actor,false);
            if ($access['role']!=='owner') throw new DomainException('Only the group creator may manage group membership.');
            if ($action==='group_invite') {
                $name=strtolower(required_text('username',40,'Username'));
                $target=fetch_one('SELECT id FROM users WHERE username=? AND is_active=1',[$name]);
                if (!$target || (int)$target['id']===$actor) throw new DomainException('Choose another registered username.');
                $targetId=(int)$target['id'];
                $existing=fetch_one('SELECT * FROM group_members WHERE group_id=? AND user_id=? FOR UPDATE',[$groupId,$targetId]);
                if ($existing && in_array($existing['status'],['active','pending'],true)) throw new DomainException('This user is already a member or invited.');
                if ($existing) run_sql('UPDATE group_members SET status="pending",role="member",invited_by=?,joined_at=NULL WHERE id=?',[$actor,$existing['id']]);
                else run_sql('INSERT INTO group_members(group_id,user_id,invited_by) VALUES (?,?,?)',[$groupId,$targetId,$actor]);
                log_action($groupId,$actor,'member_invited','user',$targetId,'Invitation sent');
                $msg='Invitation sent. Your friend must accept it.';
            } elseif ($action==='member_remove') {
                $targetId=request_id('target_user_id');
                $target=fetch_one('SELECT * FROM group_members WHERE group_id=? AND user_id=? AND status="active" FOR UPDATE',[$groupId,$targetId]);
                if (!$target || $target['role']==='owner') throw new DomainException('Owner cannot be removed.');
                $rows=fetch_all('SELECT d.id,d.approval_status,d.amount,COALESCE(SUM(CASE WHEN p.status="confirmed" THEN p.amount ELSE 0 END),0) paid
                    FROM debts d LEFT JOIN repayments p ON p.debt_id=d.id
                    WHERE d.group_id=? AND (d.debtor_id=? OR d.creditor_id=?)
                    GROUP BY d.id,d.approval_status,d.amount',[$groupId,$targetId,$targetId]);
                foreach ($rows as $d) {
                    if ($d['approval_status']==='pending' || ($d['approval_status']==='accepted' && db_cents((string)$d['amount'])>db_cents((string)$d['paid']))) {
                        throw new DomainException('Resolve pending or outstanding debts before removing this member.');
                    }
                    $rp=fetch_one('SELECT id FROM repayments WHERE debt_id=? AND status="pending" LIMIT 1',[$d['id']]);
                    $vr=fetch_one('SELECT id FROM debt_void_requests WHERE debt_id=? AND status="pending" LIMIT 1',[$d['id']]);
                    if ($rp || $vr) throw new DomainException('Resolve pending financial requests before removing this member.');
                }
                run_sql('UPDATE group_members SET status="removed" WHERE id=?',[$target['id']]);
                log_action($groupId,$actor,'member_removed','user',$targetId,'Creator removed member; financial history retained');
                $msg='Member removed. Financial history preserved.';
            } elseif ($action==='group_edit') {
                run_sql('UPDATE `groups` SET name=?,description=? WHERE id=?',
                    [required_text('name',100,'Group name'),optional_text('description',500,'Description'),$groupId]);
                log_action($groupId,$actor,'group_edited','group',$groupId,'Group details updated');
                $msg='Group updated.';
            } elseif ($action==='group_delete') {
                $ds=fetch_one('SELECT COUNT(*) n FROM debts WHERE group_id=?',[$groupId]);
                $ms=fetch_one('SELECT COUNT(*) n FROM group_members WHERE group_id=?',[$groupId]);
                if ((int)$ds['n'] || (int)$ms['n']!==1) throw new DomainException('Only a completely unused group can be deleted.');
                run_sql('DELETE FROM `groups` WHERE id=?',[$groupId]);
                $msg='Empty group deleted.';
            }
        }
    } elseif (in_array($action,['debt_create','debt_accept','debt_reject','debt_edit','debt_cancel','payment_submit','payment_confirm','payment_reject','payment_edit','payment_withdraw'],true)) {
        $debtId=($action==='debt_create')?0:request_id('debt_id');
        if ($action==='debt_create') $groupId=request_id('group_id');
        else {
            $base=fetch_one('SELECT group_id FROM debts WHERE id=?',[$debtId]);
            if (!$base) throw new DomainException('Debt does not exist.');
            $groupId=(int)$base['group_id'];
        }
        db()->beginTransaction();
        lock_group($groupId);
        group_access($groupId,$actor,false);
        if ($action==='debt_create') {
            $direction=(string)($_POST['direction']??'');
            $other=request_id('counterparty');
            if (!in_array($direction,['owe','owed'],true)) throw new DomainException('Select who owes whom.');
            $debtor=$direction==='owe'?$actor:$other;
            $creditor=$direction==='owe'?$other:$actor;
            require_pair($groupId,$actor,$debtor,$creditor);
            $amt=cents_sql(money_to_cents((string)($_POST['amount']??'')));
            $desc=required_text('description',255,'Reason');
            run_sql('INSERT INTO debts(group_id,debtor_id,creditor_id,created_by,amount,description) VALUES (?,?,?,?,?,?)',
                [$groupId,$debtor,$creditor,$actor,$amt,$desc]);
            $id=(int)db()->lastInsertId();
            log_action($groupId,$actor,'debt_requested','debt',$id,'Debt awaiting counterparty approval');
            $msg='Debt request sent for approval.';
        } else {
            $d=fetch_one('SELECT * FROM debts WHERE id=? FOR UPDATE',[$debtId]);
            if (!$d) throw new DomainException('Debt missing.');
            if (in_array($action,['debt_edit','debt_cancel'],true)) {
                if ($d['approval_status']!=='pending' || (int)$d['created_by']!==$actor) {
                    throw new DomainException('Only the request creator may edit or cancel pending debts.');
                }
                if ($action==='debt_edit') {
                    $amt=cents_sql(money_to_cents((string)($_POST['amount']??'')));
                    $desc=required_text('description',255,'Reason');
                    run_sql('UPDATE debts SET amount=?,description=? WHERE id=?',[$amt,$desc,$debtId]);
                    log_action($groupId,$actor,'debt_edited','debt',$debtId,'Pending debt changed');
                    $msg='Pending debt updated.';
                } else {
                    run_sql('UPDATE debts SET approval_status="cancelled",decided_by=?,decided_at=NOW() WHERE id=?',[$actor,$debtId]);
                    log_action($groupId,$actor,'debt_cancelled','debt',$debtId,'Pending debt cancelled');
                    $msg='Pending debt cancelled.';
                }
            } elseif (in_array($action,['debt_accept','debt_reject'],true)) {
                if ($d['approval_status']!=='pending') throw new DomainException('Request already decided.');
                $other=(int)$d['created_by']===(int)$d['debtor_id']?(int)$d['creditor_id']:(int)$d['debtor_id'];
                if ($other!==$actor || !active_member($groupId,(int)$d['debtor_id']) || !active_member($groupId,(int)$d['creditor_id'])) {
                    throw new DomainException('Only the other involved member can decide.');
                }
                $status=$action==='debt_accept'?'accepted':'rejected';
                run_sql('UPDATE debts SET approval_status=?,decided_by=?,decided_at=NOW() WHERE id=?',[$status,$actor,$debtId]);
                log_action($groupId,$actor,'debt_'.$status,'debt',$debtId,'Debt '.$status);
                $msg='Debt request '.$status.'.';
            } else {
                if ($d['approval_status']!=='accepted') throw new DomainException('Debt is not active.');
                $paid=fetch_one('SELECT COALESCE(SUM(amount),0.00) total FROM repayments WHERE debt_id=? AND status="confirmed"',[$debtId]);
                $remaining=db_cents((string)$d['amount'])-db_cents((string)$paid['total']);
                if ($action==='payment_submit') {
                    if ($actor!==(int)$d['debtor_id']) throw new DomainException('Only the debtor can submit repayments.');
                    $void=fetch_one('SELECT id FROM debt_void_requests WHERE debt_id=? AND status="pending" LIMIT 1',[$debtId]);
                    if ($void) throw new DomainException('Resolve the pending debt cancellation first.');
                    $amt=money_to_cents((string)($_POST['amount']??''));
                    if ($amt>$remaining) throw new DomainException('Repayment exceeds outstanding balance.');
                    $note=optional_text('note',200,'Payment note');
                    run_sql('INSERT INTO repayments(debt_id,submitted_by,amount,note) VALUES (?,?,?,?)',
                        [$debtId,$actor,cents_sql($amt),$note]);
                    $id=(int)db()->lastInsertId();
                    log_action($groupId,$actor,'repayment_submitted','repayment',$id,'Awaiting creditor confirmation');
                    $msg='Payment claim sent. Balance unchanged until confirmed.';
                } else {
                    $paymentId=request_id('repayment_id');
                    $p=fetch_one('SELECT * FROM repayments WHERE id=? AND debt_id=? FOR UPDATE',[$paymentId,$debtId]);
                    if (!$p || $p['status']!=='pending') throw new DomainException('Repayment already decided or not found.');
                    if (in_array($action,['payment_edit','payment_withdraw'],true)) {
                        if ($actor!==(int)$d['debtor_id']) throw new DomainException('Only the debtor can change pending repayment claims.');
                        if ($action==='payment_edit') {
                            $amt=money_to_cents((string)($_POST['amount']??''));
                            if ($amt>$remaining) throw new DomainException('Repayment exceeds remaining balance.');
                            $note=optional_text('note',200,'Payment note');
                            run_sql('UPDATE repayments SET amount=?,note=? WHERE id=?',[cents_sql($amt),$note,$paymentId]);
                            log_action($groupId,$actor,'repayment_edited','repayment',$paymentId,'Pending claim changed');
                            $msg='Pending repayment updated.';
                        } else {
                            run_sql('UPDATE repayments SET status="withdrawn",decided_by=?,decided_at=NOW() WHERE id=?',[$actor,$paymentId]);
                            log_action($groupId,$actor,'repayment_withdrawn','repayment',$paymentId,'Pending claim withdrawn');
                            $msg='Pending repayment withdrawn.';
                        }
                    } else {
                    if ($actor!==(int)$d['creditor_id']) throw new DomainException('Only the creditor can confirm or reject payment.');
                    if ($action==='payment_confirm' && db_cents((string)$p['amount'])>$remaining) throw new DomainException('Payment would exceed remaining debt.');
                    $status=$action==='payment_confirm'?'confirmed':'rejected';
                    run_sql('UPDATE repayments SET status=?,decided_by=?,decided_at=NOW() WHERE id=?',[$status,$actor,$paymentId]);
                    log_action($groupId,$actor,'repayment_'.$status,'repayment',$paymentId,'Creditor '.$status.' payment');
                    $msg='Repayment '.$status.'.';
                    }
                }
            }
        }
    } else throw new DomainException('Unknown action.');
    if (db()->inTransaction()) db()->commit();
    json_reply(['ok'=>true,'message'=>$msg]);
} catch (DomainException $e) {
    rollback_if_open(); json_reply(['ok'=>false,'error'=>$e->getMessage()],400);
} catch (PDOException $e) {
    rollback_if_open(); error_log('SplitCircle API SQL: '.$e->getMessage());
    json_reply(['ok'=>false,'error'=>'Database error. Check XAMPP, credentials and database migration.'],500);
} catch (Throwable $e) {
    rollback_if_open(); error_log('SplitCircle API error: '.$e->getMessage());
    json_reply(['ok'=>false,'error'=>'Unexpected server error. Check PHP error log.'],500);
}
