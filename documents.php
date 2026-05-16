<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireLogin();

$user = currentUser(); $uid = currentUserId(); $role = currentRole();
$unread = unreadNotificationCount($sb, $uid);

$docs = $sb->rpc('get_documents', ['p_role' => $role, 'p_uid' => $uid]);
$docs = is_array($docs) ? $docs : [];

$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['document']['name'])) {
    $rid     = (int)($_POST['referral_id'] ?? 0);
    $allowed = ['pdf','docx','doc','xlsx','xls'];
    $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
    if ($rid && in_array($ext, $allowed) && $_FILES['document']['size'] <= 10*1024*1024) {
        $fname = 'REF-' . $rid . '-' . time() . '.' . $ext;
        $dest  = 'uploads/' . $fname;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $dest)) {
            $sb->from('documents')->insert([
                'referral_id' => $rid, 'user_id' => $uid,
                'filename' => $_FILES['document']['name'], 'filepath' => $dest,
                'filesize' => $_FILES['document']['size'],
            ]);
            logAction($sb, $uid, $user['name'], 'document_uploaded', 'document', $rid, "Uploaded {$_FILES['document']['name']} for referral #{$rid}");
            header("Location: documents.php?uploaded=1"); exit;
        }
    } else {
        $uploadMsg = 'Upload failed. Check file type (PDF/DOCX/XLSX) and size (max 10MB).';
    }
}

$myReferrals = [];
if ($role === 'partner') {
    $myReferrals = $sb->from('referrals')->eq('partner_id', $uid)->select('id,ref_number,client_name')->order('date_submitted', false)->get();
}
$notifList = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                            <div><div class="notif-item-title"><?= e($n['title']) ?></div><div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role"><?= ucfirst($role) ?></div></div>
        </div>
        <div class="page-body">
            <div class="page-heading">
                <div><h1>Documents</h1><p>Referral documents and attachments.</p></div>
                <?php if ($role === 'partner' && $myReferrals): ?>
                    <button class="btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'"><i class="bi bi-upload"></i> Upload Document</button>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['uploaded'])): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> Document uploaded successfully.</div><?php endif; ?>
            <?php if ($uploadMsg): ?><div class="alert alert-error"><?= htmlspecialchars($uploadMsg) ?></div><?php endif; ?>

            <div class="table-card">
                <table>
                    <thead><tr><th>File</th><th>Referral</th><th>Client</th><?= $role !== 'partner' ? '<th>Uploaded By</th>' : '' ?><th>Size</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($docs as $d): ?>
                            <tr>
                                <td><i class="bi bi-file-earmark-text" style="margin-right:6px;color:#1F3864"></i><?= e($d['filename']) ?></td>
                                <td><span class="ref-number"><?= e($d['ref_number']) ?></span></td>
                                <td><?= e($d['client_name']) ?></td>
                                <?php if ($role !== 'partner'): ?><td><?= e($d['uploader'] ?? '—') ?></td><?php endif; ?>
                                <td><?= round(($d['filesize'] ?? 0) / 1024) ?> KB</td>
                                <td><?= date('d M Y', strtotime($d['uploaded_at'])) ?></td>
                                <td><a href="<?= e($d['filepath']) ?>" class="btn-outline btn-sm" download><i class="bi bi-download"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$docs): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-folder"></i> No documents yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'partner' && $myReferrals): ?>
<div class="modal-overlay" id="uploadModal" style="display:none">
    <div class="modal-box">
        <h2>Upload Document</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom:14px">
                <label>Referral</label>
                <select name="referral_id" required>
                    <option value="">Select referral…</option>
                    <?php foreach ($myReferrals as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['ref_number']) ?> — <?= e($r['client_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label>File (PDF, DOCX, XLSX — max 10MB)</label>
                <input type="file" name="document" accept=".pdf,.docx,.doc,.xlsx,.xls" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('uploadModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="assets/js/main.js"></script>
</body>
</html>
