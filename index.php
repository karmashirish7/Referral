<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = currentRole();
    if ($role === 'broker')       header("Location: pipeline.php");
    elseif ($role === 'admin')    header("Location: admin-users.php");
    elseif ($role === 'auditor')  header("Location: audit.php");
    else                          header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'partner';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = $sb->from('users')->eq('email', $email)->single();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $error = 'Your account is awaiting admin approval. Please check back soon.';
            } elseif ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact support.';
            } elseif ($user['role'] !== $role) {
                $error = 'Incorrect role selected for this account.';
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user']      = $user;

                require_once 'includes/functions.php';
                logAction($sb, $user['id'], $user['name'], 'login', 'user', $user['id'], 'User logged in');

                if ($role === 'broker')       header("Location: pipeline.php");
                elseif ($role === 'admin')    header("Location: admin-users.php");
                elseif ($role === 'auditor')  header("Location: audit.php");
                else                          header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            <h1>Network Portal</h1>
            <p>Enterprise Referral Administration</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Access Role</label>
                <select name="role">
                    <option value="partner" <?= ($_POST['role'] ?? 'partner') === 'partner' ? 'selected' : '' ?>>Partner</option>
                    <option value="broker"  <?= ($_POST['role'] ?? '') === 'broker'  ? 'selected' : '' ?>>Broker</option>
                    <option value="admin"   <?= ($_POST['role'] ?? '') === 'admin'   ? 'selected' : '' ?>>Admin</option>
                    <option value="auditor" <?= ($_POST['role'] ?? '') === 'auditor' ? 'selected' : '' ?>>Auditor</option>
                </select>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="name@yourcompany.com" required>
            </div>
            <div class="form-group">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                    <label style="margin:0">Password</label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="login-btn">Sign In &nbsp;<i class="bi bi-arrow-right"></i></button>
        </form>

        <div class="login-footer-links">
            New to the network? <a href="register.php">Register</a>
        </div>
        <div class="security-badges">
            <span class="security-badge"><i class="bi bi-shield-lock-fill"></i> Secure 256-bit Encryption</span>
            <span class="security-badge"><i class="bi bi-patch-check-fill"></i> Compliance Verified</span>
        </div>
    </div>
    <div class="login-page-footer">
        &copy; <?= date('Y') ?> Referral Network Portal. All rights reserved.<br>
        <a href="#">Privacy Policy</a> <a href="#">Terms of Service</a>
    </div>
</div>
</body>
</html>
