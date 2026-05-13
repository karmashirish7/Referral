<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('admin');

$user   = currentUser();
$uid    = currentUserId();
$unread = unreadNotificationCount($pdo, $uid);

// Handle approve / suspend / tier change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)($_POST['user_id'] ?? 0);

    if (isset($_POST['approve_user'])) {
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$targetId]);
        $info = $pdo->prepare("SELECT name,email FROM users WHERE id=?");
        $info->execute([$targetId]); $ti = $info->fetch();
        addNotification($pdo, $targetId, 'Account Approved', 'Your account has been approved. You can now log in to the portal.');
        logAction($pdo, $uid, $user['name'], 'user_approved', 'user', $targetId, "Approved account for {$ti['name']} ({$ti['email']})");
        header("Location: admin-users.php?msg=approved"); exit;

    } elseif (isset($_POST['suspend_user'])) {
        $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?")->execute([$targetId]);
        $info = $pdo->prepare("SELECT name FROM users WHERE id=?")->execute([$targetId]);
        logAction($pdo, $uid, $user['name'], 'user_suspended', 'user', $targetId, "Suspended user #$targetId");
        header("Location: admin-users.php?msg=suspended"); exit;

    } elseif (isset($_POST['update_tier'])) {
        $tier = $_POST['tier'];
        $rate = ['Gold'=>25,'Silver'=>20,'Bronze'=>15][$tier] ?? 20;
        $pdo->prepare("UPDATE users SET tier=?, commission_rate=? WHERE id=?")->execute([$tier, $rate, $targetId]);
        $info = $pdo->prepare("SELECT name FROM users WHERE id=?"); $info->execute([$targetId]); $ti = $info->fetch();
        addNotification($pdo, $targetId, 'Tier Updated', "Your commission tier has been updated to $tier (" . $rate . "%).");
        logAction($pdo, $uid, $user['name'], 'user_tier_changed', 'user', $targetId, "Changed tier to $tier for user #$targetId ({$ti['name']})");
        header("Location: admin-users.php?msg=tier"); exit;

    } elseif (isset($_POST['update_rate'])) {
        $rate = (float)$_POST['commission_rate'];
        $pdo->prepare("UPDATE users SET commission_rate=? WHERE id=?")->execute([$rate, $targetId]);
        logAction($pdo, $uid, $user['name'], 'commission_rate_changed', 'user', $targetId, "Custom rate set to $rate% for user #$targetId");
        header("Location: admin-users.php?msg=rate"); exit;

    } elseif (isset($_POST['add_user'])) {
        $newName    = trim($_POST['new_name'] ?? '');
        $newEmail   = trim($_POST['new_email'] ?? '');
        $newPhone   = trim($_POST['new_phone'] ?? '');
        $newRole    = $_POST['new_role'] ?? 'partner';
        $newTier    = $_POST['new_tier'] ?? 'Silver';
        $newPass    = $_POST['new_password'] ?? '';
        $newStatus  = $_POST['new_status'] ?? 'active';

        if (!$newName || !$newEmail || !$newPass) {
            $addError = 'Name, email and password are required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $addError = 'Invalid email address.';
        } elseif (strlen($newPass) < 8) {
            $addError = 'Password must be at least 8 characters.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$newEmail]);
            if ($check->fetch()) {
                $addError = 'An account with this email already exists.';
            } else {
                $rateMap = ['Gold'=>25,'Silver'=>20,'Bronze'=>15];
                $newRate = in_array($newRole, ['broker','admin','auditor']) ? 0 : ($rateMap[$newTier] ?? 20);
                $avatar  = strtoupper(substr($newName,0,1) . (strpos($newName,' ')!==false ? substr(strstr($newName,' '),1,1) : substr($newName,1,1)));
                $hash    = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name,email,password,role,status,tier,commission_rate,phone,avatar) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$newName,$newEmail,$hash,$newRole,$newStatus,$newTier,$newRate,$newPhone,$avatar]);
                $newId = $pdo->lastInsertId();
                logAction($pdo, $uid, $user['name'], 'user_created', 'user', $newId, "Admin created account for $newName ($newEmail) — role: $newRole");
                if ($newStatus === 'active') {
                    addNotification($pdo, $newId, 'Account Created', 'Your account has been created by an administrator. You can now log in.');
                }
                header("Location: admin-users.php?msg=created"); exit;
            }
        }
    }
}

$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where  = [];
$params = [];
if ($roleFilter)   { $where[] = 'role = ?';   $params[] = $roleFilter; }
if ($statusFilter) { $where[] = 'status = ?'; $params[] = $statusFilter; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = $pdo->prepare("SELECT * FROM users $whereSQL ORDER BY created_at DESC");
$users->execute($params);
$users = $users->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();

// Notifications for dropdown
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();

$msgs = ['approved'=>'User approved successfully.','suspended'=>'User suspended.','tier'=>'Tier updated.','rate'=>'Commission rate updated.','created'=>'User created successfully.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <button class="notif-btn" id="notifBtn">
                    <i class="bi bi-bell"></i>
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
            <div class="user-info">
                <div class="user-name"><?= e($user['name']) ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>

        <div class="page-body">
            <?php if (isset($_GET['msg']) && isset($msgs[$_GET['msg']])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $msgs[$_GET['msg']] ?></div>
            <?php endif; ?>

            <div class="page-heading">
                <div>
                    <h1>User Management</h1>
                    <p>Approve registrations, manage tiers, and adjust commission rates.</p>
                </div>
                <div class="heading-actions">
                    <?php if ($pendingCount > 0): ?>
                        <div class="alert alert-info" style="margin:0"><i class="bi bi-person-exclamation"></i>
                            <strong><?= $pendingCount ?></strong> account<?= $pendingCount > 1 ? 's' : '' ?> awaiting approval.
                        </div>
                    <?php endif; ?>
                    <button class="btn-primary" onclick="document.getElementById('addUserModal').style.display='flex'">
                        <i class="bi bi-person-plus"></i> Add User
                    </button>
                </div>
            </div>

            <?php if (!empty($addError)): ?>
                <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= e($addError) ?></div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
                <?php
                $totalPartners = $pdo->query("SELECT COUNT(*) FROM users WHERE role='partner'")->fetchColumn();
                $totalBrokers  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='broker'")->fetchColumn();
                $activeUsers   = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
                ?>
                <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-people"></i></div><div class="stat-label">Partners</div><div class="stat-value"><?= $totalPartners ?></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="bi bi-person-badge"></i></div><div class="stat-label">Brokers</div><div class="stat-value"><?= $totalBrokers ?></div></div>
                <div class="stat-card"><div class="stat-icon orange"><i class="bi bi-hourglass"></i></div><div class="stat-label">Pending Approval</div><div class="stat-value"><?= $pendingCount ?></div></div>
                <div class="stat-card"><div class="stat-icon navy"><i class="bi bi-check2-circle"></i></div><div class="stat-label">Active Users</div><div class="stat-value"><?= $activeUsers ?></div></div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h2>All Users</h2>
                    <form method="GET" class="filter-row">
                        <select name="role" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="partner" <?= $roleFilter==='partner'?'selected':'' ?>>Partner</option>
                            <option value="broker"  <?= $roleFilter==='broker' ?'selected':'' ?>>Broker</option>
                            <option value="admin"   <?= $roleFilter==='admin'  ?'selected':'' ?>>Admin</option>
                            <option value="auditor" <?= $roleFilter==='auditor'?'selected':'' ?>>Auditor</option>
                        </select>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active"    <?= $statusFilter==='active'   ?'selected':'' ?>>Active</option>
                            <option value="pending"   <?= $statusFilter==='pending'  ?'selected':'' ?>>Pending</option>
                            <option value="suspended" <?= $statusFilter==='suspended'?'selected':'' ?>>Suspended</option>
                        </select>
                    </form>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tier</th>
                            <th>Rate</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div class="user-avatar" style="width:28px;height:28px;font-size:10px"><?= e($u['avatar']) ?></div>
                                        <strong><?= e($u['name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= e($u['email']) ?></td>
                                <td><span style="text-transform:capitalize"><?= e($u['role']) ?></span></td>
                                <td>
                                    <?php if ($u['role'] === 'partner'): ?>
                                        <span class="status-badge badge-<?= strtolower($u['tier']) ?>"><?= e($u['tier']) ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $u['role'] === 'partner' ? $u['commission_rate'] . '%' : '—' ?></td>
                                <td><?= userStatusBadge($u['status']) ?></td>
                                <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <?php if ($u['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" name="approve_user" value="1" class="btn-success btn-sm"
                                                        data-confirm="Approve account for <?= e($u['name']) ?>?">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($u['status'] === 'active' && $u['id'] !== $uid): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" name="suspend_user" value="1" class="btn-danger btn-sm"
                                                        data-confirm="Suspend account for <?= e($u['name']) ?>?">Suspend</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($u['role'] === 'partner'): ?>
                                            <button class="btn-info btn-sm" onclick="openTierModal(<?= $u['id'] ?>,'<?= e($u['name']) ?>','<?= e($u['tier']) ?>',<?= $u['commission_rate'] ?>)">
                                                Edit Tier
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                            <tr><td colspan="8"><div class="empty-state"><i class="bi bi-people"></i>No users found.</div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal" style="display:none">
    <div class="modal-box" style="max-width:580px">
        <h2><i class="bi bi-person-plus" style="color:#1F3864"></i> Add New User</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="new_name" placeholder="e.g. Jane Smith" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="new_email" placeholder="jane@company.com" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="new_phone" placeholder="0400 000 000">
                </div>
                <div class="form-group">
                    <label>Password * (min 8 chars)</label>
                    <input type="password" name="new_password" placeholder="Set initial password" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <select name="new_role" id="au_role" onchange="toggleTierField(this.value)">
                        <option value="partner">Partner</option>
                        <option value="broker">Broker</option>
                        <option value="admin">Admin</option>
                        <option value="auditor">Auditor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="new_status">
                        <option value="active">Active (can log in immediately)</option>
                        <option value="pending">Pending (awaiting approval)</option>
                    </select>
                </div>
            </div>
            <div class="form-row" id="au_tierRow">
                <div class="form-group">
                    <label>Commission Tier</label>
                    <select name="new_tier">
                        <option value="Gold">Gold (25%)</option>
                        <option value="Silver" selected>Silver (20%)</option>
                        <option value="Bronze">Bronze (15%)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="color:transparent">—</label>
                    <div style="background:#f0f4f9;border-radius:6px;padding:9px 12px;font-size:13px;color:#718096">
                        Commission rate is set automatically from tier. Edit after creation if needed.
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
                <button type="submit" name="add_user" value="1" class="btn-primary"><i class="bi bi-person-check"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Tier Edit Modal -->
<div class="modal-overlay" id="tierModal" style="display:none">
    <div class="modal-box">
        <h2>Edit Tier & Commission</h2>
        <form method="POST">
            <input type="hidden" name="user_id" id="t_uid">
            <div id="t_name" style="margin-bottom:16px;font-weight:500"></div>

            <div class="form-group" style="margin-bottom:14px">
                <label>Partner Tier</label>
                <select name="tier" id="t_tier" onchange="updateRate(this.value)">
                    <option value="Gold">Gold (25%)</option>
                    <option value="Silver">Silver (20%)</option>
                    <option value="Bronze">Bronze (15%)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px">
                <label>Custom Commission Rate % (overrides tier default)</label>
                <input type="number" name="commission_rate" id="t_rate" step="0.5" min="0" max="50">
                <span class="form-hint">Leave at tier default or enter a custom override rate.</span>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeTier()">Cancel</button>
                <button type="submit" name="update_tier" value="1" class="btn-primary">Save Tier</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
// Re-open add modal if there was a validation error
<?php if (!empty($addError)): ?>
document.getElementById('addUserModal').style.display = 'flex';
<?php endif; ?>

function toggleTierField(role) {
    document.getElementById('au_tierRow').style.display =
        role === 'partner' ? 'grid' : 'none';
}

const tierRates = {Gold:25, Silver:20, Bronze:15};
function openTierModal(uid, name, tier, rate) {
    document.getElementById('t_uid').value  = uid;
    document.getElementById('t_name').textContent = name;
    document.getElementById('t_tier').value = tier;
    document.getElementById('t_rate').value = rate;
    document.getElementById('tierModal').style.display = 'flex';
}
function closeTier() { document.getElementById('tierModal').style.display = 'none'; }
function updateRate(tier) { document.getElementById('t_rate').value = tierRates[tier]; }
document.getElementById('tierModal').addEventListener('click', function(e) { if(e.target===this) closeTier(); });
</script>
</body>
</html>
