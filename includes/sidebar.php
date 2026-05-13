<?php
// Role-aware sidebar. Included on every authenticated page.
$page    = basename($_SERVER['PHP_SELF']);
$role    = currentRole();
$user    = currentUser();
$unread  = isset($pdo) ? unreadNotificationCount($pdo, currentUserId()) : 0;

function navItem($href, $icon, $label, $activePage, $currentPage) {
    $active = ($currentPage === $activePage || $currentPage === $href) ? 'active' : '';
    return "<a href=\"$href\" class=\"nav-item $active\">
                <i class=\"bi bi-$icon\"></i> $label
            </a>";
}
?>
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
        <div>
            <div class="logo-title">Network Portal</div>
            <div class="logo-sub">Enterprise Admin</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'partner'): ?>
            <div class="sidebar-section-label">Overview</div>
            <?= navItem('dashboard.php',       'speedometer2',     'Dashboard',       'dashboard.php',       $page) ?>
            <div class="sidebar-section-label">Referrals</div>
            <?= navItem('referrals.php',       'arrow-left-right', 'My Referrals',    'referrals.php',       $page) ?>
            <?= navItem('submit-referral.php', 'plus-circle',      'Submit Referral', 'submit-referral.php', $page) ?>
            <div class="sidebar-section-label">Other</div>
            <?= navItem('documents.php',       'folder2',          'Documents',       'documents.php',       $page) ?>
            <?= navItem('settings.php',        'gear',             'Settings',        'settings.php',        $page) ?>

        <?php elseif ($role === 'broker'): ?>
            <div class="sidebar-section-label">Pipeline</div>
            <?= navItem('pipeline.php',  'kanban',           'Referral Pipeline', 'pipeline.php',  $page) ?>
            <?= navItem('referrals.php', 'arrow-left-right', 'All Referrals',     'referrals.php', $page) ?>
            <div class="sidebar-section-label">Other</div>
            <?= navItem('documents.php', 'folder2',          'Documents',    'documents.php', $page) ?>
            <?= navItem('settings.php',  'gear',             'Settings',     'settings.php',  $page) ?>

        <?php elseif ($role === 'admin'): ?>
            <div class="sidebar-section-label">Management</div>
            <?= navItem('admin-users.php',     'people',           'Users',           'admin-users.php',     $page) ?>
            <?= navItem('referrals.php',       'arrow-left-right', 'All Referrals',   'referrals.php',       $page) ?>
            <?= navItem('admin-commissions.php','cash-stack',      'Commissions',     'admin-commissions.php',$page) ?>
            <div class="sidebar-section-label">Compliance</div>
            <?= navItem('audit.php',     'shield-check',     'Audit Log',    'audit.php',     $page) ?>
            <div class="sidebar-section-label">Other</div>
            <?= navItem('settings.php',  'gear',             'Settings',     'settings.php',  $page) ?>

        <?php elseif ($role === 'auditor'): ?>
            <div class="sidebar-section-label">Compliance</div>
            <?= navItem('audit.php',     'shield-check',     'Audit Log',    'audit.php',     $page) ?>
            <?= navItem('referrals.php', 'arrow-left-right', 'Referrals',    'referrals.php', $page) ?>
            <div class="sidebar-section-label">Other</div>
            <?= navItem('settings.php',  'gear',             'Settings',     'settings.php',  $page) ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-bottom">
        <a href="#" class="nav-item"><i class="bi bi-headset"></i> Support</a>
        <a href="logout.php" class="nav-item"><i class="bi bi-box-arrow-left"></i> Sign Out</a>
    </div>
</div>
