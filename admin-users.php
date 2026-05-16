<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

$user = currentUser(); $uid = currentUserId();
$unread = unreadNotificationCount($sb, $uid);
$addError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)($_POST['user_id'] ?? 0);

    if (isset($_POST['approve_user'])) {
        $sb->from('users')->eq('id', $targetId)->update(['status' => 'active']);
        $ti = $sb->from('users')->eq('id', $targetId)->select('name,email')->single();
        addNotification($sb, $targetId, 'Account Approved', 'Your account has been approved. You can now log in to the portal.');
        logAction($sb, $uid, $user['name'], 'user_approved', 'user', $targetId, "Approved account for {$ti['name']} ({$ti['email']})");
        header("Location: admin-users.php?msg=approved"); exit;

    } elseif (isset($_POST['suspend_user'])) {
        $sb->from('users')->eq('id', $targetId)->update(['status' => 'suspended']);
        logAction($sb, $uid, $user['name'], 'user_suspended', 'user', $targetId, "Suspended user #{$targetId}");
        header("Location: admin-users.php?msg=suspended"); exit;

    } elseif (isset($_POST['update_tier'])) {
        $tier = $_POST['tier'];
        $rate = ['Gold' => 25, 'Silver' => 20, 'Bronze' => 15][$tier] ?? 20;
        $sb->from('users')->eq('id', $targetId)->update(['tier' => $tier, 'commission_rate' => $rate]);
        $ti = $sb->from('users')->eq('id', $targetId)->select('name')->single();
        addNotification($sb, $targetId, 'Tier Updated', "Your commission tier has been updated to {$tier} ({$rate}%).");
        logAction($sb, $uid, $user['name'], 'user_tier_changed', 'user', $targetId, "Changed tier to {$tier} for {$ti['name']}");
        header("Location: admin-users.php?msg=tier"); exit;

    } elseif (isset($_POST['update_rate'])) {
        $rate = (float)$_POST['commission_rate'];
        $sb->from('users')->eq('id', $targetId)->update(['commission_rate' => $rate]);
        logAction($sb, $uid, $user['name'], 'commission_rate_changed', 'user', $targetId, "Custom rate set to {$rate}% for user #{$targetId}");
        header("Location: admin-users.php?msg=rate"); exit;

    } elseif (isset($_POST['add_user'])) {
        $newName   = trim($_POST['new_name'] ?? '');
        $newEmail  = trim($_POST['new_email'] ?? '');
        $newPhone  = trim($_POST['new_phone'] ?? '');
        $newRole   = $_POST['new_role']     ?? 'partner';
        $newTier   = $_POST['new_tier']     ?? 'Silver';
        $newPass   = $_POST['new_password'] ?? '';
        $newStatus = $_POST['new_status']   ?? 'active';

        if (!$newName || !$newEmail || !$newPass) {
            $addError = 'Name, email and password are required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $addError = 'Invalid email address.';
        } elseif (strlen($newPass) < 8) {
            $addError = 'Password must be at least 8 characters.';
        } elseif ($sb->from('users')->eq('email', $newEmail)->count() > 0) {
            $addError = 'An account with this email already exists.';
        } else {
            $rateMap = ['Gold' => 25, 'Silver' => 20, 'Bronze' => 15];
            $newRate = in_array($newRole, ['broker','admin','auditor']) ? 0 : ($rateMap[$newTier] ?? 20);
            $avatar  = strtoupper(substr($newName,0,1) . (strpos($newName,' ')!==false ? substr(strstr($newName,' '),1,1) : substr($newName,1,1)));
            $row = $sb->from('users')->insert([
                'name' => $newName, 'email' => $newEmail, 'password' => password_hash($newPass, PASSWORD_DEFAULT),
                'role' => $newRole, 'status' => $newStatus, 'tier' => $newTier,
                'commission_rate' => $newRate, 'phone' => $newPhone, 'avatar' => $avatar,
            ]);
            if ($row) {
                logAction($sb, $uid, $user['name'], 'user_created', 'user', $row['id'], "Admin created {$newName} ({$newEmail}) — role: {$newRole}");
                if ($newStatus === 'active') {
                    addNotification($sb, $row['id'], 'Account Created', 'Your account has been created by an administrator.');
                }
                header("Location: admin-users.php?msg=created"); exit;
            }
        }
    }
}

$roleFilter   = $_GET['role']   ?? '';
$statusFilter = $_GET['status'] ?? '';

$query = $sb->from('users');
if ($roleFilter)   $query = $query->eq('role', $roleFilter);
if ($statusFilter) $query = $query->eq('status', $statusFilter);
$users = $query->order('created_at', false)->get();

$pendingCount = $sb->from('users')->eq('status', 'pending')->count();
$notifList    = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management — Network Portal</title>
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
                            <div><div class="notif-item-title"><?= e($n['title']) ?></div><div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div></div>
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
                    <?= ['approved'=>'User approved.','suspended'=>'User suspended.','tier'=>'Tier updated.','rate'=>'Rate updated.','created'=>'User created.'][$_GET['msg']] ?? '' ?>
                </div>
            <?php endif; ?>
            <?php if ($addError): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($addError) ?></div><?php endif; ?>

            <div class="page-heading">
                <div><h1>User Management</h1><p><?= count($users) ?> users<?= $pendingCount > 0 ? " — <strong>{$pendingCount} pending approval</strong>" : '' ?></p></div>
                <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'"><i class="bi bi-person-plus"></i> Add User</button>
            </div>

            <form method="GET" style="display:flex;gap:10px;margin-bottom:20px">
                <select name="role" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <?php foreach (['partner','broker','admin','auditor'] as $r): ?><option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
                </select>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach (['active','pending','suspended'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                </select>
                <a href="admin-users.php" class="btn-outline btn-sm">Clear</a>
            </form>

            <div class="table-card">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Tier</th><th>Rate</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><div style="display:flex;align-items:center;gap:8px"><div style="width:32px;height:32px;background:#1F3864;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700"><?= e($u['avatar']) ?></div><?= e($u['name']) ?></div></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= ucfirst($u['role']) ?></td>
                                <td><?= userStatusBadge($u['status']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'partner'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <select name="tier" onchange="this.form.submit()" style="border:none;background:transparent;font-size:13px;cursor:pointer">
                                            <?php foreach (['Gold','Silver','Bronze'] as $t): ?><option value="<?= $t ?>" <?= $u['tier']===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="update_tier" value="1">
                                    </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'partner'): ?>
                                    <form method="POST" style="display:flex;align-items:center;gap:4px">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="number" name="commission_rate" value="<?= $u['commission_rate'] ?>" step="0.5" min="0" max="100" style="width:60px;border:1px solid #e2e8f0;border-radius:4px;padding:3px 6px;font-size:12px">
                                        <span style="font-size:12px">%</span>
                                        <button type="submit" name="update_rate" value="1" class="btn-outline btn-sm">Set</button>
                                    </form>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <div style="display:flex;gap:6px">
                                        <?php if ($u['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" name="approve_user" value="1" class="btn-success btn-sm" data-confirm="Approve this user?">Approve</button></form>
                                        <?php endif; ?>
                                        <?php if ($u['status'] !== 'suspended' && $u['id'] !== $uid): ?>
                                            <form method="POST" style="display:inline"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" name="suspend_user" value="1" class="btn-warning btn-sm" data-confirm="Suspend this user?">Suspend</button></form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i> No users found.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addModal" style="display:none">
    <div class="modal-box" style="max-width:480px">
        <h2>Add New User</h2>
        <form method="POST">
            <div class="form-group" style="margin-bottom:12px"><label>Full Name *</label><input type="text" name="new_name" required></div>
            <div class="form-group" style="margin-bottom:12px"><label>Email *</label><input type="email" name="new_email" required></div>
            <div class="form-group" style="margin-bottom:12px"><label>Phone</label><input type="text" name="new_phone"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group"><label>Role</label>
                    <select name="new_role">
                        <?php foreach (['partner','broker','admin','auditor'] as $r): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Tier</label>
                    <select name="new_tier">
                        <?php foreach (['Gold','Silver','Bronze'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="form-group"><label>Password *</label><input type="password" name="new_password" placeholder="Min 8 chars" required></div>
                <div class="form-group"><label>Status</label>
                    <select name="new_status"><option value="active">Active</option><option value="pending">Pending</option></select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" name="add_user" value="1" class="btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
