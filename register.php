<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) { header("Location: dashboard.php"); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $suburb  = trim($_POST['suburb'] ?? '');
    $state   = trim($_POST['state'] ?? '');
    $role    = $_POST['role'] ?? 'partner';
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $consent = isset($_POST['consent_email']) ? 1 : 0;

    if (!$name || !$email || !$pass) {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($sb->from('users')->eq('email', $email)->count() > 0) {
        $error = 'An account with this email already exists.';
    } else {
        $hash   = password_hash($pass, PASSWORD_DEFAULT);
        $avatar = strtoupper(substr($name, 0, 1) . (strpos($name, ' ') !== false ? substr(strstr($name, ' '), 1, 1) : substr($name, 1, 1)));
        $tier   = in_array($role, ['broker', 'admin', 'auditor']) ? 'Gold' : 'Silver';
        $rate   = $tier === 'Gold' ? 25.00 : 20.00;

        $row = $sb->from('users')->insert([
            'name' => $name, 'email' => $email, 'password' => $hash,
            'role' => $role, 'status' => 'pending', 'tier' => $tier,
            'commission_rate' => $rate, 'phone' => $phone, 'suburb' => $suburb,
            'state' => $state, 'consent_email' => $consent, 'avatar' => $avatar,
        ]);
        if ($row) {
            logAction($sb, $row['id'], $name, 'registered', 'user', $row['id'], "New {$role} account: {$email}");
            $success = 'Registration successful! Your account is pending admin approval.';
        } else {
            $error = 'Registration failed. Please try again.';
        }
    }
}
$states = ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width:520px">
        <div class="login-logo">
            <div class="icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            <h1>Create Account</h1><p>Join the Referral Network Portal</p>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <div style="text-align:center;margin-top:16px"><a href="index.php" class="btn-primary">Back to Sign In</a></div>
        <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Full Name *</label><input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required></div>
            <div class="form-group"><label>Email Address *</label><input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required></div>
            <div class="form-group"><label>Role</label>
                <select name="role">
                    <option value="partner" <?= ($_POST['role'] ?? 'partner') === 'partner' ? 'selected' : '' ?>>Partner</option>
                    <option value="broker"  <?= ($_POST['role'] ?? '') === 'broker' ? 'selected' : '' ?>>Broker</option>
                </select>
            </div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
                <div class="form-group"><label>Suburb</label><input type="text" name="suburb" value="<?= htmlspecialchars($_POST['suburb'] ?? '') ?>"></div>
                <div class="form-group"><label>State</label>
                    <select name="state"><?php foreach ($states as $s): ?><option value="<?= $s ?>" <?= ($_POST['state'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
                </div>
            </div>
            <div class="form-group"><label>Password *</label><input type="password" name="password" placeholder="Minimum 8 characters" required></div>
            <div class="form-group"><label>Confirm Password *</label><input type="password" name="confirm_password" required></div>
            <div class="form-group" style="flex-direction:row;align-items:center;gap:8px">
                <input type="checkbox" name="consent_email" id="ce" <?= isset($_POST['consent_email']) ? 'checked' : '' ?> style="width:auto">
                <label for="ce" style="margin:0;font-size:13px;text-transform:none;letter-spacing:0">I agree to receive email notifications</label>
            </div>
            <button type="submit" class="login-btn">Create Account</button>
        </form>
        <div class="login-footer-links">Already have an account? <a href="index.php">Sign In</a></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
