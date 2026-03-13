<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin', 'hod');
$pageTitle = 'Attendance Management';
$conn = getDBConnection();

// Handle verify / reject
$action = $_GET['action'] ?? '';
$id = sanitizeInt($_GET['id'] ?? 0);

if ($action === 'verify' && $id) {
    $uid = (int)$_SESSION['user_id'];
    $conn->query("UPDATE attendance_records SET status='verified', verified_by=$uid, verified_at=NOW() WHERE id=$id");
    // Get lecturer id for notification
    $ar = $conn->query("SELECT lecturer_id FROM attendance_records WHERE id=$id")->fetch_assoc();
    if ($ar) sendNotification($ar['lecturer_id'], 'Attendance Verified', "Your attendance record #$id has been verified.", 'success');
    setFlash('success', 'Attendance verified successfully.');
    logActivity($_SESSION['user_id'], 'VERIFY_ATTENDANCE', "Verified record ID: $id");
    header('Location: attendance.php'); exit;
}

if ($action === 'reject' && $id && isset($_POST['reason'])) {
    $reason = clean($_POST['reason']);
    $uid = (int)$_SESSION['user_id'];
    $conn->query("UPDATE attendance_records SET status='rejected', verified_by=$uid, verified_at=NOW(), rejection_reason='$reason' WHERE id=$id");
    $ar = $conn->query("SELECT lecturer_id FROM attendance_records WHERE id=$id")->fetch_assoc();
    if ($ar) sendNotification($ar['lecturer_id'], 'Attendance Rejected', "Attendance record #$id was rejected. Reason: $reason", 'danger');
    setFlash('danger', 'Attendance rejected.');
    logActivity($_SESSION['user_id'], 'REJECT_ATTENDANCE', "Rejected ID: $id – $reason");
    header('Location: attendance.php'); exit;
}

// Filters
$s_lecturer = sanitizeInt($_GET['lecturer'] ?? 0);
$s_course   = sanitizeInt($_GET['course'] ?? 0);
$s_status   = clean($_GET['status'] ?? '');
$s_from     = clean($_GET['from'] ?? '');
$s_to       = clean($_GET['to'] ?? '');
$s_session  = sanitizeInt($_GET['session_id'] ?? 0);

$where = 'WHERE 1=1';
if ($s_lecturer) $where .= " AND ar.lecturer_id = $s_lecturer";
if ($s_course)   $where .= " AND ar.course_id = $s_course";
if ($s_status)   $where .= " AND ar.status = '$s_status'";
if ($s_from)     $where .= " AND ar.attendance_date >= '$s_from'";
if ($s_to)       $where .= " AND ar.attendance_date <= '$s_to'";
if ($s_session)  $where .= " AND ar.session_id = $s_session";
if ($_SESSION['role'] === 'hod' && !empty($_SESSION['dept_id'])) {
    $deptId = (int)$_SESSION['dept_id'];
    $where .= " AND c.department_id = $deptId";
}

$records = $conn->query("SELECT ar.*, u.full_name as lecturer_name, c.course_title, c.course_code,
    v.full_name as verifier_name, s.session_name, s.semester
    FROM attendance_records ar
    JOIN users u ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    LEFT JOIN users v ON ar.verified_by = v.id
    LEFT JOIN academic_sessions s ON ar.session_id = s.id
    $where ORDER BY ar.attendance_date DESC, ar.start_time DESC");

// Summary counts
$counts = $conn->query("SELECT 
    SUM(ar.status='pending') as pending,
    SUM(ar.status='verified') as verified,
    SUM(ar.status='rejected') as rejected,
    COUNT(*) as total,
    COALESCE(SUM(CASE WHEN ar.status='verified' THEN ar.duration_hours ELSE 0 END),0) as hours
    FROM attendance_records ar JOIN courses c ON ar.course_id = c.id $where")->fetch_assoc();

$lecturers = getUsersByRole('lecturer');
$sessions  = $conn->query("SELECT * FROM academic_sessions ORDER BY id DESC");
$courses   = $conn->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code");

$conn->close();

$isAdmin = $_SESSION['role'] === 'admin';
$sidebarItems = [
    ['url'=>($isAdmin?'admin':'hod').'/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>($isAdmin?'admin':'hod').'/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'Attendance Records','active'=>true],
    ['url'=>($isAdmin?'admin':'hod').'/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>($isAdmin?'admin':'hod').'/profile.php','icon'=>'fas fa-user','label'=>'Profile'],
];
$breadcrumb = [['label' => 'Attendance']];
include '../includes/header.php';
?>

<!-- Summary Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-list"></i></div><div><div class="stat-value"><?= $counts['total'] ?></div><div class="stat-label">Total Records</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $counts['pending'] ?></div><div class="stat-label">Pending</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $counts['verified'] ?></div><div class="stat-label">Verified</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div><div class="stat-value"><?= $counts['rejected'] ?></div><div class="stat-label">Rejected</div></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-hourglass"></i></div><div><div class="stat-value"><?= number_format($counts['hours'],1) ?>h</div><div class="stat-label">Verified Hours</div></div></div>
</div>

<!-- Filters -->
<form method="GET">
<div class="filter-bar mb-2" style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius)">
  <select name="lecturer" class="form-control">
    <option value="">All Lecturers</option>
    <?php foreach ($lecturers as $l): ?>
      <option value="<?= $l['id'] ?>" <?= $s_lecturer == $l['id'] ? 'selected' : '' ?>><?= clean($l['full_name']) ?></option>
    <?php endforeach ?>
  </select>
  <select name="course" class="form-control">
    <option value="">All Courses</option>
    <?php while ($c = $courses->fetch_assoc()): ?>
      <option value="<?= $c['id'] ?>" <?= $s_course == $c['id'] ? 'selected' : '' ?>><?= clean($c['course_code']) ?> – <?= clean($c['course_title']) ?></option>
    <?php endwhile ?>
  </select>
  <select name="status" class="form-control">
    <option value="">All Status</option>
    <option value="pending"  <?= $s_status === 'pending'  ? 'selected' : '' ?>>Pending</option>
    <option value="verified" <?= $s_status === 'verified' ? 'selected' : '' ?>>Verified</option>
    <option value="rejected" <?= $s_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
  </select>
  <input type="date" name="from" class="form-control" value="<?= $s_from ?>" placeholder="From">
  <input type="date" name="to"   class="form-control" value="<?= $s_to ?>"   placeholder="To">
  <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
  <a href="attendance.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
  <button type="button" onclick="exportTableCSV('attTable','attendance_report')" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> CSV</button>
  <button type="button" onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
</div>
</form>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-clipboard-check"></i> Attendance Records</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table" id="attTable">
      <thead><tr>
        <th>#</th><th>Lecturer</th><th>Course</th><th>Date</th><th>Start</th><th>End</th><th>Hours</th><th>Topic</th><th>Students</th><th>Type</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php $i=1; while ($r = $records->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= clean($r['lecturer_name']) ?></td>
          <td><strong><?= clean($r['course_code']) ?></strong><br><small><?= clean($r['course_title']) ?></small></td>
          <td><?= formatDate($r['attendance_date']) ?></td>
          <td><?= formatTime($r['start_time']) ?></td>
          <td><?= formatTime($r['end_time']) ?></td>
          <td><?= $r['duration_hours'] ? number_format($r['duration_hours'],1).'h' : '—' ?></td>
          <td><?= clean(substr($r['topic_covered'] ?? '—', 0, 40)) ?><?= strlen($r['topic_covered'] ?? '') > 40 ? '...' : '' ?></td>
          <td><?= $r['students_present'] ?></td>
          <td><?= ucfirst($r['lecture_type'] ?? '') ?></td>
          <td>
            <span class="badge <?= badgeStatus($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
            <?php if ($r['status'] === 'verified'): ?>
              <br><small class="text-muted">by <?= clean($r['verifier_name'] ?? 'N/A') ?></small>
            <?php endif ?>
          </td>
          <td>
            <div class="btn-group">
              <a href="view_attendance.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
              <?php if ($r['status'] === 'pending'): ?>
                <a href="attendance.php?action=verify&id=<?= $r['id'] ?>" class="btn btn-success btn-sm" data-confirm="Verify this record?" title="Verify"><i class="fas fa-check"></i></a>
                <button class="btn btn-danger btn-sm" onclick="openReject(<?= $r['id'] ?>)" title="Reject"><i class="fas fa-times"></i></button>
              <?php endif ?>
            </div>
          </td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-times-circle" style="color:var(--danger)"></i> Reject Attendance</span>
      <button class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" id="rejectForm">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for Rejection <span class="req">*</span></label>
          <textarea name="reason" class="form-control" required rows="4" placeholder="Provide clear reason..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fas fa-times-circle"></i> Reject</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReject(id) {
  document.getElementById('rejectForm').action = 'attendance.php?action=reject&id=' + id;
  document.getElementById('rejectModal').classList.add('open');
}
</script>

<?php include '../includes/footer.php'; ?>
