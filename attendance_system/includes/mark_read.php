<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
if (!isLoggedIn()) exit;
$uid = (int)$_SESSION['user_id'];
$conn = getDBConnection();
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
$conn->close();
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL));
exit;
