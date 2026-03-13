<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Academic Sessions';
$user = getCurrentUser();
$conn = getDBConnection();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean($_POST['action'] ?? '');
    $uid = (int)$_SESSION['user_id'];

    if ($action === 'add') {
        $name = clean($_POST['session_name']); $sem = clean($_POST['semester']);
        $sd = clean($_POST['start_date']); $ed = clean($_POST['end_date']);
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        if ($isCurrent) $conn->query("UPDATE academic_sessions SET is_current=0");
        $conn->query("INSERT INTO academic_sessions (session_name,semester,start_date,end_date,is_current) VALUES('$name','$sem','$sd','$ed',$isCurrent)");
        logActivity($uid, 'ADD_SESSION', "Added session $name $sem");
        setFlash('Session added successfully.', 'success');
    } elseif ($action === 'set_current') {
        $id = sanitizeInt($_POST['session_id']);
        $conn->query("UPDATE academic_sessions SET is_current=0");
        $conn->query("UPDATE academic_sessions SET is_current=1 WHERE id=$id");
        setFlash('Current session updated.', 'success');
    } elseif ($action === 'delete') {
        $id = sanitizeInt($_POST['session_id']);
        $conn->query("DELETE FROM academic_sessions WHERE id=$id AND is_current=0");
        setFlash('Session deleted.', 'danger');
    }
    header('Location: sessions.php'); exit;
}

$sessions = $conn->query("SELECT s.*, (SELECT COUNT(*) FROM attendance_records WHERE session_id=s.id) as records_count FROM academic_sessions s ORDER BY s.created_at DESC");
$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Academic Sessions','active'=>true],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Course Assignments'],
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
  <div><h2>Academic Sessions</h2><p>Manage academic sessions and semesters</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
    <i class="fas fa-plus"></i> Add Session
  </button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>#</th><th>Session</th><th>Semester</th><th>Start Date</th><th>End Date</th><th>Records</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $i=0; while($s=$sessions->fetch_assoc()): $i++; ?>
        <tr>
          <td><?=$i?></td>
          <td class="fw-600"><?=clean($s['session_name'])?></td>
          <td><?=ucfirst($s['semester'])?> Semester</td>
          <td><?=formatDate($s['start_date'])?></td>
          <td><?=formatDate($s['end_date'])?></td>
          <td><?=$s['records_count']?></td>
          <td><?=$s['is_current'] ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Current</span>' : '<span class="badge bg-secondary">Past</span>'?></td>
          <td>
            <?php if (!$s['is_current']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="set_current">
              <input type="hidden" name="session_id" value="<?=$s['id']?>">
              <button type="submit" class="btn btn-outline btn-sm" data-confirm="Set this as current session?">Set Current</button>
            </form>
            <?php if ($s['records_count']==0): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="session_id" value="<?=$s['id']?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this session?"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif ?>
            <?php endif ?>
          </td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:white;border-radius:14px;padding:28px;max-width:480px;width:100%">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-size:17px;font-weight:700;margin:0">Add Academic Session</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid-2 mb-3">
        <div class="form-group">
          <label>Session Name <span class="req">*</span></label>
          <input type="text" name="session_name" class="form-control" placeholder="e.g. 2024/2025" required>
        </div>
        <div class="form-group">
          <label>Semester <span class="req">*</span></label>
          <select name="semester" class="form-control" required>
            <option value="first">First</option>
            <option value="second">Second</option>
            <option value="summer">Summer</option>
          </select>
        </div>
      </div>
      <div class="form-grid-2 mb-3">
        <div class="form-group">
          <label>Start Date</label>
          <input type="date" name="start_date" class="form-control">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="date" name="end_date" class="form-control">
        </div>
      </div>
      <div class="form-group mb-3">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="is_current"> Set as current session
        </label>
      </div>
      <div class="btn-group">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Session</button>
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
