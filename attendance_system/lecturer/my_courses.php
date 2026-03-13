<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('lecturer');
$pageTitle = 'My Courses';
$user = getCurrentUser();
$conn = getDBConnection();
$uid = (int)$_SESSION['user_id'];
$session = getCurrentSession();
$sessId = $session ? (int)$session['id'] : 0;

$courses = $conn->query("
    SELECT c.*, d.name as dept_name,
        (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=$uid AND course_id=c.id AND session_id=$sessId) as sessions_logged,
        (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=$uid AND course_id=c.id AND session_id=$sessId AND status='verified') as verified,
        (SELECT COALESCE(SUM(duration_hours),0) FROM attendance_records WHERE lecturer_id=$uid AND course_id=c.id AND session_id=$sessId) as hours
    FROM courses c
    JOIN course_assignments ca ON c.id = ca.course_id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE ca.lecturer_id = $uid AND ca.session_id = $sessId
    ORDER BY c.course_code");

$conn->close();
$sidebarItems = [
    ['url'=>'lecturer/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'lecturer/log_attendance.php','icon'=>'fas fa-plus-circle','label'=>'Log Attendance'],
    ['url'=>'lecturer/my_attendance.php','icon'=>'fas fa-clipboard-list','label'=>'My Records'],
    ['divider'=>'Courses'],
    ['url'=>'lecturer/my_courses.php','icon'=>'fas fa-book','label'=>'My Courses','active'=>true],
    ['divider'=>'Account'],
    ['url'=>'lecturer/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
    ['url'=>'lecturer/change_password.php','icon'=>'fas fa-lock','label'=>'Change Password'],
];
include '../includes/header.php';
?>
<div class="page-header">
  <div><h2>My Courses</h2><p>Courses assigned to you for <?=clean($session['session_name']??'current')?> – <?=ucfirst($session['semester']??'')?> Semester</p></div>
  <a href="log_attendance.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Log Attendance</a>
</div>

<div class="grid-3">
  <?php $total=0; while($c=$courses->fetch_assoc()): $total++; ?>
  <div class="card">
    <div class="card-body">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:800;color:var(--primary)"><?=clean($c['course_code'])?></div>
          <div style="font-size:14px;font-weight:600;margin-top:2px"><?=clean($c['course_title'])?></div>
          <small class="text-muted"><?=clean($c['dept_name']??'')?>  &bull; Level <?=clean($c['level'])?></small>
        </div>
        <span class="badge bg-primary"><?=$c['credit_units']?> CU</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin:12px 0;text-align:center">
        <div style="background:var(--gray-50);border-radius:8px;padding:8px">
          <div style="font-size:20px;font-weight:800"><?=$c['sessions_logged']?></div>
          <div style="font-size:11px;color:var(--text-muted)">Sessions</div>
        </div>
        <div style="background:#d1fae5;border-radius:8px;padding:8px">
          <div style="font-size:20px;font-weight:800;color:#059669"><?=$c['verified']?></div>
          <div style="font-size:11px;color:#059669">Verified</div>
        </div>
        <div style="background:#dbeafe;border-radius:8px;padding:8px">
          <div style="font-size:20px;font-weight:800;color:var(--primary)"><?=number_format($c['hours'],1)?></div>
          <div style="font-size:11px;color:var(--primary)">Hours</div>
        </div>
      </div>
      <a href="log_attendance.php?course_id=<?=$c['id']?>" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
        <i class="fas fa-plus-circle"></i> Log Attendance
      </a>
    </div>
  </div>
  <?php endwhile ?>
  <?php if (!$total): ?>
  <div class="col-12">
    <div class="empty-state"><div class="empty-icon"><i class="fas fa-book-open"></i></div>
    <h4>No Courses Assigned</h4><p>You have no courses assigned for this session. Contact admin.</p></div>
  </div>
  <?php endif ?>
</div>
<?php include '../includes/footer.php'; ?>
