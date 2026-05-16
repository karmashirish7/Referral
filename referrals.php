<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user = currentUser(); $uid = currentUserId(); $role = currentRole();
$unread = unreadNotificationCount($sb, $uid);

// Handle status update (from pipeline quick-update form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $rid          = (int)($_POST['referral_id'] ?? 0);
    $newStatus    = $_POST['new_status'] ?? '';
    $brokerNotes  = trim($_POST['broker_notes'] ?? '');
    $brokerUpfront = (float)($_POST['broker_upfront'] ?? 0);

    $updateData = ['status' => $newStatus, 'broker_notes' => $brokerNotes];
    if ($newStatus === 'settled' && $brokerUpfront > 0) {
        $updateData['broker_upfront'] = $brokerUpfront;
    }
    $sb->from('referrals')->eq('id', $rid)->update($updateData);

    if ($newStatus === 'settled' && $brokerUpfront > 0) {
        $ref = $sb->from('referrals')->eq('id', $rid)->single();
        if ($ref) {
            $partnerUser = $sb->from('users')->eq('id', $ref['partner_id'])->select('commission_rate')->single();
            $rate = (float)($partnerUser['commission_rate'] ?? 20);
            $commAmt = calculateCommission($brokerUpfront, $rate);
            $existingComm = $sb->from('commissions')->eq('referral_id', $rid)->single();
            if ($existingComm) {
                $sb->from('commissions')->eq('id', $existingComm['id'])->update(['broker_upfront' => $brokerUpfront, 'amount' => $commAmt]);
            } else {
                $sb->from('commissions')->insert(['referral_id' => $rid, 'partner_id' => $ref['partner_id'], 'broker_upfront' => $brokerUpfront, 'rate' => $rate, 'amount' => $commAmt]);
            }
            addNotification($sb, $ref['partner_id'], 'Referral Settled', "Your referral {$ref['ref_number']} has been settled. Commission: " . money($commAmt));
        }
    }

    logAction($sb, $uid, $user['name'], 'status_changed', 'referral', $rid, "Status changed to {$newStatus}");
    header("Location: referrals.php?updated=1"); exit;
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$allReferrals = $sb->rpc('get_referrals_list', [
    'p_role'   => $role,
    'p_uid'    => $uid,
    'p_status' => $_GET['status'] ?? '',
    'p_date'   => $_GET['date']   ?? '',
    'p_search' => trim($_GET['search'] ?? ''),
]);
$allReferrals = is_array($allReferrals) ? $allReferrals : [];
$total        = count($allReferrals);
$totalPages   = max(1, ceil($total / $perPage));
$referrals    = array_slice($allReferrals, ($page - 1) * $perPage, $perPage);

$totalValue   = array_sum(array_column($allReferrals, 'estimated_amount'));
$statusCounts = array_count_values(array_column($allReferrals, 'status'));

$notifList = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Referrals</span>
            <div class="header-spacer"></div>
            <div style="position:relative">
                <button class="notif-btn" id="notifBtn"><i class="bi bi-bell"></i>
                    <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread ?></span><?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">Notifications <a href="notifications.php">View all</a></div>
                    <?php foreach ($notifList as $n): ?>
                        <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                            <div class="notif-item-dot"></div>
                            <div><div class="notif-item-title"><?= e($n['title']) ?></div><div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role"><?= ucfirst($role) ?></div></div>
        </div>

        <div class="page-body">
            <?php if (isset($_GET['updated'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Referral updated.</div><?php endif; ?>

            <div class="page-heading">
                <div><h1>All Referrals</h1><p><?= $total ?> referral<?= $total != 1 ? 's' : '' ?> — total value <?= money($totalValue) ?></p></div>
                <?php if ($role === 'partner'): ?><a href="submit-referral.php" class="btn-primary"><i class="bi bi-plus-lg"></i> New Referral</a><?php endif; ?>
            </div>

            <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
                <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search name, email, ref..." class="filter-select">
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['pending','qualified','lodged','approved','settled','declined'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?> (<?= $statusCounts[$s] ?? 0 ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date" value="<?= e($_GET['date'] ?? '') ?>" class="filter-select">
                <button type="submit" class="btn-primary btn-sm">Filter</button>
                <a href="referrals.php" class="btn-outline btn-sm">Clear</a>
            </form>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Ref #</th><th>Client</th><th>Loan Type</th>
                            <?php if ($role !== 'partner'): ?><th>Partner</th><?php endif; ?>
                            <?php if ($role !== 'broker'):  ?><th>Broker</th><?php endif; ?>
                            <th>Est. Value</th><th>Date</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $r): ?>
                            <tr>
                                <td><span class="ref-number"><?= e($r['ref_number']) ?></span></td>
                                <td>
                                    <span class="client-name"><?= e($r['client_name']) ?></span>
                                    <?php if ($r['client_email']): ?><br><span style="font-size:11px;color:#718096"><?= e($r['client_email']) ?></span><?php endif; ?>
                                </td>
                                <td><?= e($r['loan_type']) ?></td>
                                <?php if ($role !== 'partner'): ?><td><?= e($r['partner_name'] ?? '—') ?> <?php if ($r['partner_tier'] ?? ''): ?><span class="status-badge badge-<?= strtolower($r['partner_tier']) ?>" style="font-size:10px"><?= e($r['partner_tier']) ?></span><?php endif; ?></td><?php endif; ?>
                                <?php if ($role !== 'broker'):  ?><td><?= e($r['broker_name'] ?? '—') ?></td><?php endif; ?>
                                <td><?= money($r['estimated_amount']) ?></td>
                                <td><?= date('d M Y', strtotime($r['date_submitted'])) ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$referrals): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i> No referrals found.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div style="margin-top:16px;display:flex;gap:6px">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="btn-outline btn-sm <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
