<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('partner');

$user = currentUser(); $uid = currentUserId();
$unread = unreadNotificationCount($sb, $uid);

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName   = trim($_POST['client_name']   ?? '');
    $clientEmail  = trim($_POST['client_email']  ?? '');
    $clientPhone  = trim($_POST['client_phone']  ?? '');
    $clientSuburb = trim($_POST['client_suburb'] ?? '');
    $clientState  = trim($_POST['client_state']  ?? '');
    $loanType     = $_POST['loan_type']           ?? 'Owner-Occupied';
    $estAmount    = (float)($_POST['estimated_amount'] ?? 0);
    $brokerId     = (int)($_POST['broker_id']    ?? 0) ?: null;
    $notes        = trim($_POST['notes']         ?? '');
    $consent      = isset($_POST['consent']) ? 1 : 0;

    if (!$clientName) {
        $error = 'Client name is required.';
    } elseif (!$consent) {
        $error = 'Client consent is required.';
    } else {
        $refNumber = generateRefNumber($sb);
        $rate      = (float)($user['commission_rate'] ?? 20);

        $row = $sb->from('referrals')->insert([
            'ref_number'       => $refNumber,
            'partner_id'       => $uid,
            'broker_id'        => $brokerId,
            'client_name'      => $clientName,
            'client_email'     => $clientEmail,
            'client_phone'     => $clientPhone,
            'client_suburb'    => $clientSuburb,
            'client_state'     => $clientState,
            'loan_type'        => $loanType,
            'estimated_amount' => $estAmount,
            'consent'          => $consent,
            'consent_timestamp'=> $consent ? date('c') : null,
            'notes'            => $notes,
            'status'           => 'pending',
        ]);

        if ($row) {
            $sb->from('commissions')->insert([
                'referral_id' => $row['id'],
                'partner_id'  => $uid,
                'rate'        => $rate,
                'amount'      => 0,
            ]);

            logAction($sb, $uid, $user['name'], 'referral_submitted', 'referral', $row['id'],
                "Submitted {$refNumber} for {$clientName}");

            if ($brokerId) {
                addNotification($sb, $brokerId, 'New Referral Assigned',
                    "A new referral {$refNumber} for {$clientName} has been assigned to you.");
            }

            $admins = $sb->from('users')->eq('role', 'admin')->eq('status', 'active')->select('id')->get();
            foreach ($admins as $admin) {
                addNotification($sb, $admin['id'], 'New Referral Submitted',
                    "{$user['name']} submitted referral {$refNumber} for {$clientName}.");
            }

            header("Location: referrals.php?submitted=1"); exit;
        } else {
            $error = 'Failed to submit referral. Please try again.';
        }
    }
}

$brokers   = $sb->from('users')->eq('role', 'broker')->eq('status', 'active')->select('id,name')->order('name')->get();
$notifList = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();
$loanTypes = ['Owner-Occupied','Investment','Refinance','Commercial','Construction','SMSF','Other'];
$states    = ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Referral — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Submit Referral</span>
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
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role"><?= e(tierLabel($user['tier'])) ?></div></div>
        </div>

        <div class="page-body">
            <div class="page-heading"><div><h1>Submit New Referral</h1><p>Your commission rate: <strong><?= $user['commission_rate'] ?>%</strong> (<?= $user['tier'] ?> tier)</p></div><a href="referrals.php" class="btn-outline"><i class="bi bi-arrow-left"></i> Back</a></div>

            <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST">
                <div class="two-col">
                    <div class="card">
                        <div class="card-title">Client Details</div>
                        <div class="form-group"><label>Client Full Name *</label><input type="text" name="client_name" value="<?= e($_POST['client_name'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Client Email</label><input type="email" name="client_email" value="<?= e($_POST['client_email'] ?? '') ?>"></div>
                        <div class="form-group"><label>Client Phone</label><input type="text" name="client_phone" value="<?= e($_POST['client_phone'] ?? '') ?>"></div>
                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
                            <div class="form-group"><label>Suburb</label><input type="text" name="client_suburb" value="<?= e($_POST['client_suburb'] ?? '') ?>"></div>
                            <div class="form-group"><label>State</label>
                                <select name="client_state"><?php foreach ($states as $s): ?><option value="<?= $s ?>" <?= ($_POST['client_state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                        <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
                            <input type="checkbox" name="consent" id="consent" <?= isset($_POST['consent']) ? 'checked' : '' ?> style="width:auto" required>
                            <label for="consent" style="margin:0;font-size:13px;text-transform:none;letter-spacing:0">Client has given consent to be contacted *</label>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-title">Loan Details</div>
                        <div class="form-group"><label>Loan Type</label>
                            <select name="loan_type">
                                <?php foreach ($loanTypes as $lt): ?><option value="<?= $lt ?>" <?= ($_POST['loan_type'] ?? '') === $lt ? 'selected' : '' ?>><?= $lt ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Estimated Loan Amount (AUD)</label><input type="number" name="estimated_amount" value="<?= e($_POST['estimated_amount'] ?? '') ?>" step="1000" min="0" placeholder="e.g. 500000"></div>
                        <div class="form-group"><label>Preferred Broker (optional)</label>
                            <select name="broker_id">
                                <option value="">— Auto-assign —</option>
                                <?php foreach ($brokers as $b): ?><option value="<?= $b['id'] ?>" <?= ($_POST['broker_id'] ?? '') == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Notes</label><textarea name="notes" rows="4" placeholder="Any additional notes..."><?= e($_POST['notes'] ?? '') ?></textarea></div>
                    </div>
                </div>

                <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
                    <a href="referrals.php" class="btn-outline">Cancel</a>
                    <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Submit Referral</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
