<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user   = currentUser();
$uid    = currentUserId();
$role   = currentRole();
$unread = unreadNotificationCount($pdo, $uid);

// ── Filters ────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$dateFilter   = $_GET['date']   ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 10;
$offset       = ($page - 1) * $perPage;

// ── Build Query ────────────────────────────────────────────
$where  = [];
$params = [];

// Partners see only their own; brokers/admins/auditors see all
if ($role === 'partner') {
    $where[]  = 'r.partner_id = ?';
    $params[] = $uid;
} elseif ($role === 'broker') {
    $where[]  = 'r.broker_id = ?';
    $params[] = $uid;
}

if ($statusFilter) {
    $where[]  = 'r.status = ?';
    $params[] = $statusFilter;
}

if ($dateFilter) {
    $where[]  = 'DATE(r.date_submitted) = ?';
    $params[] = $dateFilter;
}

if ($search) {
    $where[]  = '(r.ref_number LIKE ? OR r.client_name LIKE ? OR r.client_email LIKE ?)';
    $like     = "%$search%";
    $params   = array_merge($params, [$like, $like, $like]);
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM referrals r $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Referrals for this page
$stmt = $pdo->prepare("
    SELECT r.*, p.name AS partner_name, p.tier AS partner_tier, b.name AS broker_name
    FROM referrals r
    LEFT JOIN users p ON r.partner_id = p.id
    LEFT JOIN users b ON r.broker_id  = b.id
    $whereSQL
    ORDER BY r.date_submitted DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$referrals = $stmt->fetchAll();

// Total value of filtered set
$valStmt = $pdo->prepare("SELECT COALESCE(SUM(r.estimated_amount),0) FROM referrals r $whereSQL");
$valStmt->execute($params);
$totalValue = $valStmt->fetchColumn();

// Handle status update (broker/admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    requireRole(['broker','admin']);
    $rid        = (int)$_POST['referral_id'];
    $newStatus  = $_POST['new_status'];
    $brokerNote = trim($_POST['broker_notes'] ?? '');
    $brokerUp   = (float)($_POST['broker_upfront'] ?? 0);

    $validStatuses = ['pending','qualified','lodged','approved','settled','declined'];
    if (in_array($newStatus, $validStatuses)) {
        $stmt = $pdo->prepare("UPDATE referrals SET status=?, broker_notes=? WHERE id=?");
        $stmt->execute([$newStatus, $brokerNote, $rid]);

        // If settled → create/update commission record
        if ($newStatus === 'settled' && $brokerUp > 0) {
            $ref = $pdo->prepare("SELECT r.*, u.commission_rate FROM referrals r JOIN users u ON r.partner_id = u.id WHERE r.id=?");
            $ref->execute([$rid]);
            $refData = $ref->fetch();

            if ($refData) {
                $pdo->prepare("UPDATE referrals SET broker_upfront=?, commission_amount=? WHERE id=?")->execute([
                    $brokerUp,
                    calculateCommission($brokerUp, $refData['commission_rate']),
                    $rid
                ]);
                // Delete old commission if re-settling
                $pdo->prepare("DELETE FROM commissions WHERE referral_id=?")->execute([$rid]);
                $commAmt = calculateCommission($brokerUp, $refData['commission_rate']);
                $pdo->prepare("INSERT INTO commissions (referral_id, partner_id, broker_upfront, rate, amount) VALUES (?,?,?,?,?)")
                    ->execute([$rid, $refData['partner_id'], $brokerUp, $refData['commission_rate'], $commAmt]);

                // Notify partner
                $ref2 = $pdo->prepare("SELECT ref_number, client_name FROM referrals WHERE id=?");
                $ref2->execute([$rid]);
                $rn = $ref2->fetch();
                addNotification($pdo, $refData['partner_id'], 'Referral Settled',
                    "Referral {$rn['ref_number']} for {$rn['client_name']} has settled. Commission " . money($commAmt) . " is pending.");
            }
        }

        // Notify partner on status change
        $ref2 = $pdo->prepare("SELECT r.partner_id, r.ref_number, r.client_name FROM referrals r WHERE r.id=?");
        $ref2->execute([$rid]); $rn = $ref2->fetch();
        if ($rn) {
            addNotification($pdo, $rn['partner_id'], 'Status Updated',
                "Referral {$rn['ref_number']} ({$rn['client_name']}) status changed to " . ucfirst($newStatus));
        }

        logAction($pdo, $uid, $user['name'], 'status_changed', 'referral', $rid, "Status changed to $newStatus for referral #$rid");
        header("Location: referrals.php?updated=1");
        exit;
    }
}

// Notifications for dropdown
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Referral Portal</span>
            <div class="search-bar">
                <form method="GET" style="display:contents">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search referrals...">
                </form>
            </div>
            <div class="header-spacer"></div>
            <div style="position:relative">
                <button class="notif-btn" id="notifBtn">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread ?></span><?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">Notifications <a href="notifications.php">View all</a></div>
                    <?php foreach ($notifList as $n): ?>
                        <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>">
                            <div class="notif-item-dot"></div>
                            <div>
                                <div class="notif-item-title"><?= e($n['title']) ?></div>
                                <div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info">
                <div class="user-name"><?= e($user['name']) ?></div>
                <div class="user-role"><?= ucfirst($role) ?></div>
            </div>
        </div>

        <div class="page-body">

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> Referral status updated successfully.</div>
            <?php endif; ?>

            <div class="page-heading">
                <div>
                    <h1><?= $role === 'partner' ? 'My Referrals' : 'All Referrals' ?></h1>
                    <p>Monitor and manage <?= $role === 'partner' ? 'your' : 'all' ?> referrals in the network.</p>
                </div>
                <div class="heading-actions">
                    <a href="referrals.php?export=csv&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>" class="btn-outline">
                        <i class="bi bi-download"></i> Export CSV
                    </a>
                    <?php if ($role === 'partner'): ?>
                        <a href="submit-referral.php" class="btn-primary"><i class="bi bi-plus-lg"></i> New Referral</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="table-card">
                <div class="table-header">
                    <form method="GET" class="filter-row">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending','qualified','lodged','approved','settled','declined'] as $s): ?>
                                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" class="filter-input" value="<?= e($dateFilter) ?>" onchange="this.form.submit()">
                        <input type="text" name="search" class="filter-input" value="<?= e($search) ?>" placeholder="Search..." style="width:160px">
                        <button type="submit" class="btn-primary btn-sm">Filter</button>
                        <?php if ($statusFilter || $dateFilter || $search): ?>
                            <a href="referrals.php" class="btn-outline btn-sm">Clear</a>
                        <?php endif; ?>
                    </form>
                    <div style="font-size:13px;color:#1F3864;font-weight:600">
                        Total Value: <?= money($totalValue) ?>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Client</th>
                            <?php if ($role !== 'partner'): ?><th>Partner</th><?php endif; ?>
                            <th>Loan Type</th>
                            <th>Date Submitted</th>
                            <th>Est. Amount</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <?php if (in_array($role, ['broker','admin'])): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $r): ?>
                            <tr>
                                <td><span class="ref-number"><?= e($r['ref_number']) ?></span></td>
                                <td>
                                    <div class="client-name"><?= e($r['client_name']) ?></div>
                                    <?php if ($r['client_suburb']): ?>
                                        <div style="font-size:11px;color:#718096"><?= e($r['client_suburb']) ?>, <?= e($r['client_state']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($role !== 'partner'): ?>
                                    <td><?= e($r['partner_name']) ?> <span class="status-badge badge-<?= strtolower($r['partner_tier']) ?>"><?= e($r['partner_tier']) ?></span></td>
                                <?php endif; ?>
                                <td><?= e($r['loan_type']) ?></td>
                                <td><?= date('d M Y', strtotime($r['date_submitted'])) ?></td>
                                <td><?= money($r['estimated_amount']) ?></td>
                                <td><?= $r['commission_amount'] > 0 ? money($r['commission_amount']) : '<span style="color:#718096">—</span>' ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                                <?php if (in_array($role, ['broker','admin'])): ?>
                                    <td>
                                        <button class="btn-info btn-sm" onclick="openStatusModal(
                                            <?= $r['id'] ?>,
                                            '<?= e($r['ref_number']) ?>',
                                            '<?= e($r['client_name']) ?>',
                                            '<?= e($r['status']) ?>',
                                            '<?= e(addslashes($r['broker_notes'] ?? '')) ?>'
                                        )">Update</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$referrals): ?>
                            <tr><td colspan="9">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    No referrals found.
                                    <?php if ($role === 'partner'): ?><br><a href="submit-referral.php">Submit your first referral</a><?php endif; ?>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="table-footer">
                    <span>Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> referrals</span>
                    <div style="display:flex;gap:6px">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&date=<?= urlencode($dateFilter) ?>"
                               style="padding:4px 10px;border-radius:4px;text-decoration:none;font-size:12px;
                                      background:<?= $i === $page ? '#1F3864' : '#f1f5f9' ?>;
                                      color:<?= $i === $page ? '#fff' : '#1F3864' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($role, ['broker','admin'])): ?>
<!-- Status Update Modal -->
<div class="modal-overlay" id="statusModal" style="display:none">
    <div class="modal-box">
        <h2>Update Referral Status</h2>
        <form method="POST">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="referral_id" id="modal_rid">
            <div id="modal_meta" style="margin-bottom:16px;font-size:13px;color:#718096"></div>

            <div class="form-group" style="margin-bottom:14px">
                <label>New Status</label>
                <select name="new_status" id="modal_status" onchange="toggleBrokerUpfront(this.value)">
                    <?php foreach (['pending','qualified','lodged','approved','settled','declined'] as $s): ?>
                        <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="upfrontGroup" style="display:none;margin-bottom:14px">
                <label>Broker Upfront Commission (AUD) *</label>
                <input type="number" name="broker_upfront" step="0.01" min="0" placeholder="e.g. 8500.00">
                <span class="form-hint">Required when settling. Partner commission = this × tier rate.</span>
            </div>

            <div class="form-group" style="margin-bottom:14px">
                <label>Broker Notes (optional)</label>
                <textarea name="broker_notes" id="modal_notes" rows="3" placeholder="Add a note for the partner..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatusModal(rid, ref, client, currentStatus, notes) {
    document.getElementById('modal_rid').value    = rid;
    document.getElementById('modal_meta').textContent = ref + ' — ' + client;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('modal_notes').value  = notes;
    toggleBrokerUpfront(currentStatus);
    document.getElementById('statusModal').style.display = 'flex';
}
function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}
function toggleBrokerUpfront(status) {
    document.getElementById('upfrontGroup').style.display = status === 'settled' ? 'flex' : 'none';
}
document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) closeStatusModal();
});
</script>
<?php endif; ?>

<script src="assets/js/main.js"></script>
</body>
</html>

<?php
// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="referrals-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Ref #','Client','Loan Type','Suburb','Date Submitted','Est. Amount','Commission','Status']);
    foreach ($referrals as $r) {
        fputcsv($out, [$r['ref_number'], $r['client_name'], $r['loan_type'], $r['client_suburb'], $r['date_submitted'], $r['estimated_amount'], $r['commission_amount'], $r['status']]);
    }
    fclose($out);
    exit;
}
?>
