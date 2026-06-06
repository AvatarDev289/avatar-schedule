<?php
/**
 * db.php — Database connection (PDO + prepared statements)
 * Avatar Electric — Schedule of Project Dashboard
 *
 * Edit the constants below to match your environment.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

// ---- Database configuration --------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'avatar_schedule');
define('DB_USER', 'root');     // XAMPP default
define('DB_PASS', '');         // XAMPP default (empty)
define('DB_CHARSET', 'utf8mb4');

// ---- App settings ------------------------------------------------------
define('APP_NAME', 'Avatar Electric — Schedule of Project');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', 'uploads');
define('NEAR_DUE_DAYS', 7); // number of days considered "near due"

/**
 * Returns a shared PDO connection (singleton).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die(
            '<div style="font-family:sans-serif;padding:2rem;color:#b91c1c">'
            . '<h2>เชื่อมต่อฐานข้อมูลไม่สำเร็จ</h2>'
            . '<p>กรุณาตรวจสอบการตั้งค่าใน <code>db.php</code> และ import <code>database.sql</code> แล้ว</p>'
            . '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>'
            . '</div>'
        );
    }

    return $pdo;
}
