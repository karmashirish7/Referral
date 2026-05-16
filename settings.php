<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user = currentUser(); $uid = currentUserId(); $role = currentRole();
$unread = unreadNotificationCount($sb, $uid);
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name   = trim($_POST['name'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $suburb = trim($_POST['suburb'] ?? '');
        $state  = trim($_POST['state'] ?? '');
        $ce     = isset($_POST['consent_email']) ? 1 : 0;
        if (!$name) {
            $error = 'Name is required.';
        } else {
            $avatar = strtoupper(substr($name, 0, 1) . (strpos($name, ' ') !== false ? substr(strstr($name, ' '), 1, 1) : substr($name, 1, 1)));
            $sb->from('users')->eq('id', $uid)->update(['name' => $name, 'phone' => $phone, 'suburb' => $suburb, 'state' => $state, 'consent_email' => $ce, 'avatar' => $avatar]);
            $updated = $sb->from('users')->eq('id', $uid)->single();
            $_SESSION['user'] = $updated; $user = $updated;
            logAction($sb, $uid, $user['name'], 'profile_updated', 'user', $uid, 'Profile updated');
            $success = 'Profile updated successfully.';
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $row  = $sb->from('users')->eq('id', $uid)->select('password')->single();
        $hash = $row['password'] ?? '';
        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $sb->from('users')->eq('id', $uid)->update(['password' => password_hash($new, PASSWORD_DEFAULT)]);
            logAction($sb, $uid, $user['name'], 'password_changed', 'user', $uid, 'Password changed');
            $success = 'Password changed successfully.';
        }
    }
}

$states    = ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'];
$notifList = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Settings</span>
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
            <div class="page-heading"><div><h1>Account Settings</h1><p>Manage your profile and security.</p></div></div>
            <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="two-col">
                <div class="card">
                    <div class="card-title">Profile Information</div>
                    <form method="POST">
                        <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
                        <div class="form-group"><label>Email</label><input type="email" value="<?= e($user['email']) ?>" disabled style="background:#f8fafc;color:#718096"></div>
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
                            <div class="form-group"><label>Suburb</label><input type="text" name="suburb" value="<?= e($user['suburb'] ?? '') ?>"></div>
                            <div class="form-group"><label>State</label>
                                <select name="state"><?php foreach ($states as $s): ?><option value="<?= $s ?>" <?= ($user['state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
                            <input type="checkbox" name="consent_email" id="ce" <?= ($user['consent_email'] ?? 0) ? 'checked' : '' ?> style="width:auto">
                            <label for="ce" style="margin:0;font-size:13px;text-transform:none;letter-spacing:0">Email notifications</label>
                        </div>
                        <button type="submit" name="update_profile" class="btn-primary" style="width:100%">Save Profile</button>
                    </form>
                </div>
                <div class="card">
                    <div class="card-title">Change Password</div>
                    <form method="POST">
                        <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                        <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Minimum 8 characters" required></div>
                        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
                        <button type="submit" name="change_password" class="btn-primary" style="width:100%">Change Password</button>
                    </form>
                    <hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0">
                    <div class="card-title" style="margin-bottom:12px">Account Info</div>
                    <div style="font-size:13px;color:#718096;line-height:2">
                        Role: <strong><?= ucfirst($user['role']) ?></strong><br>
                        Tier: <strong><?= $user['tier'] ?></strong><br>
                        Rate: <strong><?= $user['commission_rate'] ?>%</strong><br>
                        Member since: <strong><?= date('d M Y', strtotime($user['created_at'])) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
