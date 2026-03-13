<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('hod', 'admin');
$pageTitle = 'HOD Dashboard';
$user = getCurrentUser();
$conn = getDBConnection();
$deptId = (int)($_SESSION['dept_id'] ?? 0);
$deptWhere = $deptId ? "AND c.department_id = $deptId" : '';
$lecWhere  = $deptId ? "AND u.department_id = $deptId" : '';

// Stats
$totalLecturers = $conn->query("SELECT COUNT(*) as c FROM users u WHERE u.role='lecturer' $lecWhere")->fetch_assoc()['c'];
$totalCourses   = $conn->query("SELECT COUNT(*) as c FROM courses c WHERE 1=1 $deptWhere")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) as c FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE ar.status='pending' $deptWhere")->fetch_assoc()['c'];
$verified = $conn->query("SELECT COUNT(*) as c FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE ar.status='verified' $deptWhere")->fetch_assoc()['c'];
$totalHours = $conn->query("SELECT COALESCE(SUM(ar.duration_hours),0) as h FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE ar.status='verified' $deptWhere")->fetch_assoc()['h'];
$todayLec = $conn->query("SELECT COUNT(*) as c FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE ar.attendance_date=CURDATE() $deptWhere")->fetch_assoc()['c'];

// Recent records
$recent = $conn->query("SELECT ar.*, u.full_name, c.course_code, c.course_title
    FROM attendance_records ar
    JOIN users u ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    WHERE 1=1 $deptWhere
    ORDER BY ar.created_at DESC LIMIT 10");

// Lecturers in dept
$lecturers = $conn->query("SELECT u.*, 
    (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=u.id) as sessions,
    (SELECT COALESCE(SUM(duration_hours),0) FROM attendance_records WHERE lecturer_id=u.id AND status='verified') as hours,
    (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=u.id AND status='pending') as pending
    FROM users u WHERE u.role='lecturer' $lecWhere ORDER BY u.full_name");

$conn->close();

$sidebarItems = [
    ['url'=>'hod/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard','active'=>true],
    ['divider'=>'Attendance'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'Attendance Records','badge'=>$pending > 0 ? $pending : null],
    ['url'=>'hod/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports & Downloads'],
    ['divider'=>'Department'],
    ['url'=>'hod/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'hod/courses.php','icon'=>'fas fa-book','label'=>'Department Courses'],
    ['divider'=>'Account'],
    ['url'=>'hod/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
    ['url'=>'hod/change_password.php','icon'=>'fas fa-lock','label'=>'Change Password'],
];
include '../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-chalkboard-teacher"></i></div><div><div class="stat-value"><?= $totalLecturers ?></div><div class="stat-label">Lecturers</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending Verification</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $verified ?></div><div class="stat-label">Verified Records</div></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-hourglass"></i></div><div><div class="stat-value"><?= number_format($totalHours,1) ?>h</div><div class="stat-label">Verified Hours</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-book"></i></div><div><div class="stat-value"><?= $totalCourses ?></div><div class="stat-label">Courses</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div><div><div class="stat-value"><?= $todayLec ?></div><div class="stat-label">Today's Lectures</div></div></div>
</div>

<?php if ($pending > 0): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle"></i>
  <span>You have <strong><?= $pending ?></strong> attendance record(s) awaiting verification.</span>
  <a href="<?= BASE_URL ?>admin/attendance.php?status=pending" class="btn btn-warning btn-sm" style="margin-left:auto">Review Now</a>
</div>
<?php endif ?>

<div class="grid-2 mb-3">
  <!-- Recent Records -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-clipboard-list"></i> Recent Attendance</span>
      <a href="<?= BASE_URL ?>admin/attendance.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Lecturer</th><th>Course</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php while ($r = $recent->fetch_assoc()): ?>
          <tr>
            <td><?= clean($r['full_name']) ?></td>
            <td><?= clean($r['course_code']) ?></td>
            <td><?= formatDate($r['attendance_date']) ?></td>
            <td><span class="badge <?= badgeStatus($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <a href="<?= BASE_URL ?>admin/view_attendance.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a>
              <?php if ($r['status'] === 'pending'): ?>
                <a href="<?= BASE_URL ?>admin/attendance.php?action=verify&id=<?= $r['id'] ?>" class="btn btn-success btn-sm" data-confirm="Verify?"><i class="fas fa-check"></i></a>
              <?php endif ?>
            </td>
          </tr>
          <?php endwhile ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Lecturers -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-users"></i> Department Lecturers</span>
      <a href="<?= BASE_URL ?>hod/reports.php" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Report</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Sessions</th><th>Hours</th><th>Pending</th></tr></thead>
        <tbody>
          <?php while ($r = $lecturers->fetch_assoc()): ?>
          <tr>
            <td><?= clean($r['full_name']) ?><br><small class="text-muted"><?= clean($r['email']) ?></small></td>
            <td><?= $r['sessions'] ?></td>
            <td><?= number_format($r['hours'],1) ?>h</td>
            <td><?= $r['pending'] > 0 ? '<span class="badge badge-warning">'.$r['pending'].'</span>' : '0' ?></td>
          </tr>
          <?php endwhile ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
