<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole(['admin','auditor']);

$user = currentUser(); $uid = currentUserId(); $role = currentRole();
$unread = unreadNotificationCount($sb, $uid);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$allLogs = $sb->rpc('get_audit_log', [
    'p_action'    => $_GET['action']    ?? '',
    'p_user_id'   => (int)($_GET['user_id'] ?? 0),
    'p_date_from' => $_GET['date_from'] ?? '',
    'p_date_to'   => $_GET['date_to']   ?? '',
    'p_search'    => trim($_GET['search'] ?? ''),
]);
$allLogs    = is_array($allLogs) ? $allLogs : [];
$total      = count($allLogs);
$totalPages = max(1, ceil($total / $perPage));
$logs       = array_slice($allLogs, ($page - 1) * $perPage, $perPage);

$actions  = array_values(array_unique(array_column($allLogs, 'action')));
sort($actions);
$allUsers  = $sb->from('users')->select('id,name')->order('name')->get();
$notifList = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();

$actionColors = ['login'=>'dot-navy','logout'=>'dot-orange','registered'=>'dot-blue',
    'referral_submitted'=>'dot-green','status_changed'=>'dot-blue','commission_paid'=>'dot-green','user_approved'=>'dot-green'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Audit Log</span>
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
            <div class="page-heading"><div><h1>Audit Log</h1><p>Complete activity history — <?= $total ?> records.</p></div></div>
            <form method="GET" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap">
                <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search..." class="filter-select">
                <select name="action" class="filter-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= ($_GET['action'] ?? '') === $a ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ',$a))) ?></option><?php endforeach; ?>
                </select>
                <select name="user_id" class="filter-select">
                    <option value="">All Users</option>
                    <?php foreach ($allUsers as $u): ?><option value="<?= $u['id'] ?>" <?= ($_GET['user_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>" class="filter-select">
                <input type="date" name="date_to"   value="<?= e($_GET['date_to']   ?? '') ?>" class="filter-select">
                <button type="submit" class="btn-primary btn-sm">Filter</button>
                <a href="audit.php" class="btn-outline btn-sm">Clear</a>
            </form>
            <div class="table-card">
                <table>
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
                                <td><strong><?= e($l['user_name']) ?></strong><?php if (!empty($l['user_email'])): ?><br><span style="font-size:11px;color:#718096"><?= e($l['user_email']) ?></span><?php endif; ?></td>
                                <td><span class="activity-dot <?= $actionColors[$l['action']] ?? 'dot-navy' ?>" style="display:inline-block;margin-right:4px"></span><?= e(ucwords(str_replace('_',' ',$l['action']))) ?></td>
                                <td><?= e($l['entity_type'] ?? '—') ?><?= $l['entity_id'] ? ' #'.$l['entity_id'] : '' ?></td>
                                <td><?= e($l['description']) ?></td>
                                <td style="font-size:11px;color:#718096"><?= e($l['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-journal"></i> No entries found.</div></td></tr><?php endif; ?>
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
