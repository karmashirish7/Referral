<?php
// ============================================================
// Helper Functions
// ============================================================

// Generate a unique referral number: REF-YYYY-NNNN
function generateRefNumber($pdo) {
    $year = date('Y');
    // PostgreSQL uses EXTRACT instead of YEAR()
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE EXTRACT(YEAR FROM date_submitted) = ?");
    $stmt->execute([$year]);
    $count = (int)$stmt->fetchColumn() + 1;
    return 'REF-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// PostgreSQL lastInsertId requires the sequence name
function lastId($pdo, $table) {
    return $pdo->lastInsertId($table . '_id_seq');
}

// Calculate partner commission based on tier rate
// Formula: broker_upfront_commission × (tier_rate / 100)
function calculateCommission($brokerUpfront, $tierRate) {
    return round($brokerUpfront * ($tierRate / 100), 2);
}

// Write an entry to the audit log
function logAction($pdo, $userId, $userName, $action, $entityType, $entityId, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, user_name, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $userName, $action, $entityType, $entityId, $description, $ip]);
}

// Create an in-app notification for a user
function addNotification($pdo, $userId, $title, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $title, $message]);
}

// Return count of unread notifications for a user
function unreadNotificationCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Format a dollar amount
function money($amount) {
    return '$' . number_format($amount, 2);
}

// Return a Bootstrap badge class for a referral status
function statusBadge($status) {
    $map = [
        'pending'   => 'badge-pending',
        'qualified' => 'badge-qualified',
        'lodged'    => 'badge-lodged',
        'approved'  => 'badge-approved',
        'settled'   => 'badge-settled',
        'declined'  => 'badge-declined',
    ];
    $label = ucfirst($status);
    $cls   = $map[$status] ?? 'badge-pending';
    return "<span class=\"status-badge $cls\">$label</span>";
}

// Return a badge for commission status
function commissionBadge($status) {
    $cls = $status === 'paid' ? 'badge-settled' : 'badge-pending';
    return "<span class=\"status-badge $cls\">" . ucfirst($status) . "</span>";
}

// Return a badge for user status
function userStatusBadge($status) {
    $map = ['active' => 'badge-settled', 'pending' => 'badge-pending', 'suspended' => 'badge-declined'];
    $cls = $map[$status] ?? 'badge-pending';
    return "<span class=\"status-badge $cls\">" . ucfirst($status) . "</span>";
}

// Format time ago (e.g. "2 hours ago")
function timeAgo($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days > 30)  return $then->format('d M Y');
    if ($diff->days > 0)   return $diff->days . ' day'  . ($diff->days  > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)      return $diff->h    . ' hour' . ($diff->h     > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)      return $diff->i    . ' min'  . ($diff->i     > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// Sanitise user output to prevent XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Get tier commission rate label
function tierLabel($tier) {
    $rates = ['Gold' => '25%', 'Silver' => '20%', 'Bronze' => '15%'];
    return $tier . ' (' . ($rates[$tier] ?? '-') . ')';
}
