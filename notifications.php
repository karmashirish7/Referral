<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user = currentUser(); $uid = currentUserId(); $role = currentRole();

if (isset($_GET['mark_all_read'])) {
    $sb->from('notifications')->eq('user_id', $uid)->update(['is_read' => 1]);
    header("Location: notifications.php"); exit;
}
if (isset($_GET['read'])) {
    $sb->from('notifications')->eq('id', (int)$_GET['read'])->eq('user_id', $uid)->update(['is_read' => 1]);
    header("Location: notifications.php"); exit;
}

$unread        = unreadNotificationCount($sb, $uid);
$notifications = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->get();
$notifList     = array_slice($notifications, 0, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Notifications</span>
            <div class="header-spacer"></div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role"><?= ucfirst($role) ?></div></div>
        </div>
        <div class="page-body">
            <div class="page-heading">
                <div><h1>Notifications</h1><p>All your alerts and updates.</p></div>
                <?php if ($unread > 0): ?><a href="?mark_all_read=1" class="btn-outline"><i class="bi bi-check2-all"></i> Mark All as Read</a><?php endif; ?>
            </div>
            <div class="card">
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>" style="border-radius:8px;margin-bottom:4px">
                        <div class="notif-item-dot"></div>
                        <div style="flex:1">
                            <div class="notif-item-title"><?= e($n['title']) ?></div>
                            <div style="font-size:13px;color:#4a5568;margin:3px 0"><?= e($n['message']) ?></div>
                            <div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div>
                        </div>
                        <?php if (!$n['is_read']): ?><a href="?read=<?= $n['id'] ?>" class="btn-outline btn-sm">Mark Read</a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$notifications): ?><div class="empty-state"><i class="bi bi-bell"></i> No notifications yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
