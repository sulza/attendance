<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('lecturer');
$pageTitle = 'My Dashboard';
$user = getCurrentUser();
$conn = getDBConnection();
$uid = (int)$_SESSION['user_id'];
$session = getCurrentSession();
$sessId = $session ? (int)$session['id'] : 0;

// Stats
$totalSessions = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE lecturer_id=$uid")->fetch_assoc()['c'];
$verifiedCount = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE lecturer_id=$uid AND status='verified'")->fetch_assoc()['c'];
$pendingCount  = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE lecturer_id=$uid AND status='pending'")->fetch_assoc()['c'];
$rejectedCount = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE lecturer_id=$uid AND status='rejected'")->fetch_assoc()['c'];
$totalHours    = $conn->query("SELECT COALESCE(SUM(duration_hours),0) as h FROM attendance_records WHERE lecturer_id=$uid AND status='verified'")->fetch_assoc()['h'];
$monthHours    = $conn->query("SELECT COALESCE(SUM(duration_hours),0) as h FROM attendance_records WHERE lecturer_id=$uid AND status='verified' AND MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())")->fetch_assoc()['h'];

// Assigned courses
$courses = $conn->query("SELECT ca.*, c.course_code, c.course_title, c.credit_units, c.level,
    (SELECT COUNT(*) FROM attendance_records WHERE course_id=c.id AND lecturer_id=$uid) as logged
    FROM course_assignments ca
    JOIN courses c ON ca.course_id = c.id
    WHERE ca.lecturer_id = $uid AND ca.session_id = $sessId");

// Recent attendance
$recent = $conn->query("SELECT ar.*, c.course_code, c.course_title
    FROM attendance_records ar JOIN courses c ON ar.course_id=c.id
    WHERE ar.lecturer_id=$uid ORDER BY ar.created_at DESC LIMIT 8");

$conn->close();

$sidebarItems = [
    ['url'=>'lecturer/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard','active'=>true],
    ['divider'=>'Attendance'],
    ['url'=>'lecturer/log_attendance.php','icon'=>'fas fa-plus-circle','label'=>'Log Attendance'],
    ['url'=>'lecturer/my_attendance.php','icon'=>'fas fa-clipboard-list','label'=>'My Records'],
    ['divider'=>'Courses'],
    ['url'=>'lecturer/my_courses.php','icon'=>'fas fa-book','label'=>'My Courses'],
    ['divider'=>'Account'],
    ['url'=>'lecturer/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
    ['url'=>'lecturer/change_password.php','icon'=>'fas fa-lock','label'=>'Change Password'],
];
include '../includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div><div><div class="stat-value"><?= $totalSessions ?></div><div class="stat-label">Total Sessions Logged</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $verifiedCount ?></div><div class="stat-label">Verified</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending Review</div></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-hourglass"></i></div><div><div class="stat-value"><?= number_format($totalHours,1) ?>h</div><div class="stat-label">Total Verified Hours</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-calendar-month"></i></div><div><div class="stat-value"><?= number_format($monthHours,1) ?>h</div><div class="stat-label">Hours This Month</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div><div class="stat-value"><?= $rejectedCount ?></div><div class="stat-label">Rejected</div></div></div>
</div>

<!-- Quick action -->
<div class="card mb-3" style="background:linear-gradient(135deg,var(--primary),var(--secondary));border:none">
  <div class="card-body" style="padding:24px;display:flex;align-items:center;justify-content:space-between;color:#fff;flex-wrap:wrap;gap:16px">
    <div>
      <h3 style="font-family:'Space Grotesk',sans-serif;font-size:20px;margin-bottom:4px">Log Today's Attendance</h3>
      <p style="opacity:.85;font-size:13px"><?= date('l, F j, Y') ?> · <?= $session ? clean($session['session_name'].' – '.ucfirst($session['semester'])) : 'No active session' ?></p>
    </div>
    <a href="<?= BASE_URL ?>lecturer/log_attendance.php" class="btn" style="background:#fff;color:var(--primary);font-weight:700;padding:10px 24px"><i class="fas fa-plus"></i> Log Attendance</a>
  </div>
</div>

<div class="grid-2 mb-3">
  <!-- My Courses -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-book-open"></i> My Courses (<?= $session ? clean($session['session_name']) : 'N/A' ?>)</span>
      <a href="<?= BASE_URL ?>lecturer/my_courses.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Code</th><th>Title</th><th>Level</th><th>Units</th><th>Sessions Logged</th></tr></thead>
        <tbody>
          <?php if ($courses->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:32px">No courses assigned for current session.</td></tr>
          <?php else: while ($r = $courses->fetch_assoc()): ?>
          <tr>
            <td><strong><?= clean($r['course_code']) ?></strong></td>
            <td><?= clean($r['course_title']) ?></td>
            <td><?= $r['level'] ?></td>
            <td><?= $r['credit_units'] ?></td>
            <td><span class="badge badge-info"><?= $r['logged'] ?></span></td>
          </tr>
          <?php endwhile; endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Attendance -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-history"></i> Recent Attendance</span>
      <a href="<?= BASE_URL ?>lecturer/my_attendance.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Course</th><th>Date</th><th>Hours</th><th>Status</th></tr></thead>
        <tbody>
          <?php if ($recent->num_rows === 0): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:32px">No attendance logged yet.</td></tr>
          <?php else: while ($r = $recent->fetch_assoc()): ?>
          <tr>
            <td><strong><?= clean($r['course_code']) ?></strong><br><small><?= clean(substr($r['course_title'],0,30)) ?></small></td>
            <td><?= formatDate($r['attendance_date']) ?></td>
            <td><?= $r['duration_hours'] ? number_format($r['duration_hours'],1).'h' : '—' ?></td>
            <td><span class="badge <?= badgeStatus($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
          <?php endwhile; endif ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
