<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Course Assignments';
$user = getCurrentUser();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    $uid = (int)$_SESSION['user_id'];
    if ($action === 'assign') {
        $lecId = sanitizeInt($_POST['lecturer_id']);
        $courseId = sanitizeInt($_POST['course_id']);
        $sessId = sanitizeInt($_POST['session_id']);
        $dup = $conn->query("SELECT id FROM course_assignments WHERE lecturer_id=$lecId AND course_id=$courseId AND session_id=$sessId")->fetch_assoc();
        if ($dup) { setFlash('This assignment already exists.', 'warning'); }
        else {
            $conn->query("INSERT INTO course_assignments (lecturer_id,course_id,session_id,assigned_by) VALUES($lecId,$courseId,$sessId,$uid)");
            logActivity($uid, 'ASSIGN_COURSE', "Assigned course $courseId to lecturer $lecId");
            setFlash('Course assigned successfully.', 'success');
        }
    } elseif ($action === 'remove') {
        $id = sanitizeInt($_POST['assignment_id']);
        $conn->query("DELETE FROM course_assignments WHERE id=$id");
        setFlash('Assignment removed.', 'danger');
    }
    header('Location: assignments.php'); exit;
}

$lecturers = $conn->query("SELECT id, full_name, staff_id FROM users WHERE role='lecturer' AND status='active' ORDER BY full_name");
$courses   = $conn->query("SELECT c.*, d.name as dept FROM courses c LEFT JOIN departments d ON c.department_id=d.id WHERE c.status='active' ORDER BY c.course_code");
$sessions  = $conn->query("SELECT * FROM academic_sessions ORDER BY is_current DESC, created_at DESC");
$currentSession = $conn->query("SELECT id FROM academic_sessions WHERE is_current=1 LIMIT 1")->fetch_assoc();
$currentSessId = $currentSession['id'] ?? 0;

$assignments = $conn->query("
    SELECT ca.*, u.full_name as lec_name, u.staff_id, c.course_code, c.course_title, c.level, s.session_name, s.semester,
           a.full_name as assigner
    FROM course_assignments ca
    JOIN users u ON ca.lecturer_id = u.id
    JOIN courses c ON ca.course_id = c.id
    JOIN academic_sessions s ON ca.session_id = s.id
    LEFT JOIN users a ON ca.assigned_by = a.id
    ORDER BY s.is_current DESC, u.full_name, c.course_code");

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Academic Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Course Assignments','active'=>true],
    ['divider'=>'Reports'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'All Attendance'],
    ['url'=>'admin/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports'],
    ['url'=>'admin/activity_logs.php','icon'=>'fas fa-history','label'=>'Activity Logs'],
    ['divider'=>'System'],
    ['url'=>'admin/settings.php','icon'=>'fas fa-cog','label'=>'Settings'],
    ['url'=>'admin/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
];
include '../includes/header.php';
?>
<div class="page-header">
  <div><h2>Course Assignments</h2><p>Assign courses to lecturers for each academic session</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('assignModal').style.display='flex'">
    <i class="fas fa-plus"></i> New Assignment
  </button>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-link"></i> All Assignments</span>
    <input type="text" id="tableSearch" class="form-control" placeholder="Search..." style="width:200px">
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>#</th><th>Lecturer</th><th>Course</th><th>Level</th><th>Session</th><th>Assigned By</th><th>Action</th></tr></thead>
      <tbody>
        <?php $i=0; while($r=$assignments->fetch_assoc()): $i++; ?>
        <tr>
          <td><?=$i?></td>
          <td><div class="fw-600"><?=clean($r['lec_name'])?></div><small class="text-muted"><?=clean($r['staff_id']??'')?></small></td>
          <td><div class="fw-600"><?=clean($r['course_code'])?></div><small class="text-muted"><?=clean($r['course_title'])?></small></td>
          <td>Level <?=clean($r['level'])?></td>
          <td><?=clean($r['session_name'])?> – <?=ucfirst($r['semester'])?></td>
          <td><?=clean($r['assigner']??'System')?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="assignment_id" value="<?=$r['id']?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="Remove this assignment?"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Assign Modal -->
<div id="assignModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:white;border-radius:14px;padding:28px;max-width:500px;width:100%">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-size:17px;font-weight:700;margin:0">Assign Course to Lecturer</h3>
      <button onclick="document.getElementById('assignModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="assign">
      <div class="form-group mb-3">
        <label>Lecturer <span class="req">*</span></label>
        <select name="lecturer_id" class="form-control" required>
          <option value="">-- Select Lecturer --</option>
          <?php $lecturers->data_seek(0); while($l=$lecturers->fetch_assoc()): ?>
          <option value="<?=$l['id']?>"><?=clean($l['full_name'])?> (<?=clean($l['staff_id']??'')?>) </option>
          <?php endwhile ?>
        </select>
      </div>
      <div class="form-group mb-3">
        <label>Course <span class="req">*</span></label>
        <select name="course_id" class="form-control" required>
          <option value="">-- Select Course --</option>
          <?php $courses->data_seek(0); while($c=$courses->fetch_assoc()): ?>
          <option value="<?=$c['id']?>"><?=clean($c['course_code'])?> — <?=clean($c['course_title'])?></option>
          <?php endwhile ?>
        </select>
      </div>
      <div class="form-group mb-3">
        <label>Academic Session <span class="req">*</span></label>
        <select name="session_id" class="form-control" required>
          <?php $sessions->data_seek(0); while($s=$sessions->fetch_assoc()): ?>
          <option value="<?=$s['id']?>" <?=$s['id']==$currentSessId?'selected':''?>><?=clean($s['session_name'])?> – <?=ucfirst($s['semester'])?><?=$s['is_current']?' (Current)':''?></option>
          <?php endwhile ?>
        </select>
      </div>
      <div class="btn-group">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Assign</button>
        <button type="button" onclick="document.getElementById('assignModal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
