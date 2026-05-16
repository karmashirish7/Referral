<?php

function generateRefNumber(Supabase $sb): string {
    $year  = date('Y');
    $count = $sb->from('referrals')
                ->ilike('ref_number', "REF-{$year}-%")
                ->count();
    return 'REF-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function logAction(Supabase $sb, $userId, string $userName, string $action, string $entityType, $entityId, string $description): void {
    $sb->from('audit_log')->insert([
        'user_id'     => $userId,
        'user_name'   => $userName,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'description' => $description,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ]);
}

function addNotification(Supabase $sb, $userId, string $title, string $message): void {
    $sb->from('notifications')->insert([
        'user_id' => $userId,
        'title'   => $title,
        'message' => $message,
    ]);
}

function unreadNotificationCount(Supabase $sb, $userId): int {
    return $sb->from('notifications')->eq('user_id', $userId)->eq('is_read', 0)->count();
}

function calculateCommission(float $brokerUpfront, float $tierRate): float {
    return round($brokerUpfront * ($tierRate / 100), 2);
}

function money(float $amount): string {
    return '$' . number_format($amount, 2);
}

function statusBadge(string $status): string {
    $map = [
        'pending'   => 'badge-pending',
        'qualified' => 'badge-qualified',
        'lodged'    => 'badge-lodged',
        'approved'  => 'badge-approved',
        'settled'   => 'badge-settled',
        'declined'  => 'badge-declined',
    ];
    $cls = $map[$status] ?? 'badge-pending';
    return "<span class=\"status-badge $cls\">" . ucfirst($status) . "</span>";
}

function commissionBadge(string $status): string {
    $cls = $status === 'paid' ? 'badge-settled' : 'badge-pending';
    return "<span class=\"status-badge $cls\">" . ucfirst($status) . "</span>";
}

function userStatusBadge(string $status): string {
    $map = ['active' => 'badge-settled', 'pending' => 'badge-pending', 'suspended' => 'badge-declined'];
    $cls = $map[$status] ?? 'badge-pending';
    return "<span class=\"status-badge $cls\">" . ucfirst($status) . "</span>";
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days > 30) return $then->format('d M Y');
    if ($diff->days > 0)  return $diff->days . ' day'  . ($diff->days > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)     return $diff->h    . ' hour' . ($diff->h    > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)     return $diff->i    . ' min'  . ($diff->i    > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function tierLabel(string $tier): string {
    $rates = ['Gold' => '25%', 'Silver' => '20%', 'Bronze' => '15%'];
    return $tier . ' (' . ($rates[$tier] ?? '-') . ')';
}
