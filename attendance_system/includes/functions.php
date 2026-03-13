<?php
/**
 * VLA System - includes/functions.php
 * This file simply loads the main config which contains all helper functions.
 * All pages do: require_once '../config/database.php'; require_once '../includes/functions.php';
 */

// All helper functions are defined in config/database.php
// This file exists for compatibility - no duplicate definitions needed.

// Only define functions that are NOT already in config/database.php

if (!function_exists('clean')) {
    function clean(string $str): string {
        return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitizeInt')) {
    function sanitizeInt($val): int {
        return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
    }
}

if (!function_exists('formatDate')) {
    function formatDate(string $date, string $fmt = 'd M, Y'): string {
        return $date ? date($fmt, strtotime($date)) : '—';
    }
}

if (!function_exists('formatTime')) {
    function formatTime(string $time): string {
        return $time ? date('h:i A', strtotime($time)) : '—';
    }
}

if (!function_exists('statusBadge')) {
    function statusBadge(string $status): string {
        $map = ['pending'=>'warning','verified'=>'success','rejected'=>'danger'];
        $cls = $map[$status] ?? 'secondary';
        $icon = match($status) { 'pending'=>'clock','verified'=>'check-circle','rejected'=>'times-circle', default=>'circle' };
        return "<span class='badge badge-{$cls}'><i class='fas fa-{$icon} me-1'></i>" . ucfirst($status) . "</span>";
    }
}

if (!function_exists('roleBadge')) {
    function roleBadge(string $role): string {
        $map = ['admin'=>'danger','hod'=>'warning','lecturer'=>'primary'];
        $cls = $map[$role] ?? 'secondary';
        return "<span class='badge badge-{$cls}'>" . ucfirst($role) . "</span>";
    }
}

if (!function_exists('badgeStatus')) {
    function badgeStatus(string $status): string {
        return match($status) {
            'verified' => 'badge-success',
            'pending'  => 'badge-warning',
            'rejected' => 'badge-danger',
            default    => 'badge-secondary',
        };
    }
}

if (!function_exists('calcDuration')) {
    function calcDuration(string $start, string $end): float {
        if (!$start || !$end) return 0;
        $s = strtotime($start);
        $e = strtotime($end);
        if ($e <= $s) return 0;
        return round(($e - $s) / 3600, 2);
    }
}

if (!function_exists('durationHours')) {
    function durationHours(float $hours): string {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        if ($h && $m) return "{$h}h {$m}m";
        if ($h) return "{$h}hr" . ($h > 1 ? 's' : '');
        return "{$m}min";
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void { header("Location: $url"); exit; }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('requireRole')) {
    function requireRole(string ...$roles): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }
        if (!in_array($_SESSION['role'], $roles)) {
            header('Location: ' . BASE_URL . 'index.php?err=unauthorized');
            exit;
        }
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) return null;
        $conn = getDBConnection();
        $uid = (int)$_SESSION['user_id'];
        $r = $conn->query("SELECT u.*, d.name as dept_name, d.code as dept_code
            FROM users u LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = $uid LIMIT 1");
        $conn->close();
        return $r ? $r->fetch_assoc() : null;
    }
}

if (!function_exists('getCurrentSession')) {
    function getCurrentSession(): ?array {
        $conn = getDBConnection();
        $r = $conn->query("SELECT * FROM academic_sessions WHERE is_current = 1 LIMIT 1");
        $conn->close();
        return $r && $r->num_rows ? $r->fetch_assoc() : null;
    }
}

if (!function_exists('setFlash')) {
    function setFlash(string $first, string $second = 'success'): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $validTypes = ['success','danger','warning','info'];
        if (in_array($first, $validTypes)) {
            $type = $first; $message = $second;
        } else {
            $message = $first; $type = in_array($second, $validTypes) ? $second : 'success';
        }
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
}

if (!function_exists('getFlash')) {
    function getFlash(): ?array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
}

if (!function_exists('logActivity')) {
    function logActivity(int $userId, string $action, string $description = ''): void {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?,?,?,?,?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt->bind_param('issss', $userId, $action, $description, $ip, $ua);
            $stmt->execute();
            $conn->close();
        } catch (Exception $e) {}
    }
}

if (!function_exists('countUnread')) {
    function countUnread(int $userId): int {
        $conn = getDBConnection();
        $r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$userId AND is_read=0");
        $count = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $conn->close();
        return $count;
    }
}

if (!function_exists('getUnreadNotifications')) {
    function getUnreadNotifications(int $userId): array {
        $conn = getDBConnection();
        $r = $conn->query("SELECT * FROM notifications WHERE user_id=$userId AND is_read=0 ORDER BY created_at DESC LIMIT 10");
        $rows = [];
        if ($r) while ($row = $r->fetch_assoc()) $rows[] = $row;
        $conn->close();
        return $rows;
    }
}

if (!function_exists('sendNotification')) {
    function sendNotification(int $userId, string $title, string $message, string $type = 'info'): void {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $stmt->bind_param('isss', $userId, $title, $message, $type);
            $stmt->execute();
            $conn->close();
        } catch (Exception $e) {}
    }
}

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->bind_param('s', $key);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $conn->close();
            return $r['setting_value'] ?? $default;
        } catch (Exception $e) { return $default; }
    }
}

if (!function_exists('getInstitutionName')) {
    function getInstitutionName(): string {
        try { return getSetting('institution_name', defined('INSTITUTION_NAME') ? INSTITUTION_NAME : 'University'); }
        catch (Exception $e) { return 'University'; }
    }
}

if (!function_exists('getUsersByRole')) {
    function getUsersByRole(string $role, ?int $deptId = null): mysqli_result|false {
        $conn = getDBConnection();
        $sql = "SELECT id, full_name, staff_id, email FROM users WHERE role='$role' AND status='active'";
        if ($deptId) $sql .= " AND department_id=$deptId";
        $sql .= " ORDER BY full_name";
        $result = $conn->query($sql);
        $conn->close();
        return $result;
    }
}

if (!function_exists('getDepartments')) {
    function getDepartments(): mysqli_result|false {
        $conn = getDBConnection();
        $r = $conn->query("SELECT * FROM departments ORDER BY name");
        $conn->close();
        return $r;
    }
}

if (!function_exists('sanitizeInt')) {
    function sanitizeInt_($val): int {
        return (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
    }
}
