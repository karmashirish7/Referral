<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user   = currentUser();
$uid    = currentUserId();
$role   = currentRole();
$unread = unreadNotificationCount($pdo, $uid);

// Partners see their own docs; brokers/admins see all
if ($role === 'partner') {
    $docs = $pdo->prepare("SELECT d.*, r.ref_number, r.client_name FROM documents d JOIN referrals r ON d.referral_id = r.id WHERE d.user_id = ? ORDER BY d.uploaded_at DESC");
    $docs->execute([$uid]);
} else {
    $docs = $pdo->query("SELECT d.*, r.ref_number, r.client_name, u.name AS uploader FROM documents d JOIN referrals r ON d.referral_id = r.id JOIN users u ON d.user_id = u.id ORDER BY d.uploaded_at DESC");
}
$docs = $docs->fetchAll();

// Handle upload from this page (attach to a referral)
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['document']['name'])) {
    $rid     = (int)($_POST['referral_id'] ?? 0);
    $allowed = ['pdf','docx','doc','xlsx','xls'];
    $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    if ($rid && in_array($ext, $allowed) && $_FILES['document']['size'] <= 10*1024*1024) {
        $fname = 'REF-' . $rid . '-' . time() . '.' . $ext;
        $dest  = 'uploads/' . $fname;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $dest)) {
            $pdo->prepare("INSERT INTO documents (referral_id, user_id, filename, filepath, filesize) VALUES (?,?,?,?,?)")
                ->execute([$rid, $uid, $_FILES['document']['name'], $dest, $_FILES['document']['size']]);
            logAction($pdo, $uid, $user['name'], 'document_uploaded', 'document', $rid, "Uploaded {$_FILES['document']['name']} for referral #$rid");
            $uploadMsg = 'Document uploaded successfully.';
            header("Location: documents.php?uploaded=1"); exit;
        }
    } else {
        $uploadMsg = 'Upload failed. Check file type (PDF/DOCX/XLSX) and size (max 10MB).';
    }
}

// Partner's referrals for the upload dropdown
$myReferrals = [];
if ($role === 'partner') {
    $stmt = $pdo->prepare("SELECT id, ref_number, client_name FROM referrals WHERE partner_id = ? ORDER BY date_submitted DESC");
    $stmt->execute([$uid]);
    $myReferrals = $stmt->fetchAll();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
$stmt->execute([$uid]); $notifList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Documents</span>
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
            <?php if (isset($_GET['uploaded'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> Document uploaded successfully.</div>
            <?php endif; ?>
            <?php if ($uploadMsg): ?><div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= e($uploadMsg) ?></div><?php endif; ?>

            <div class="page-heading">
                <div><h1>Documents</h1><p>Uploaded files attached to referrals.</p></div>
                <?php if ($role === 'partner' && $myReferrals): ?>
                    <button class="btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'">
                        <i class="bi bi-upload"></i> Upload Document
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Referral</th>
                            <th>Client</th>
                            <?php if ($role !== 'partner'): ?><th>Uploaded By</th><?php endif; ?>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docs as $d): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <i class="bi bi-file-earmark-pdf" style="color:#dc2626;font-size:18px"></i>
                                        <span style="font-size:13px"><?= e($d['filename']) ?></span>
                                    </div>
                                </td>
                                <td><span class="ref-number"><?= e($d['ref_number']) ?></span></td>
                                <td><?= e($d['client_name']) ?></td>
                                <?php if ($role !== 'partner'): ?><td><?= e($d['uploader'] ?? '—') ?></td><?php endif; ?>
                                <td style="font-size:12px;color:#718096"><?= $d['filesize'] ? number_format($d['filesize']/1024,1) . ' KB' : '—' ?></td>
                                <td style="font-size:12px"><?= date('d M Y', strtotime($d['uploaded_at'])) ?></td>
                                <td>
                                    <?php if (file_exists($d['filepath'])): ?>
                                        <a href="<?= e($d['filepath']) ?>" download class="btn-outline btn-sm">
                                            <i class="bi bi-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#718096;font-size:12px">File not found</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$docs): ?>
                            <tr><td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-folder2-open"></i>
                                    No documents yet. Upload files when submitting a referral.
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'partner' && $myReferrals): ?>
<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal" style="display:none">
    <div class="modal-box">
        <h2>Upload Document</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom:14px">
                <label>Attach to Referral</label>
                <select name="referral_id" required>
                    <option value="">— Select Referral —</option>
                    <?php foreach ($myReferrals as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= e($r['ref_number']) ?> — <?= e($r['client_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>File (PDF, DOCX, XLSX — max 10MB)</label>
                <input type="file" name="document" accept=".pdf,.doc,.docx,.xlsx,.xls" required style="border:1px solid #e2e8f0;border-radius:6px;padding:8px">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('uploadModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary"><i class="bi bi-upload"></i> Upload</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="assets/js/main.js"></script>
</body>
</html>
