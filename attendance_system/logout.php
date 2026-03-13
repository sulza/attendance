<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    logActivity((int)$_SESSION['user_id'], 'LOGOUT', 'User logged out');
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . 'index.php?msg=Logged+out+successfully.');
exit;
