<?php
// api/dashboard_stats.php
// GET → returns { total_evacuees, marked_safe_today,
//                 relief_distributed, pending_verification }

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(['success' => false, 'error' => 'Method not allowed'], 405);
}

$pdo = db();

// Total active evacuees (role = 'user', not deleted)
$total = (int) $pdo->query(
    'SELECT COUNT(*) FROM users WHERE role = "user" AND deleted_at IS NULL'
)->fetchColumn();

// Marked safe today (distinct users with a "Marked Safe" scan today)
$safeToday = (int) $pdo->query(
    'SELECT COUNT(DISTINCT user_id) FROM scans
     WHERE scan_type = "Marked Safe"
       AND DATE(scanned_at) = CURDATE()'
)->fetchColumn();

// Relief distributed (total "Received Relief Goods" scan records)
$relief = (int) $pdo->query(
    'SELECT COUNT(*) FROM scans WHERE scan_type = "Received Relief Goods"'
)->fetchColumn();

// Pending verification = users not yet marked safe at all
$pending = (int) $pdo->query(
    'SELECT COUNT(*) FROM users
     WHERE role = "user"
       AND deleted_at IS NULL
       AND is_marked_safe = 0'
)->fetchColumn();

respond([
    'success'              => true,
    'total_evacuees'       => $total,
    'marked_safe_today'    => $safeToday,
    'relief_distributed'   => $relief,
    'pending_verification' => $pending,
]);
