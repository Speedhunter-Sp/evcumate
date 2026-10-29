<?php
// api/register.php
// POST { "first_name","last_name","email","date_of_birth","password" }
// Returns { "success": true/false, "user_id": "...", "error": "..." }

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body       = bodyJson();
$firstName  = trim($body['first_name']   ?? '');
$lastName   = trim($body['last_name']    ?? '');
$email      = trim($body['email']        ?? '');
$dob        = trim($body['date_of_birth'] ?? '');
$password   = $body['password']          ?? '';

// ── Validate ─────────────────────────────────
if (!$firstName || !$lastName || !$email || !$password) {
    respond(['success' => false, 'error' => 'All fields are required'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'error' => 'Invalid email address'], 400);
}
if (strlen($password) < 6) {
    respond(['success' => false, 'error' => 'Password must be at least 6 characters'], 400);
}

// ── Check duplicate email ─────────────────────
$check = db()->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
$check->execute([':email' => $email]);
if ($check->fetch()) {
    respond(['success' => false, 'error' => 'Email is already registered'], 409);
}

// ── Insert user ───────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT);
// contact_number and address are not collected at self-registration,
// we store empty strings and let the user fill them in the profile.
$ins = db()->prepare(
    'INSERT INTO users
       (first_name, last_name, email, password_hash,
        contact_number, address, role, date_of_birth)
     VALUES
       (:fn, :ln, :em, :pw, :cn, :ad, "user", :dob)'
);
$ins->execute([
    ':fn'  => $firstName,
    ':ln'  => $lastName,
    ':em'  => $email,
    ':pw'  => $hash,
    ':cn'  => '',
    ':ad'  => '',
    ':dob' => $dob ?: null,
]);
$userId = db()->lastInsertId();

// ── Generate QR payload and store it ─────────
$qrData = 'evacumate://user/' . $userId;
$qrIns  = db()->prepare(
    'INSERT INTO qr_codes (user_id, qr_code_data)
     VALUES (:uid, :qr)'
);
$qrIns->execute([':uid' => $userId, ':qr' => $qrData]);

respond(['success' => true, 'user_id' => (string) $userId]);
