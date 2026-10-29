<?php
// api/login.php
// POST { "email": "...", "password": "..." }
// Returns { "success": true/false, "role": "user"|"admin",
//           "user_id": "123", "is_superuser": false, "error": "..." }

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body     = bodyJson();
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if ($email === '' || $password === '') {
    respond(['success' => false, 'error' => 'Email and password are required'], 400);
}

// Fetch without is_superuser first — column may not exist yet
$stmt = db()->prepare(
    'SELECT user_id, role, password_hash
     FROM   users
     WHERE  email = :email
       AND  deleted_at IS NULL
     LIMIT  1'
);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    respond(['success' => false, 'error' => 'Invalid email or password'], 401);
}

// Try to read is_superuser — gracefully fall back to false if column missing
$isSuperuser = false;
try {
    $s2 = db()->prepare(
        'SELECT is_superuser FROM users WHERE user_id = :id LIMIT 1'
    );
    $s2->execute([':id' => $user['user_id']]);
    $row2 = $s2->fetch();
    $isSuperuser = (bool) ($row2['is_superuser'] ?? false);
} catch (Throwable $e) {
    // Column doesn't exist yet — that's fine, default false
    $isSuperuser = false;
}

respond([
    'success'      => true,
    'role'         => $user['role'],
    'user_id'      => (string) $user['user_id'],
    'is_superuser' => $isSuperuser,
]);