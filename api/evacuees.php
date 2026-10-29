<?php
// api/evacuees.php
//
// GET    ?search=xxx          → list all evacuees (admin), optional search
// DELETE ?user_id=123         → soft-delete evacuee
// POST   { admin_id, first_name, last_name, email, address, phone_number }
//                             → admin registers a new evacuee

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Helper: try to fetch profile_pic_url for a user — null if column missing
function getProfilePic(int $userId): ?string {
    try {
        $s = db()->prepare('SELECT profile_pic_url FROM users WHERE user_id = :id LIMIT 1');
        $s->execute([':id' => $userId]);
        $r = $s->fetch();
        return $r['profile_pic_url'] ?? null;
    } catch (Throwable $e) {
        return null;
    }
}

// ── GET – list evacuees ───────────────────────
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmt = db()->prepare(
            'SELECT u.user_id, u.first_name, u.last_name, u.email,
                    u.address, u.contact_number AS phone_number,
                    COALESCE(q.qr_code_data,
                             CONCAT("evacumate://user/", u.user_id)) AS qr_code
             FROM   users u
             LEFT   JOIN qr_codes q ON q.user_id = u.user_id AND q.is_active = 1
             WHERE  u.role = "user"
               AND  u.deleted_at IS NULL
               AND  (u.first_name  LIKE :s
                  OR u.last_name   LIKE :s
                  OR u.email       LIKE :s)
             ORDER  BY u.first_name, u.last_name'
        );
        $stmt->execute([':s' => $like]);
    } else {
        $stmt = db()->prepare(
            'SELECT u.user_id, u.first_name, u.last_name, u.email,
                    u.address, u.contact_number AS phone_number,
                    COALESCE(q.qr_code_data,
                             CONCAT("evacumate://user/", u.user_id)) AS qr_code
             FROM   users u
             LEFT   JOIN qr_codes q ON q.user_id = u.user_id AND q.is_active = 1
             WHERE  u.role = "user"
               AND  u.deleted_at IS NULL
             ORDER  BY u.first_name, u.last_name'
        );
        $stmt->execute();
    }

    $rows = array_map(function ($r) {
        return [
            'id'              => (string) $r['user_id'],
            'first_name'      => $r['first_name'],
            'last_name'       => $r['last_name'],
            'email'           => $r['email'],
            'address'         => $r['address'],
            'phone_number'    => $r['phone_number'],
            'qr_code'         => $r['qr_code'],
            'profile_pic_url' => getProfilePic((int) $r['user_id']),
        ];
    }, $stmt->fetchAll());

    respond(['success' => true, 'evacuees' => $rows]);
}

// ── DELETE – soft-delete evacuee ─────────────
if ($method === 'DELETE') {
    $userId = trim($_GET['user_id'] ?? '');
    if (!$userId) respond(['success' => false, 'error' => 'user_id required'], 400);

    db()->prepare('UPDATE users SET deleted_at = NOW() WHERE user_id = :id')
        ->execute([':id' => $userId]);

    respond(['success' => true]);
}

// ── POST – admin registers evacuee ───────────
if ($method === 'POST') {
    $body      = bodyJson();
    $adminId   = trim($body['admin_id']     ?? '');
    $firstName = trim($body['first_name']   ?? '');
    $lastName  = trim($body['last_name']    ?? '');
    $email     = trim($body['email']        ?? '');
    $address   = trim($body['address']      ?? '');
    $phone     = trim($body['phone_number'] ?? '');

    if (!$adminId || !$firstName || !$lastName || !$email) {
        respond(['success' => false, 'error' => 'Missing required fields'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'error' => 'Invalid email address'], 400);
    }

    $chk = db()->prepare('SELECT user_id FROM users WHERE email = :em LIMIT 1');
    $chk->execute([':em' => $email]);
    if ($chk->fetch()) {
        respond(['success' => false, 'error' => 'Email already registered'], 409);
    }

    $tmpPw = bin2hex(random_bytes(8));
    $hash  = password_hash($tmpPw, PASSWORD_BCRYPT);

    $ins = db()->prepare(
        'INSERT INTO users
           (first_name, last_name, email, password_hash,
            contact_number, address, role, registered_by)
         VALUES (:fn, :ln, :em, :pw, :ph, :ad, "user", :reg)'
    );
    $ins->execute([
        ':fn'  => $firstName,
        ':ln'  => $lastName,
        ':em'  => $email,
        ':pw'  => $hash,
        ':ph'  => $phone,
        ':ad'  => $address,
        ':reg' => $adminId,
    ]);
    $newUserId = db()->lastInsertId();

    $qrData = 'evacumate://user/' . $newUserId;
    db()->prepare('INSERT INTO qr_codes (user_id, qr_code_data) VALUES (:uid, :qr)')
        ->execute([':uid' => $newUserId, ':qr' => $qrData]);

    db()->prepare(
        'INSERT INTO admin_actions_log
           (admin_id, action_type, action_description, target_user_id)
         VALUES (:admin, "register", :desc, :target)'
    )->execute([
        ':admin'  => $adminId,
        ':desc'   => "Admin registered evacuee: $firstName $lastName ($email)",
        ':target' => $newUserId,
    ]);

    respond([
        'success'       => true,
        'user_id'       => (string) $newUserId,
        'temp_password' => $tmpPw,
    ]);
}

respond(['success' => false, 'error' => 'Method not allowed'], 405);