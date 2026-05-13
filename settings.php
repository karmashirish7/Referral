<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user   = currentUser();
$uid    = currentUserId();
$role   = currentRole();
$unread = unreadNotificationCount($pdo, $uid);
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name   = trim($_POST['name'] ?? '');
        $phone  = trim($_POST['phone'] ?? '');
        $suburb = trim($_POST['suburb'] ?? '');
        $state  = trim($_POST['state'] ?? '');
        $consentEmail = isset($_POST['consent_email']) ? 1 : 0;

        if (!$name) {
            $error = 'Name is required.';
        } else {
            $avatar = strtoupper(substr($name,0,1) . (strpos($name,' ')!==false ? substr(strstr($name,' '),1,1) : substr($name,1,1)));
            $pdo->prepare("UPDATE users SET name=?, phone=?, suburb=?, state=?, consent_email=?, avatar=? WHERE id=?")
                ->execute([$name, $phone, $suburb, $state, $consentEmail, $avatar, $uid]);

            // Refresh session
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$uid]);
            $_SESSION['user'] = $stmt->fetch();
            $user = $_SESSION['user'];

            logAction($pdo, $uid, $user['name'], 'profile_updated', 'user', $uid, 'Profile updated');
            $success = 'Profile updated successfully.';
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?"); $stmt->execute([$uid]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            logAction($pdo, $uid, $user['name'], 'password_changed', 'user', $uid, 'Password changed');
            $success = 'Password changed successfully.';
        }
    }
}

$states    = ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'];
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <div><h1>Account Settings</h1><p>Update your profile, password, and notification preferences.</p></div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <div class="two-col">
                <!-- Profile -->
                <div class="card">
                    <div class="card-title">Profile Information</div>
                    <form method="POST">
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Full Name *</label>
                            <input type="text" name="name" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Email Address</label>
                            <input type="email" value="<?= e($user['email']) ?>" disabled style="background:#f8fafc;color:#718096">
                            <span class="form-hint">Email cannot be changed. Contact admin if needed.</span>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="0400 000 000">
                            </div>
                            <div class="form-group">
                                <label>State</label>
                                <select name="state">
                                    <option value="">— Select —</option>
                                    <?php foreach ($states as $s): ?>
                                        <option value="<?= $s ?>" <?= ($user['state']??'')===$s?'selected':'' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:14px">
                            <label>Suburb</label>
                            <input type="text" name="suburb" value="<?= e($user['suburb'] ?? '') ?>" placeholder="e.g. Bondi Beach">
                        </div>

                        <?php if ($role === 'partner'): ?>
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:14px">
                            <div style="font-size:12px;color:#718096;margin-bottom:4px">Commission Tier (set by admin)</div>
                            <strong><?= e(tierLabel($user['tier'])) ?></strong> — Rate: <?= $user['commission_rate'] ?>%
                        </div>
                        <?php endif; ?>

                        <div class="consent-box" style="margin-bottom:16px">
                            <input type="checkbox" name="consent_email" id="consent_email" <?= $user['consent_email'] ? 'checked' : '' ?>>
                            <label for="consent_email">Receive email notifications about referral updates and commission payments. (Spam Act 2003 — unsubscribe anytime.)</label>
                        </div>

                        <button type="submit" name="update_profile" value="1" class="btn-primary">Save Profile</button>
                    </form>
                </div>

                <!-- Password -->
                <div>
                    <div class="card" style="margin-bottom:20px">
                        <div class="card-title">Change Password</div>
                        <form method="POST">
                            <div class="form-group" style="margin-bottom:14px">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-group" style="margin-bottom:14px">
                                <label>New Password</label>
                                <input type="password" name="new_password" required>
                                <span class="form-hint">Minimum 8 characters</span>
                            </div>
                            <div class="form-group" style="margin-bottom:16px">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" value="1" class="btn-primary">Update Password</button>
                        </form>
                    </div>

                    <!-- Account Info -->
                    <div class="card">
                        <div class="card-title">Account Information</div>
                        <table style="font-size:13px">
                            <tr><td style="padding:6px 0;color:#718096;width:130px">Role</td><td style="text-transform:capitalize"><?= e($user['role']) ?></td></tr>
                            <tr><td style="padding:6px 0;color:#718096">Status</td><td><?= userStatusBadge($user['status']) ?></td></tr>
                            <tr><td style="padding:6px 0;color:#718096">Member Since</td><td><?= date('d M Y', strtotime($user['created_at'])) ?></td></tr>
                            <?php if ($role === 'partner'): ?>
                                <tr><td style="padding:6px 0;color:#718096">Tier</td><td><?= e($user['tier']) ?></td></tr>
                                <tr><td style="padding:6px 0;color:#718096">Commission Rate</td><td><?= $user['commission_rate'] ?>%</td></tr>
                            <?php endif; ?>
                        </table>
                        <hr class="divider">
                        <p style="font-size:12px;color:#718096">
                            Your data is stored securely in accordance with the Privacy Act 1988.
                            Data is retained for 7 years. <a href="#" style="color:#1F3864">Request your data export</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
