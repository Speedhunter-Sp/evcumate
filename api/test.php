<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'u793073111_evacumate_db');
define('DB_USER',    'u793073111_julian0929');
define('DB_PASS',    'Julian.924');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=u793073111_evacumate_db;charset=utf8mb4',
        'u793073111_julian0929',
        'Julian.924',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo json_encode(['success' => true, 'message' => 'Connected!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}