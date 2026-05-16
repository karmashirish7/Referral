<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
requireRole('partner');

$user = currentUser(); $uid = currentUserId();
$unread = unreadNotificationCount($sb, $uid);

$stats = $sb->rpc('partner_dashboard_stats', ['p_uid' => $uid]);
$totalReferrals = (int)($stats['total_referrals'] ?? 0);
$totalSettled   = (int)($stats['total_settled']   ?? 0);
$totalEarned    = (float)($stats['total_earned']  ?? 0);
$pendingPayout  = (float)($stats['pending_payout']?? 0);
$conversionRate = $totalReferrals > 0 ? round(($totalSettled / $totalReferrals) * 100, 1) : 0;

$chart        = $sb->rpc('partner_monthly_chart', ['p_uid' => $uid]);
$months       = $chart['months']   ?? [];
$approvedData = $chart['approved'] ?? [];
$pendingData  = $chart['pending']  ?? [];

$activities = $sb->from('audit_log')->eq('user_id', $uid)->order('created_at', false)->limit(5)->get();

$allReferrals = $sb->rpc('get_referrals_list', ['p_role' => 'partner', 'p_uid' => $uid]);
$allReferrals = is_array($allReferrals) ? $allReferrals : [];
$pipeline     = array_slice($allReferrals, 0, 5);

$allCommissions = $sb->rpc('get_commissions_list', ['p_status' => '']);
$allCommissions = is_array($allCommissions) ? $allCommissions : [];
$payouts = array_slice(array_filter($allCommissions, fn($c) => $c['partner_id'] == $uid), 0, 5);

$notifList = $sb->from('notifications')->eq('user_id', $uid)->order('created_at', false)->limit(8)->get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Network Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-header">
            <span class="header-portal-label">Referral Portal</span>
            <div class="search-bar"><i class="bi bi-search"></i><input type="text" placeholder="Search referrals..."></div>
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
                            <div><div class="notif-item-title"><?= e($n['title']) ?></div><div class="notif-item-msg"><?= e($n['message']) ?></div><div class="notif-item-time"><?= timeAgo($n['created_at']) ?></div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$notifList): ?><div style="padding:20px;text-align:center;color:#718096;font-size:13px">No notifications</div><?php endif; ?>
                </div>
            </div>
            <div class="user-avatar"><?= e($user['avatar']) ?></div>
            <div class="user-info"><div class="user-name"><?= e($user['name']) ?></div><div class="user-role"><?= e(tierLabel($user['tier'])) ?></div></div>
        </div>

        <div class="page-body">
            <div class="page-heading">
                <div><h1>Partner Overview</h1><p>Performance metrics — <?= date('F Y') ?></p></div>
                <div class="heading-actions">
                    <a href="referrals.php" class="btn-outline"><i class="bi bi-download"></i> Download Report</a>
                    <a href="submit-referral.php" class="btn-primary"><i class="bi bi-plus-lg"></i> Submit New Referral</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon blue"><i class="bi bi-arrow-left-right"></i></div><div class="stat-label">Total Referrals</div><div class="stat-value"><?= $totalReferrals ?></div><div class="stat-change up"><i class="bi bi-arrow-up"></i> All time</div></div>
                <div class="stat-card"><div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div><div class="stat-label">Pending Payouts</div><div class="stat-value"><?= money($pendingPayout) ?></div><div class="stat-change"><span style="color:#718096">Awaiting settlement</span></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="bi bi-check-circle"></i></div><div class="stat-label">Commissions Earned</div><div class="stat-value"><?= money($totalEarned) ?></div><div class="stat-change up"><i class="bi bi-arrow-up"></i> Total paid</div></div>
                <div class="stat-card"><div class="stat-icon navy"><i class="bi bi-percent"></i></div><div class="stat-label">Conversion Rate</div><div class="stat-value"><?= $conversionRate ?>%</div><div class="stat-change <?= $conversionRate > 50 ? 'up' : '' ?>"><?= $totalSettled ?> settled of <?= $totalReferrals ?></div></div>
            </div>

            <div class="two-col" style="margin-bottom:24px">
                <div class="card">
                    <div class="card-title">Monthly Referral Volume</div>
                    <canvas id="referralChart" height="130"></canvas>
                </div>
                <div class="card">
                    <div class="card-title">Recent Activity <a href="audit.php" style="font-size:12px;color:#1F3864;text-decoration:none;font-weight:400">View All</a></div>
                    <ul class="activity-list">
                        <?php $dotColors = ['referral_submitted'=>'dot-navy','status_changed'=>'dot-blue','commission_paid'=>'dot-green','login'=>'dot-orange'];
                        foreach ($activities as $act): $dot = $dotColors[$act['action']] ?? 'dot-navy'; ?>
                            <li class="activity-item">
                                <div class="activity-dot <?= $dot ?>"></div>
                                <div>
                                    <div class="activity-title"><?= e(ucwords(str_replace('_',' ',$act['action']))) ?></div>
                                    <div class="activity-desc"><?= e($act['description']) ?></div>
                                    <div class="activity-time"><?= timeAgo($act['created_at']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!$activities): ?><li class="activity-item"><div class="activity-desc">No activity yet.</div></li><?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header"><h2>Referral Pipeline</h2><div class="heading-actions"><a href="referrals.php" class="btn-outline btn-sm">View All</a><a href="submit-referral.php" class="btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Referral</a></div></div>
                <table>
                    <thead><tr><th>Ref #</th><th>Client Name</th><th>Loan Type</th><th>Date Submitted</th><th>Est. Value</th><th>Commission</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($pipeline as $r): ?>
                            <tr>
                                <td><span class="ref-number"><?= e($r['ref_number']) ?></span></td>
                                <td><span class="client-name"><?= e($r['client_name']) ?></span></td>
                                <td><?= e($r['loan_type']) ?></td>
                                <td><?= date('d M Y', strtotime($r['date_submitted'])) ?></td>
                                <td><?= money($r['estimated_amount']) ?></td>
                                <td><?= ($r['commission_amount'] ?? 0) > 0 ? money($r['commission_amount']) : '<span style="color:#718096">—</span>' ?></td>
                                <td><?= statusBadge($r['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$pipeline): ?><tr><td colspan="7" style="text-align:center;padding:32px;color:#718096">No referrals yet. <a href="submit-referral.php">Submit your first referral</a></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($payouts): ?>
            <div class="table-card" style="margin-top:24px">
                <div class="table-header"><h2>Recent Payouts</h2></div>
                <table>
                    <thead><tr><th>Referral</th><th>Client</th><th>Broker Upfront</th><th>Your Rate</th><th>Commission</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($payouts as $p): ?>
                            <tr>
                                <td><span class="ref-number"><?= e($p['ref_number']) ?></span></td>
                                <td><?= e($p['client_name']) ?></td>
                                <td><?= money($p['broker_upfront']) ?></td>
                                <td><?= $p['rate'] ?>%</td>
                                <td><strong><?= money($p['override_amount'] ?? $p['amount']) ?></strong></td>
                                <td><?= commissionBadge($p['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
new Chart(document.getElementById('referralChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [
            { label: 'Approved', data: <?= json_encode($approvedData) ?>, backgroundColor: '#1F3864', borderRadius: 4 },
            { label: 'Pending',  data: <?= json_encode($pendingData)  ?>, backgroundColor: '#dbeafe', borderRadius: 4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } } } }
});
</script>
</body>
</html>
