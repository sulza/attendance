<?php
// ============================================================
// VLA System - Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'visiting_lecturer_attendance');
define('BASE_URL', 'http://localhost/attendance_system/');
define('SYSTEM_NAME', 'VLA System');
define('INSTITUTION_NAME', 'University Attendance Management');

/**
 * Returns a persistent-style shared MySQLi connection.
 * Removed ->ping() to avoid deprecation issues in PHP 8.2+
 */
function getDBConnection(): mysqli {
    // Remove 'static' so every function call gets a fresh, isolated connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die('Database Connection Failed: ' . htmlspecialchars($conn->connect_error));
    }
    
    $conn->set_charset('utf8mb4');
    return $conn;
}