<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('partner');

$user   = currentUser();
$uid    = currentUserId();
$unread = unreadNotificationCount($pdo, $uid);
$error  = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientName    = trim($_POST['client_name'] ?? '');
    $clientEmail   = trim($_POST['client_email'] ?? '');
    $clientPhone   = trim($_POST['client_phone'] ?? '');
    $clientSuburb  = trim($_POST['client_suburb'] ?? '');
    $clientState   = trim($_POST['client_state'] ?? '');
    $loanType      = $_POST['loan_type'] ?? 'Owner-Occupied';
    $estAmount     = (float)($_POST['estimated_amount'] ?? 0);
    $notes         = trim($_POST['notes'] ?? '');
    $consent       = isset($_POST['consent']) ? 1 : 0;
    $isDraft       = isset($_POST['save_draft']);

    if (!$clientName) {
        $error = 'Client name is required.';
    } elseif (!$consent && !$isDraft) {
        $error = 'You must obtain client consent before submitting.';
    } elseif ($estAmount <= 0) {
        $error = 'Please enter a valid estimated loan amount.';
    } else {
        $status         = $isDraft ? 'pending' : 'pending';
        $refNumber      = generateRefNumber($pdo);
        $consentTs      = $consent ? date('Y-m-d H:i:s') : null;
        $docPath        = null;
        $docName        = null;

        // Handle file upload
        if (!empty($_FILES['document']['name'])) {
            $allowed = ['pdf','docx','doc','xlsx','xls'];
            $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $maxSize = 10 * 1024 * 1024; // 10MB

            if (!in_array($ext, $allowed)) {
                $error = 'Invalid file type. Allowed: PDF, DOCX, XLSX.';
            } elseif ($_FILES['document']['size'] > $maxSize) {
                $error = 'File exceeds 10MB limit.';
            } else {
                $fileName = $refNumber . '-' . time() . '.' . $ext;
                $destPath = 'uploads/' . $fileName;
                if (move_uploaded_file($_FILES['document']['tmp_name'], $destPath)) {
                    $docPath = $destPath;
                    $docName = $_FILES['document']['name'];
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                INSERT INTO referrals
                  (ref_number, partner_id, client_name, client_email, client_phone,
                   client_suburb, client_state, loan_type, estimated_amount,
                   consent, consent_timestamp, notes, status, document_path, document_name)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $refNumber, $uid, $clientName, $clientEmail, $clientPhone,
                $clientSuburb, $clientState, $loanType, $estAmount,
                $consent, $consentTs, $notes, $status, $docPath, $docName
            ]);
            $refId = lastId($pdo, 'referrals');

            // Save document record
            if ($docPath) {
                $pdo->prepare("INSERT INTO documents (referral_id, user_id, filename, filepath, filesize) VALUES (?,?,?,?,?)")
                    ->execute([$refId, $uid, $docName, $docPath, $_FILES['document']['size']]);
            }

            // Notify all brokers of new referral
            $brokers = $pdo->query("SELECT id FROM users WHERE role='broker' AND status='active'")->fetchAll();
            foreach ($brokers as $b) {
                addNotification($pdo, $b['id'], 'New Referral Received',
                    "Referral $refNumber submitted by {$user['name']} for client $clientName — " . money($estAmount));
            }

            logAction($pdo, $uid, $user['name'], 'referral_submitted', 'referral', $refId,
                "Submitted referral $refNumber for client $clientName");

            $success = "Referral $refNumber submitted successfully! " . ($isDraft ? "Saved as draft." : "It is now under review.");

            // Clear POST data
            $_POST = [];
        }
    }
}

// Get notifications for dropdown
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();

$states    = ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT'];
$loanTypes = ['Owner-Occupied','Investment','Refinance','Commercial','Construction'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Referral — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Referral Form</span>
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
                            <div>
                                <div class="notif-item-title"><?= e($n['title']) ?></div>
                                <div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info">
                <div class="user-name"><?= e($user['name']) ?></div>
                <div class="user-role"><?= e(tierLabel($user['tier'])) ?></div>
            </div>
        </div>

        <div class="page-body">

            <div class="page-heading">
                <div>
                    <h1>Submit New Referral</h1>
                    <p>Refer a new client to the network. Ensure client consent is obtained before submitting.</p>
                </div>
                <a href="referrals.php" class="btn-outline"><i class="bi bi-arrow-left"></i> Back to Referrals</a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <div class="info-banner">
                <i class="bi bi-info-circle"></i>
                Please ensure all client information is accurate to expedite the vetting process.
                Secure handling of data is our priority. (Privacy Act 1988)
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="two-col-main">
                    <!-- Left: Form -->
                    <div>
                        <!-- Client Information -->
                        <div class="card" style="margin-bottom:20px">
                            <div class="form-section-title">Client Information</div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full Name *</label>
                                    <input type="text" name="client_name"
                                           value="<?= e($_POST['client_name'] ?? '') ?>"
                                           placeholder="e.g. Jonathan Harper" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="client_email"
                                           value="<?= e($_POST['client_email'] ?? '') ?>"
                                           placeholder="client@email.com.au">
                                </div>
                            </div>

                            <div class="form-row three">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="client_phone"
                                           value="<?= e($_POST['client_phone'] ?? '') ?>"
                                           placeholder="0400 000 000">
                                </div>
                                <div class="form-group">
                                    <label>Suburb</label>
                                    <input type="text" name="client_suburb"
                                           value="<?= e($_POST['client_suburb'] ?? '') ?>"
                                           placeholder="e.g. Bondi Beach">
                                </div>
                                <div class="form-group">
                                    <label>State</label>
                                    <select name="client_state">
                                        <option value="">— Select —</option>
                                        <?php foreach ($states as $s): ?>
                                            <option value="<?= $s ?>" <?= ($_POST['client_state']??'')===$s?'selected':'' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Referral Details -->
                        <div class="card" style="margin-bottom:20px">
                            <div class="form-section-title">Referral Details</div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Loan Type</label>
                                    <select name="loan_type">
                                        <?php foreach ($loanTypes as $lt): ?>
                                            <option value="<?= $lt ?>" <?= ($_POST['loan_type']??'Owner-Occupied')===$lt?'selected':'' ?>><?= $lt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Estimated Loan Amount (AUD) *</label>
                                    <input type="number" name="estimated_amount" id="estimated_amount"
                                           value="<?= e($_POST['estimated_amount'] ?? '') ?>"
                                           step="1000" min="0" placeholder="e.g. 650000" required>
                                    <input type="hidden" id="tierRate" value="<?= $user['commission_rate'] ?>">
                                </div>
                            </div>

                            <div class="form-row single">
                                <div class="form-group">
                                    <label>Additional Notes (Optional)</label>
                                    <textarea name="notes" placeholder="Provide context about the client's situation, goals, timeline..."><?= e($_POST['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Document Upload -->
                        <div class="card" style="margin-bottom:20px">
                            <div class="form-section-title">Document Upload (Optional)</div>
                            <div class="upload-zone" id="uploadZone">
                                <i class="bi bi-cloud-upload"></i>
                                <p><span>Click to upload</span> or drag and drop</p>
                                <p style="font-size:11px;margin-top:4px">PDF, DOCX, or XLSX (Max 10MB)</p>
                            </div>
                            <input type="file" name="document" id="documentFile" style="display:none" accept=".pdf,.doc,.docx,.xlsx,.xls">
                        </div>

                        <!-- Consent -->
                        <div class="card" style="margin-bottom:20px">
                            <div class="form-section-title">Client Consent (Privacy Act 1988)</div>
                            <div class="consent-box">
                                <input type="checkbox" name="consent" id="consent"
                                       <?= isset($_POST['consent']) ? 'checked' : '' ?>>
                                <label for="consent">
                                    I confirm that the client has been informed about and has expressly consented to their
                                    personal information being submitted to the Referral Network Portal for the purpose of
                                    mortgage/finance referral. The consent timestamp will be recorded for audit purposes.
                                </label>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;gap:12px;justify-content:flex-end">
                            <button type="submit" name="save_draft" value="1" class="btn-secondary">
                                <i class="bi bi-floppy"></i> Save Draft
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="bi bi-send"></i> Submit Referral
                            </button>
                        </div>
                    </div>

                    <!-- Right: Guidelines Panel -->
                    <div>
                        <div class="guidelines-panel">
                            <h3><i class="bi bi-shield-check"></i> Referral Guidelines</h3>
                            <ul>
                                <li><i class="bi bi-check-circle-fill"></i> Ensure the client has expressed verbal consent before submission</li>
                                <li><i class="bi bi-check-circle-fill"></i> Include any known project timelines in the notes section</li>
                                <li><i class="bi bi-check-circle-fill"></i> Standard vetting time is 48 business hours</li>
                                <li><i class="bi bi-check-circle-fill"></i> Upload supporting documents where available</li>
                                <li><i class="bi bi-check-circle-fill"></i> Commission disputes must be raised within 30 days</li>
                            </ul>
                            <div class="commission-box">
                                <div class="label">Your Commission Tier</div>
                                <div class="value"><?= e($user['tier']) ?> — <?= $user['commission_rate'] ?>%</div>
                                <div style="font-size:12px;margin-top:4px;opacity:0.8">
                                    Est. commission: <span id="commissionPreview">—</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
                        $stmt->execute([$uid]); $recent = $stmt->fetchAll();
                        ?>
                        <?php if ($recent): ?>
                        <div class="card">
                            <div class="card-title">Recent Activity</div>
                            <ul class="activity-list">
                                <?php foreach ($recent as $n): ?>
                                    <li class="activity-item">
                                        <div class="activity-dot dot-navy"></div>
                                        <div>
                                            <div class="activity-title"><?= e($n['title']) ?></div>
                                            <div class="activity-time"><?= timeAgo($n['created_at']) ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
