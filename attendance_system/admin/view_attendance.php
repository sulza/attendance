<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin','hod');
header('Location: attendance.php'); // redirect to main attendance page
exit;
