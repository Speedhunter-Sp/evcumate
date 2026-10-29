<?php
// api/user_profile.php
// GET  ?user_id=123          → fetch profile
// PUT  { user_id, first_name, last_name, email, address, phone_number } → update

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET profile ───────────────────────────────
if ($method === 'GET') {
    $userId = trim($_GET['user_id'] ?? '');
    if ($userId === '') respond(['success' => false, 'error' => 'user_id required'], 400);

    // Base query — works even before migration (no new columns)
    $stmt = db()->prepare(
        'SELECT u.user_id        AS id,
                u.first_name,
                u.last_name,
                u.email,
                u.address,
                u.contact_number AS phone_number,
                u.is_marked_safe,
                COALESCE(q.qr_code_data, CONCAT("evacumate://user/", u.user_id)) AS qr_code
         FROM   users u
         LEFT   JOIN qr_codes q ON q.user_id = u.user_id AND q.is_active = 1
         WHERE  u.user_id = :id
           AND  u.deleted_at IS NULL
         LIMIT  1'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();

    if (!$row) respond(['success' => false, 'error' => 'User not found'], 404);

    // Try to read profile_pic_url — gracefully null if column missing
    $profilePicUrl = null;
    try {
        $s2 = db()->prepare(
            'SELECT profile_pic_url FROM users WHERE user_id = :id LIMIT 1'
        );
        $s2->execute([':id' => $userId]);
        $r2 = $s2->fetch();
        $profilePicUrl = $r2['profile_pic_url'] ?? null;
    } catch (Throwable $e) {
        // Column doesn't exist yet — fine, just leave null
        $profilePicUrl = null;
    }

    respond([
        'success'         => true,
        'id'              => (string) $row['id'],
        'first_name'      => $row['first_name'],
        'last_name'       => $row['last_name'],
        'email'           => $row['email'],
        'address'         => $row['address'],
        'phone_number'    => $row['phone_number'],
        'qr_code'         => $row['qr_code'],
        'is_marked_safe'  => (bool) $row['is_marked_safe'],
        'profile_pic_url' => $profilePicUrl,
    ]);
}

// ── PUT  update profile ───────────────────────
if ($method === 'PUT') {
    $body      = bodyJson();
    $userId    = trim($body['user_id']      ?? '');
    $firstName = trim($body['first_name']   ?? '');
    $lastName  = trim($body['last_name']    ?? '');
    $email     = trim($body['email']        ?? '');
    $address   = trim($body['address']      ?? '');
    $phone     = trim($body['phone_number'] ?? '');

    if (!$userId) respond(['success' => false, 'error' => 'user_id required'], 400);

    $stmt = db()->prepare(
        'UPDATE users
         SET    first_name     = :fn,
                last_name      = :ln,
                email          = :em,
                address        = :ad,
                contact_number = :ph
         WHERE  user_id = :id
           AND  deleted_at IS NULL'
    );
    $stmt->execute([
        ':fn' => $firstName,
        ':ln' => $lastName,
        ':em' => $email,
        ':ad' => $address,
        ':ph' => $phone,
        ':id' => $userId,
    ]);

    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Method not allowed'], 405);