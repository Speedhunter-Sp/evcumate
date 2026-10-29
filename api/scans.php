<?php
// api/scans.php
//
// GET  ?user_id=123               → scans for one user
// GET  ?filter=Marked+Safe        → all scans, optional type filter (admin)
// GET  (no params)                → all scans (admin)
// POST { user_id, scan_type, location, member_count, admin_id } → record scan

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── Helpers ───────────────────────────────────
function rowToScan(array $r): array {
    return [
        'id'            => (string) $r['scan_id'],
        'user_id'       => (string) $r['user_id'],
        'user_full_name'=> $r['user_full_name'],
        'scan_type'     => $r['scan_type'],
        'scanned_at'    => $r['scanned_at'],
        'location'      => $r['location'],
        'member_count'  => (int) $r['member_count'],
    ];
}

// ── GET ───────────────────────────────────────
if ($method === 'GET') {
    $userId     = trim($_GET['user_id'] ?? '');
    $filterType = trim($_GET['filter']  ?? '');

    if ($userId !== '') {
        // ── User's own scan history ───────────
        $stmt = db()->prepare(
            'SELECT s.scan_id, s.user_id,
                    CONCAT(u.first_name," ",u.last_name) AS user_full_name,
                    s.scan_type, s.scanned_at, s.location, s.member_count
             FROM   scans s
             JOIN   users u ON u.user_id = s.user_id
             WHERE  s.user_id = :uid
             ORDER  BY s.scanned_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
    } elseif ($filterType !== '' && $filterType !== 'All') {
        // ── Admin: filtered scans ─────────────
        $stmt = db()->prepare(
            'SELECT s.scan_id, s.user_id,
                    CONCAT(u.first_name," ",u.last_name) AS user_full_name,
                    s.scan_type, s.scanned_at, s.location, s.member_count
             FROM   scans s
             JOIN   users u ON u.user_id = s.user_id
             WHERE  s.scan_type = :type
             ORDER  BY s.scanned_at DESC'
        );
        $stmt->execute([':type' => $filterType]);
    } else {
        // ── Admin: all scans ──────────────────
        $stmt = db()->prepare(
            'SELECT s.scan_id, s.user_id,
                    CONCAT(u.first_name," ",u.last_name) AS user_full_name,
                    s.scan_type, s.scanned_at, s.location, s.member_count
             FROM   scans s
             JOIN   users u ON u.user_id = s.user_id
             ORDER  BY s.scanned_at DESC'
        );
        $stmt->execute();
    }

    $rows = array_map('rowToScan', $stmt->fetchAll());
    respond(['success' => true, 'scans' => $rows]);
}

// ── POST – record a new scan ──────────────────
if ($method === 'POST') {
    $body        = bodyJson();
    $userId      = trim($body['user_id']      ?? '');
    $scanType    = trim($body['scan_type']    ?? '');
    $location    = trim($body['location']     ?? '');
    $memberCount = (int) ($body['member_count'] ?? 1);
    $adminId     = trim($body['admin_id']     ?? '');

    if (!$userId || !$scanType || !$location || !$adminId) {
        respond(['success' => false, 'error' => 'Missing required fields'], 400);
    }
    if (!in_array($scanType, ['Marked Safe', 'Received Relief Goods'], true)) {
        respond(['success' => false, 'error' => 'Invalid scan_type'], 400);
    }

    $stmt = db()->prepare(
        'INSERT INTO scans
           (user_id, scan_type, location, member_count, recorded_by)
         VALUES (:uid, :type, :loc, :mc, :admin)'
    );
    $stmt->execute([
        ':uid'   => $userId,
        ':type'  => $scanType,
        ':loc'   => $location,
        ':mc'    => $memberCount,
        ':admin' => $adminId,
    ]);

    // If marking safe → update user flag
    if ($scanType === 'Marked Safe') {
        db()->prepare('UPDATE users SET is_marked_safe = 1 WHERE user_id = :id')
            ->execute([':id' => $userId]);
    }

    // Log admin action
    $log = db()->prepare(
        'INSERT INTO admin_actions_log
           (admin_id, action_type, action_description, target_user_id)
         VALUES (:admin, "scan", :desc, :target)'
    );
    $log->execute([
        ':admin'  => $adminId,
        ':desc'   => "Recorded scan: $scanType for user $userId",
        ':target' => $userId,
    ]);

    respond(['success' => true, 'scan_id' => (string) db()->lastInsertId()]);
}

respond(['success' => false, 'error' => 'Method not allowed'], 405);
