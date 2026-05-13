<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole(['broker','admin']);

$user   = currentUser();
$uid    = currentUserId();
$role   = currentRole();
$unread = unreadNotificationCount($pdo, $uid);

// Load referrals grouped by status for kanban columns
$columns = [
    'pending'   => ['label' => 'New Leads',   'icon' => 'inbox'],
    'qualified' => ['label' => 'Qualified',    'icon' => 'star'],
    'lodged'    => ['label' => 'Lodged',       'icon' => 'file-earmark-text'],
    'approved'  => ['label' => 'Approved',     'icon' => 'check-circle'],
    'settled'   => ['label' => 'Settled',      'icon' => 'cash-coin'],
    'declined'  => ['label' => 'Declined',     'icon' => 'x-circle'],
];

$kanbanData = [];
foreach ($columns as $status => $info) {
    if ($role === 'broker') {
        $stmt = $pdo->prepare("SELECT r.*, u.name AS partner_name, u.tier FROM referrals r JOIN users u ON r.partner_id = u.id WHERE r.status = ? AND (r.broker_id = ? OR r.broker_id IS NULL) ORDER BY r.date_submitted DESC");
        $stmt->execute([$status, $uid]);
    } else {
        $stmt = $pdo->prepare("SELECT r.*, u.name AS partner_name, u.tier FROM referrals r JOIN users u ON r.partner_id = u.id WHERE r.status = ? ORDER BY r.date_submitted DESC");
        $stmt->execute([$status]);
    }
    $kanbanData[$status] = $stmt->fetchAll();
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
    <title>Referral Pipeline — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Wider page for kanban */
        .page-body { padding: 28px 24px; }
        .kanban-board { grid-template-columns: repeat(6,1fr); }
        @media(max-width:1200px){ .kanban-board { grid-template-columns: repeat(3,1fr); } }
        @media(max-width:700px) { .kanban-board { grid-template-columns: repeat(2,1fr); } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Referral Pipeline</span>
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
                <div class="user-role">Broker</div>
            </div>
        </div>

        <div class="page-body">
            <div class="page-heading">
                <div>
                    <h1>Referral Pipeline</h1>
                    <p>Manage and progress referrals through each stage of the process.</p>
                </div>
                <a href="referrals.php" class="btn-outline"><i class="bi bi-table"></i> Table View</a>
            </div>

            <div class="kanban-board">
                <?php foreach ($columns as $status => $info): ?>
                    <div class="kanban-col">
                        <div class="kanban-col-header <?= $status ?>">
                            <span><i class="bi bi-<?= $info['icon'] ?>"></i> <?= $info['label'] ?></span>
                            <span class="kanban-count"><?= count($kanbanData[$status]) ?></span>
                        </div>
                        <div class="kanban-cards">
                            <?php foreach ($kanbanData[$status] as $r): ?>
                                <div class="kanban-card" onclick="openQuickUpdate(<?= $r['id'] ?>,'<?= e($r['ref_number']) ?>','<?= e($r['client_name']) ?>','<?= $status ?>','<?= e(addslashes($r['broker_notes']??'')) ?>')">
                                    <div class="ref"><?= e($r['ref_number']) ?></div>
                                    <div class="name"><?= e($r['client_name']) ?></div>
                                    <div class="meta"><?= e($r['loan_type']) ?> &bull; <?= e($r['client_suburb']??'—') ?></div>
                                    <div class="meta"><?= e($r['partner_name']) ?> <span class="status-badge badge-<?= strtolower($r['tier']) ?>" style="font-size:10px"><?= e($r['tier']) ?></span></div>
                                    <div class="amount"><?= money($r['estimated_amount']) ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$kanbanData[$status]): ?>
                                <div style="padding:16px;text-align:center;color:#718096;font-size:12px">No referrals</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Update Modal -->
<div class="modal-overlay" id="quickModal" style="display:none">
    <div class="modal-box">
        <h2>Update Referral</h2>
        <form method="POST" action="referrals.php">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="referral_id" id="qrid">
            <div id="qmeta" style="margin-bottom:14px;font-size:13px;color:#718096"></div>

            <div class="form-group" style="margin-bottom:14px">
                <label>Move to Stage</label>
                <select name="new_status" id="qstatus" onchange="toggleUpfront(this.value)">
                    <option value="pending">New Leads (Pending)</option>
                    <option value="qualified">Qualified</option>
                    <option value="lodged">Lodged</option>
                    <option value="approved">Approved</option>
                    <option value="settled">Settled</option>
                    <option value="declined">Declined</option>
                </select>
            </div>

            <div id="qUpfrontGroup" style="display:none;margin-bottom:14px" class="form-group">
                <label>Broker Upfront Commission (AUD) *</label>
                <input type="number" name="broker_upfront" step="0.01" min="0" placeholder="e.g. 8500.00">
                <span class="form-hint">Partner commission is automatically calculated from this amount × tier rate.</span>
            </div>

            <div class="form-group" style="margin-bottom:14px">
                <label>Broker Notes</label>
                <textarea name="broker_notes" id="qnotes" rows="3" placeholder="Add notes visible to the partner..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeQuick()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openQuickUpdate(rid, ref, client, status, notes) {
    document.getElementById('qrid').value    = rid;
    document.getElementById('qmeta').textContent = ref + ' — ' + client;
    document.getElementById('qstatus').value = status;
    document.getElementById('qnotes').value  = notes;
    toggleUpfront(status);
    document.getElementById('quickModal').style.display = 'flex';
}
function closeQuick() { document.getElementById('quickModal').style.display = 'none'; }
function toggleUpfront(s) {
    document.getElementById('qUpfrontGroup').style.display = s === 'settled' ? 'flex' : 'none';
}
document.getElementById('quickModal').addEventListener('click', function(e) {
    if (e.target === this) closeQuick();
});
</script>
</body>
</html>
