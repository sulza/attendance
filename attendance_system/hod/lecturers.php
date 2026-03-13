<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('hod', 'admin');
$pageTitle = 'Department Lecturers';
$user = getCurrentUser();
$conn = getDBConnection();
$deptId = (int)($_SESSION['dept_id'] ?? 0);
$deptWhere = $deptId ? "AND u.department_id = $deptId" : '';

$lecturers = $conn->query("
    SELECT u.*, d.name as dept_name,
      (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=u.id) as total_sessions,
      (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=u.id AND status='verified') as verified,
      (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=u.id AND status='pending') as pending,
      (SELECT COALESCE(SUM(duration_hours),0) FROM attendance_records WHERE lecturer_id=u.id AND status='verified') as total_hours,
      (SELECT COUNT(*) FROM course_assignments ca JOIN academic_sessions s ON ca.session_id=s.id WHERE ca.lecturer_id=u.id AND s.is_current=1) as active_courses
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role='lecturer' $deptWhere
    ORDER BY u.full_name");

$conn->close();
$sidebarItems = [
    ['url'=>'hod/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'hod/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'Attendance Records'],
    ['url'=>'hod/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports & Downloads'],
    ['divider'=>'Department'],
    ['url'=>'hod/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers','active'=>true],
    ['divider'=>'Account'],
    ['url'=>'hod/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
    ['url'=>'hod/change_password.php','icon'=>'fas fa-lock','label'=>'Change Password'],
];
include '../includes/header.php';
?>
<div class="page-header">
  <div><h2>Department Lecturers</h2><p>Overview of all visiting lecturers in your department</p></div>
  <a href="<?=BASE_URL?>hod/reports.php" class="btn btn-primary"><i class="fas fa-download"></i> Download Report</a>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-users"></i> Lecturers</span>
    <input type="text" id="tableSearch" class="form-control" placeholder="Search lecturers..." style="width:220px">
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Name</th><th>Staff ID</th><th>Qualification</th><th>Active Courses</th><th>Total Sessions</th><th>Hours (Verified)</th><th>Pending</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php $i=0; while ($r = $lecturers->fetch_assoc()): $i++; ?>
        <tr>
          <td><?=$i?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="avatar sm"><?=strtoupper(substr($r['full_name'],0,1))?></div>
              <div>
                <div class="fw-600"><?=clean($r['full_name'])?></div>
                <small class="text-muted"><?=clean($r['email'])?></small>
              </div>
            </div>
          </td>
          <td><?=clean($r['staff_id']??'—')?></td>
          <td><?=clean($r['qualification']??'—')?></td>
          <td><span class="badge bg-primary"><?=$r['active_courses']?></span></td>
          <td><?=$r['total_sessions']?></td>
          <td><?=number_format($r['total_hours'],1)?>h</td>
          <td><?=$r['pending']>0 ? '<span class="badge bg-warning">'.$r['pending'].'</span>' : '<span class="text-muted">0</span>'?></td>
          <td><span class="badge <?=$r['status']==='active'?'bg-success':'bg-danger'?>"><?=ucfirst($r['status'])?></span></td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
