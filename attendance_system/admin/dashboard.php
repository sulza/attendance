<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard';
$user = getCurrentUser();
$conn = getDBConnection();

// Stats
$totalLecturers = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='lecturer'")->fetch_assoc()['c'];
$totalHODs       = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='hod'")->fetch_assoc()['c'];
$totalCourses    = $conn->query("SELECT COUNT(*) as c FROM courses")->fetch_assoc()['c'];
$totalDepts      = $conn->query("SELECT COUNT(*) as c FROM departments")->fetch_assoc()['c'];
$pendingVerify   = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE status='pending'")->fetch_assoc()['c'];
$todayCount      = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE attendance_date = CURDATE()")->fetch_assoc()['c'];
$totalHours      = $conn->query("SELECT COALESCE(SUM(duration_hours),0) as h FROM attendance_records WHERE status='verified'")->fetch_assoc()['h'];
$monthlyCount    = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())")->fetch_assoc()['c'];

// Recent attendance
$recentAtt = $conn->query("SELECT ar.*, u.full_name, c.course_title, c.course_code
    FROM attendance_records ar
    JOIN users u ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    ORDER BY ar.created_at DESC LIMIT 8");

// Activity logs
$logs = $conn->query("SELECT al.*, u.full_name FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC LIMIT 10");

// Lecturer attendance summary
$lecSummary = $conn->query("SELECT u.full_name, u.staff_id, d.name as dept,
    COUNT(ar.id) as sessions, COALESCE(SUM(ar.duration_hours),0) as hours,
    SUM(ar.status='pending') as pending, SUM(ar.status='verified') as verified
    FROM users u
    LEFT JOIN attendance_records ar ON u.id = ar.lecturer_id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role = 'lecturer'
    GROUP BY u.id ORDER BY sessions DESC LIMIT 10");

$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard','active'=>true],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/hods.php','icon'=>'fas fa-user-tie','label'=>'HODs'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Academic Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Course Assignments'],
    ['divider'=>'Attendance'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'All Attendance','badge'=>$pendingVerify > 0 ? $pendingVerify : null],
    ['url'=>'admin/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports & Analytics'],
    ['divider'=>'System'],
    ['url'=>'admin/activity_logs.php','icon'=>'fas fa-history','label'=>'Activity Logs'],
    ['url'=>'admin/settings.php','icon'=>'fas fa-cog','label'=>'Settings'],
    ['url'=>'admin/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
];
include '../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-chalkboard-teacher"></i></div>
    <div><div class="stat-value"><?= $totalLecturers ?></div><div class="stat-label">Visiting Lecturers</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-user-tie"></i></div>
    <div><div class="stat-value"><?= $totalHODs ?></div><div class="stat-label">HODs</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-clipboard-check"></i></div>
    <div><div class="stat-value"><?= $todayCount ?></div><div class="stat-label">Today's Lectures</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
    <div><div class="stat-value"><?= number_format($pendingVerify) ?></div><div class="stat-label">Pending Verification</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-hourglass-half"></i></div>
    <div><div class="stat-value"><?= number_format($totalHours, 1) ?></div><div class="stat-label">Verified Hours</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-book"></i></div>
    <div><div class="stat-value"><?= $totalCourses ?></div><div class="stat-label">Courses</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-building"></i></div>
    <div><div class="stat-value"><?= $totalDepts ?></div><div class="stat-label">Departments</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-calendar-check"></i></div>
    <div><div class="stat-value"><?= $monthlyCount ?></div><div class="stat-label">This Month</div></div>
  </div>
</div>

<div class="grid-2 mb-3">
  <!-- Recent Attendance -->
  <div class="card" style="grid-column: 1 / -1">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-clipboard-list"></i> Recent Attendance Records</span>
      <div class="btn-group">
        <button onclick="exportTableCSV('recentTable','recent_attendance')" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> CSV</button>
        <a href="<?= BASE_URL ?>admin/attendance.php" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View All</a>
      </div>
    </div>
    <div class="table-wrapper">
      <table class="data-table" id="recentTable">
        <thead><tr>
          <th>#</th><th>Lecturer</th><th>Course</th><th>Date</th><th>Time</th><th>Hours</th><th>Status</th><th>Action</th>
        </tr></thead>
        <tbody>
          <?php $i = 1; while ($r = $recentAtt->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><div class="d-flex align-center gap-2"><div class="avatar" style="width:28px;height:28px;font-size:11px"><?= strtoupper(substr($r['full_name'],0,1)) ?></div><?= clean($r['full_name']) ?></div></td>
            <td><strong><?= clean($r['course_code']) ?></strong> <span class="text-muted">— <?= clean($r['course_title']) ?></span></td>
            <td><?= formatDate($r['attendance_date']) ?></td>
            <td><?= formatTime($r['start_time']) ?> – <?= formatTime($r['end_time']) ?></td>
            <td><?= $r['duration_hours'] ? number_format($r['duration_hours'], 1) . 'h' : '—' ?></td>
            <td><span class="badge <?= badgeStatus($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <a href="<?= BASE_URL ?>admin/view_attendance.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a>
              <?php if ($r['status'] === 'pending'): ?>
                <a href="<?= BASE_URL ?>admin/verify_attendance.php?id=<?= $r['id'] ?>&action=verify" class="btn btn-success btn-sm" data-confirm="Verify this attendance?"><i class="fas fa-check"></i></a>
              <?php endif ?>
            </td>
          </tr>
          <?php endwhile ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Lecturer Summary -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-users"></i> Lecturer Summary</span>
      <a href="<?= BASE_URL ?>admin/lecturers.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Lecturer</th><th>Sessions</th><th>Hours</th><th>Pending</th></tr></thead>
        <tbody>
          <?php while ($r = $lecSummary->fetch_assoc()): ?>
          <tr>
            <td><?= clean($r['full_name']) ?><br><small class="text-muted"><?= clean($r['staff_id'] ?? '') ?> · <?= clean($r['dept'] ?? '') ?></small></td>
            <td><?= $r['sessions'] ?></td>
            <td><?= number_format($r['hours'], 1) ?>h</td>
            <td><?= $r['pending'] > 0 ? '<span class="badge badge-warning">'.$r['pending'].'</span>' : '<span class="badge badge-success">0</span>' ?></td>
          </tr>
          <?php endwhile ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Activity Log -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-history"></i> Recent Activity</span>
      <a href="<?= BASE_URL ?>admin/activity_logs.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding: 16px">
      <div class="timeline">
        <?php while ($r = $logs->fetch_assoc()): ?>
          <div class="tl-item">
            <div class="tl-dot"></div>
            <div class="tl-time"><?= date('d M, g:ia', strtotime($r['created_at'])) ?> &mdash; <?= clean($r['full_name'] ?? 'System') ?></div>
            <div class="tl-content"><strong><?= clean($r['action']) ?></strong><?= $r['description'] ? ' — ' . clean(substr($r['description'], 0, 80)) : '' ?></div>
          </div>
        <?php endwhile ?>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
