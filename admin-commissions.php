<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

$user   = currentUser();
$uid    = currentUserId();
$unread = unreadNotificationCount($pdo, $uid);

// Mark commission as paid / override amount
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)($_POST['commission_id'] ?? 0);

    if (isset($_POST['mark_paid'])) {
        $pdo->prepare("UPDATE commissions SET status='paid', paid_at=NOW() WHERE id=?")->execute([$cid]);

        // Notify the partner
        $stmt = $pdo->prepare("SELECT c.partner_id, c.amount, c.override_amount, r.ref_number, r.client_name FROM commissions c JOIN referrals r ON c.referral_id=r.id WHERE c.id=?");
        $stmt->execute([$cid]); $ci = $stmt->fetch();
        if ($ci) {
            $amt = $ci['override_amount'] ?? $ci['amount'];
            addNotification($pdo, $ci['partner_id'], 'Commission Paid',
                "Commission of " . money($amt) . " for {$ci['ref_number']} ({$ci['client_name']}) has been paid.");
        }
        logAction($pdo, $uid, $user['name'], 'commission_paid', 'commission', $cid, "Marked commission #$cid as paid");
        header("Location: admin-commissions.php?msg=paid"); exit;

    } elseif (isset($_POST['override_commission'])) {
        $overrideAmt    = (float)$_POST['override_amount'];
        $overrideReason = trim($_POST['override_reason']);
        $pdo->prepare("UPDATE commissions SET override_amount=?, override_reason=? WHERE id=?")->execute([$overrideAmt, $overrideReason, $cid]);
        logAction($pdo, $uid, $user['name'], 'commission_override', 'commission', $cid, "Override amount set to " . money($overrideAmt) . " — $overrideReason");
        header("Location: admin-commissions.php?msg=override"); exit;
    }
}

$status = $_GET['status'] ?? '';
$where  = $status ? "WHERE c.status = '$status'" : '';

$commissions = $pdo->query("
    SELECT c.*, u.name AS partner_name, u.tier, r.ref_number, r.client_name, r.loan_type
    FROM commissions c
    JOIN users     u ON c.partner_id  = u.id
    JOIN referrals r ON c.referral_id = r.id
    $where
    ORDER BY c.created_at DESC
")->fetchAll();

$totalPending = $pdo->query("SELECT COALESCE(SUM(COALESCE(override_amount,amount)),0) FROM commissions WHERE status='pending'")->fetchColumn();
$totalPaid    = $pdo->query("SELECT COALESCE(SUM(COALESCE(override_amount,amount)),0) FROM commissions WHERE status='paid'")->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissions — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Admin Panel</span>
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
                            <div><div class="notif-item-title"><?= e($n['title']) ?></div>
                            <div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role">Administrator</div></div>
        </div>

        <div class="page-body">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i>
                    <?= ['paid'=>'Commission marked as paid.','override'=>'Commission amount overridden.'][$_GET['msg']] ?? '' ?>
                </div>
            <?php endif; ?>

            <div class="page-heading">
                <div><h1>Commission Management</h1><p>Review, override, and mark commissions as paid.</p></div>
            </div>

            <div class="stats-grid" style="grid-template-columns:repeat(2,1fr);max-width:500px">
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="bi bi-hourglass"></i></div>
                    <div class="stat-label">Pending Payouts</div>
                    <div class="stat-value"><?= money($totalPending) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-label">Total Paid</div>
                    <div class="stat-value"><?= money($totalPaid) ?></div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h2>All Commissions</h2>
                    <form method="GET" class="filter-row">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
                            <option value="paid"    <?= $status==='paid'   ?'selected':'' ?>>Paid</option>
                        </select>
                    </form>
                    <a href="admin-commissions.php?export=csv" class="btn-outline btn-sm"><i class="bi bi-download"></i> Export</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Referral</th>
                            <th>Client</th>
                            <th>Partner</th>
                            <th>Tier</th>
                            <th>Broker Upfront</th>
                            <th>Rate</th>
                            <th>Commission</th>
                            <th>Override</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commissions as $c): ?>
                            <tr>
                                <td><span class="ref-number"><?= e($c['ref_number']) ?></span></td>
                                <td><?= e($c['client_name']) ?></td>
                                <td><?= e($c['partner_name']) ?></td>
                                <td><span class="status-badge badge-<?= strtolower($c['tier']) ?>"><?= e($c['tier']) ?></span></td>
                                <td><?= money($c['broker_upfront']) ?></td>
                                <td><?= $c['rate'] ?>%</td>
                                <td><?= money($c['amount']) ?></td>
                                <td>
                                    <?php if ($c['override_amount']): ?>
                                        <strong><?= money($c['override_amount']) ?></strong>
                                        <div style="font-size:11px;color:#718096"><?= e(substr($c['override_reason'],0,40)) ?></div>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= commissionBadge($c['status']) ?></td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="commission_id" value="<?= $c['id'] ?>">
                                                <button type="submit" name="mark_paid" value="1" class="btn-success btn-sm"
                                                        data-confirm="Mark this commission as paid?">Mark Paid</button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn-warning btn-sm"
                                            onclick="openOverride(<?= $c['id'] ?>, <?= $c['override_amount'] ?? $c['amount'] ?>)">
                                            Override
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$commissions): ?>
                            <tr><td colspan="10"><div class="empty-state"><i class="bi bi-cash-coin"></i>No commissions found.</div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Override Modal -->
<div class="modal-overlay" id="overrideModal" style="display:none">
    <div class="modal-box">
        <h2>Override Commission Amount</h2>
        <form method="POST">
            <input type="hidden" name="commission_id" id="ov_id">
            <div class="form-group" style="margin-bottom:14px">
                <label>Override Amount (AUD)</label>
                <input type="number" name="override_amount" id="ov_amount" step="0.01" min="0">
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>Reason for Override *</label>
                <textarea name="override_reason" rows="3" placeholder="Explain the reason..." required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('overrideModal').style.display='none'">Cancel</button>
                <button type="submit" name="override_commission" value="1" class="btn-primary">Save Override</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openOverride(id, currentAmt) {
    document.getElementById('ov_id').value     = id;
    document.getElementById('ov_amount').value = currentAmt;
    document.getElementById('overrideModal').style.display = 'flex';
}
</script>
</body>
</html>
