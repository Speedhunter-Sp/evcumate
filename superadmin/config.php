<?php
// superadmin/config.php
// ─────────────────────────────────────────────
//  Shared config for the superadmin panel.
//  CHANGE THE CREDENTIALS BELOW before uploading.
// ─────────────────────────────────────────────

// ── Superadmin panel login (separate from app accounts) ──
define('SA_USERNAME', 'superadmin');          // ← change this
define('SA_PASSWORD', 'ChangeMe!2024');       // ← change this to something strong

// ── Database (same as api/config.php) ────────
define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'u793073111_evacumate_db');
define('DB_USER',    'u793073111_julian0929');
define('DB_PASS',    'Julian.924');
define('DB_CHARSET', 'utf8mb4');

session_start();

// ── DB connection ─────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Auth guard — redirect to login if not authed ──
function requireAuth(): void {
    if (empty($_SESSION['sa_authed'])) {
        header('Location: index.php');
        exit;
    }
}

// ── CSRF token helpers ────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        die('Invalid CSRF token.');
    }
}

// ── Flash message helpers ─────────────────────
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}