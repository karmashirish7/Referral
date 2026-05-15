<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole(['admin','auditor']);

$user   = currentUser();
$uid    = currentUserId();
$role   = currentRole();
$unread = unreadNotificationCount($pdo, $uid);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$actionFilter = $_GET['action'] ?? '';
$userFilter   = $_GET['user_id'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$search       = trim($_GET['search'] ?? '');

$where  = [];
$params = [];
if ($actionFilter) { $where[] = 'action = ?';            $params[] = $actionFilter; }
if ($userFilter)   { $where[] = 'al.user_id = ?';        $params[] = $userFilter; }
if ($dateFrom)     { $where[] = 'al.created_at::date >= ?'; $params[] = $dateFrom; }
if ($dateTo)       { $where[] = 'al.created_at::date <= ?'; $params[] = $dateTo; }
if ($search)       { $where[] = '(al.user_name LIKE ? OR al.description LIKE ? OR al.action LIKE ?)';
    $like = "%$search%"; $params = array_merge($params, [$like,$like,$like]); }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM audit_log al $whereSQL");
$total->execute($params); $total = (int)$total->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$logs = $pdo->prepare("
    SELECT al.*, u.email AS user_email
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSQL
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$logs->execute($params); $logs = $logs->fetchAll();

// Unique actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// All users for filter
$allUsers = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();

$actionColors = [
    'login'                 => 'dot-navy',
    'logout'                => 'dot-orange',
    'registered'            => 'dot-blue',
    'referral_submitted'    => 'dot-green',
    'status_changed'        => 'dot-blue',
    'commission_paid'       => 'dot-green',
    'commission_override'   => 'dot-orange',
    'user_approved'         => 'dot-green',
    'user_suspended'        => 'dot-red',
    'user_tier_changed'     => 'dot-orange',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label"><?= $role === 'auditor' ? 'Compliance Audit' : 'Admin Panel' ?></span>
            <div class="header-spacer"></div>
            <div style="position:relative">
                <button class="notif-btn" id="notifBtn"><i class="bi bi-bell"></i>
                    <?php if ($unread > 0): ?><span class="notif-badge"><?= $unread ?></span><?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">Notifications</div>
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
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role"><?= ucfirst($role) ?></div></div>
        </div>

        <div class="page-body">
            <div class="page-heading">
                <div>
                    <h1>Audit Log</h1>
                    <p>Complete record of all system actions for Privacy Act 1988 compliance. Retained 7 years.</p>
                </div>
                <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn-outline">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <form method="GET" class="filter-row" style="flex-wrap:wrap;gap:8px">
                        <input type="text" name="search" class="filter-input" value="<?= e($search) ?>" placeholder="Search..." style="width:160px">
                        <select name="action" class="filter-select">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $a): ?>
                                <option value="<?= $a ?>" <?= $actionFilter===$a?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$a))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="user_id" class="filter-select">
                            <option value="">All Users</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $userFilter==$u['id']?'selected':'' ?>><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" class="filter-input" value="<?= e($dateFrom) ?>">
                        <input type="date" name="date_to"   class="filter-input" value="<?= e($dateTo) ?>">
                        <button type="submit" class="btn-primary btn-sm">Filter</button>
                        <?php if ($actionFilter||$userFilter||$dateFrom||$dateTo||$search): ?>
                            <a href="audit.php" class="btn-outline btn-sm">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="color:#718096;font-size:12px"><?= $log['id'] ?></td>
                                <td style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <div style="font-weight:500"><?= e($log['user_name'] ?? 'System') ?></div>
                                    <div style="font-size:11px;color:#718096"><?= e($log['user_email'] ?? '') ?></div>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <div class="activity-dot <?= $actionColors[$log['action']] ?? 'dot-navy' ?>"></div>
                                        <span style="font-size:12px"><?= e(ucwords(str_replace('_',' ',$log['action']))) ?></span>
                                    </div>
                                </td>
                                <td style="font-size:12px;color:#718096"><?= e($log['entity_type'] ?? '—') ?> <?= $log['entity_id'] ? '#'.$log['entity_id'] : '' ?></td>
                                <td style="font-size:12px"><?= e($log['description']) ?></td>
                                <td style="font-size:12px;font-family:monospace"><?= e($log['ip_address'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?>
                            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-shield-check"></i>No audit entries found.</div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="table-footer">
                    <span>Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> entries</span>
                    <div style="display:flex;gap:6px">
                        <?php for ($i = 1; $i <= min($totalPages,10); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"
                               style="padding:4px 10px;border-radius:4px;text-decoration:none;font-size:12px;
                                      background:<?= $i===$page?'#1F3864':'#f1f5f9'?>;
                                      color:<?= $i===$page?'#fff':'#1F3864'?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>

<?php
// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Timestamp','Actor','Action','Entity Type','Entity ID','Description','IP Address']);
    foreach ($logs as $log) {
        fputcsv($out, [$log['id'], $log['created_at'], $log['user_name'], $log['action'], $log['entity_type'], $log['entity_id'], $log['description'], $log['ip_address']]);
    }
    fclose($out); exit;
}
?>
</body>
</html>
