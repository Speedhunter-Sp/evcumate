<?php
// api/config.php
// ─────────────────────────────────────────────
//  DATABASE CONFIGURATION  –  Hostinger MySQL
//  Upload this entire /api/ folder to your
//  Hostinger public_html directory.
//
//  In Hostinger hPanel → Databases, your
//  credentials will look like:
//    Host:     127.0.0.1  (always localhost on shared hosting)
//    DB Name:  u793073111_evacumate_db
//    Username: u793073111_evacumate  (or whatever you set)
//    Password: <your password>
// ─────────────────────────────────────────────

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'u793073111_evacumate_db');
define('DB_USER',    'u793073111_julian0929');
define('DB_PASS',    'Julian.924');              // ← make sure this matches exactly
define('DB_CHARSET', 'utf8mb4');

// ── Helper: return a PDO connection ──────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname='    . DB_NAME
             . ';charset='   . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Helper: send JSON response and exit ──────
function respond(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Helper: read + decode JSON request body ──
function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── CORS – allow the Flutter app to call this API ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
