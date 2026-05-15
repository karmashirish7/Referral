<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) { header("Location: dashboard.php"); exit; }

$error   = '';
$success = '';

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
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash   = password_hash($pass, PASSWORD_DEFAULT);
            $avatar = strtoupper(substr($name, 0, 1) . (strpos($name, ' ') !== false ? substr(strstr($name, ' '), 1, 1) : substr($name, 1, 1)));

            // Tier defaults: partner starts as Silver; brokers/admins start as Gold
            $tier = in_array($role, ['broker','admin','auditor']) ? 'Gold' : 'Silver';
            $rate = $tier === 'Gold' ? 25.00 : 20.00;

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role, status, tier, commission_rate, phone, suburb, state, consent_email, avatar)
                VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $email, $hash, $role, $tier, $rate, $phone, $suburb, $state, $consent, $avatar]);
            $uid = lastId($pdo, 'users');

            logAction($pdo, $uid, $name, 'registered', 'user', $uid, "New $role account registered: $email");

            $success = 'Registration successful! Your account is pending admin approval. You will be notified once approved.';
        }
    }
}

$states = ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width:520px">

        <div class="login-logo">
            <div class="icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
            <h1>Create Account</h1>
            <p>Join the Referral Network Portal</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <div style="text-align:center;margin-top:16px;"><a href="index.php" class="btn-primary">Back to Sign In</a></div>
        <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. Harry Smith" required>
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role">
                        <option value="partner" <?= ($_POST['role']??'partner')==='partner'?'selected':'' ?>>Partner</option>
                        <option value="broker"  <?= ($_POST['role']??'')==='broker' ?'selected':'' ?>>Broker</option>
                        <option value="auditor" <?= ($_POST['role']??'')==='auditor'?'selected':'' ?>>Auditor</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label>Email Address *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@company.com.au" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="0400 000 000">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <select name="state">
                        <option value="">— Select State —</option>
                        <?php foreach ($states as $s): ?>
                            <option value="<?= $s ?>" <?= ($_POST['state']??'')===$s?'selected':'' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:16px">
                <label>Suburb</label>
                <input type="text" name="suburb" value="<?= htmlspecialchars($_POST['suburb'] ?? '') ?>" placeholder="e.g. Bondi Beach">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>

            <div class="consent-box" style="margin-bottom:16px">
                <input type="checkbox" name="consent_email" id="consent_email" checked>
                <label for="consent_email">I consent to receive email notifications about my referrals and commission updates. (Spam Act 2003 compliant — you may unsubscribe at any time.)</label>
            </div>

            <p style="font-size:12px;color:#718096;margin-bottom:14px">By registering you agree to our <a href="#" style="color:#1F3864">Privacy Policy</a> (Privacy Act 1988). Your data will be stored securely for up to 7 years.</p>

            <button type="submit" class="login-btn">Create Account</button>
        </form>

        <div class="login-footer-links" style="margin-top:14px">
            Already have an account? <a href="index.php">Sign In</a>
        </div>

        <?php endif; ?>
    </div>

    <div class="login-page-footer">
        &copy; <?= date('Y') ?> Referral Network Portal — Professional Use Only
    </div>
</div>
<link rel="stylesheet" href="assets/css/style.css">
<script>
// Allow form-row grid on login card
document.querySelectorAll('.login-card .form-row').forEach(el => el.style.display = 'grid');
</script>
</body>
</html>
