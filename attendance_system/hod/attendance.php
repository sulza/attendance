<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('hod', 'admin');
$pageTitle = 'Attendance Records';
$user = getCurrentUser();
$conn = getDBConnection();
$deptId = (int)($_SESSION['dept_id'] ?? 0);
$deptWhere = $deptId ? "AND c.department_id = $deptId" : '';

// Handle verify / reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = sanitizeInt($_POST['record_id']);
    $action = clean($_POST['action']);
    $uid = (int)$_SESSION['user_id'];
    
    if ($action === 'verify') {
        $conn->query("UPDATE attendance_records SET status='verified', verified_by=$uid, verified_at=NOW() WHERE id=$id");
        // Notify lecturer
        $rec = $conn->query("SELECT ar.lecturer_id, c.course_title FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE ar.id=$id")->fetch_assoc();
        if ($rec) sendNotification($rec['lecturer_id'], 'Attendance Verified', 'Your attendance for ' . $rec['course_title'] . ' has been verified.', 'success');
        logActivity($uid, 'VERIFY_ATTENDANCE', "Verified attendance ID $id");
        setFlash('Attendance record verified successfully.', 'success');
    } elseif ($action === 'reject') {
        $reason = clean($_POST['reason'] ?? 'No reason provided');
        $conn->query("UPDATE attendance_records SET status='rejected', rejection_reason='$reason', verified_by=$uid, verified_at=NOW() WHERE id=$id");
        $rec = $conn->query("SELECT ar.lecturer_id, c.course_title FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE ar.id=$id")->fetch_assoc();
        if ($rec) sendNotification($rec['lecturer_id'], 'Attendance Rejected', 'Your attendance for ' . $rec['course_title'] . ' was rejected: ' . $reason, 'danger');
        logActivity($uid, 'REJECT_ATTENDANCE', "Rejected attendance ID $id: $reason");
        setFlash('Attendance record rejected.', 'danger');
    }
    header('Location: attendance.php'); exit;
}

// Filters
$statusFilter = clean($_GET['status'] ?? '');
$lecFilter    = sanitizeInt($_GET['lecturer'] ?? 0);
$dateFrom     = clean($_GET['date_from'] ?? '');
$dateTo       = clean($_GET['date_to'] ?? '');

$where = "WHERE 1=1 $deptWhere";
if ($statusFilter) $where .= " AND ar.status = '$statusFilter'";
if ($lecFilter)    $where .= " AND ar.lecturer_id = $lecFilter";
if ($dateFrom)     $where .= " AND ar.attendance_date >= '$dateFrom'";
if ($dateTo)       $where .= " AND ar.attendance_date <= '$dateTo'";

$records = $conn->query("
    SELECT ar.*, u.full_name, u.staff_id, c.course_code, c.course_title, d.name as dept_name,
           v.full_name as verifier_name
    FROM attendance_records ar
    JOIN users u ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    LEFT JOIN users v ON ar.verified_by = v.id
    $where ORDER BY ar.created_at DESC LIMIT 200");

$lecturers = $conn->query("SELECT id, full_name FROM users WHERE role='lecturer'" . ($deptId ? " AND department_id=$deptId" : '') . " ORDER BY full_name");

$conn->close();

$sidebarItems = [
    ['url'=>'hod/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'hod/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'Attendance Records','active'=>true],
    ['url'=>'hod/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports & Downloads'],
    ['divider'=>'Department'],
    ['url'=>'hod/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['divider'=>'Account'],
    ['url'=>'hod/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
    ['url'=>'hod/change_password.php','icon'=>'fas fa-lock','label'=>'Change Password'],
];
include '../includes/header.php';
?>
<div class="page-header">
  <div><h2>Attendance Records</h2><p>Review and verify lecturer attendance submissions</p></div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body" style="padding-bottom:10px">
    <form method="GET" class="filter-bar" style="background:none;border:none;padding:0;flex-wrap:wrap;gap:10px">
      <select name="status" class="form-control" style="width:160px">
        <option value="">All Status</option>
        <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
        <option value="verified" <?= $statusFilter==='verified'?'selected':'' ?>>Verified</option>
        <option value="rejected" <?= $statusFilter==='rejected'?'selected':'' ?>>Rejected</option>
      </select>
      <select name="lecturer" class="form-control" style="width:200px">
        <option value="">All Lecturers</option>
        <?php while($l=$lecturers->fetch_assoc()): ?>
        <option value="<?=$l['id']?>" <?=$lecFilter==$l['id']?'selected':''?>><?=clean($l['full_name'])?></option>
        <?php endwhile ?>
      </select>
      <input type="date" name="date_from" class="form-control" value="<?=clean($dateFrom)?>" style="width:160px" placeholder="From">
      <input type="date" name="date_to" class="form-control" value="<?=clean($dateTo)?>" style="width:160px" placeholder="To">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
      <a href="attendance.php" class="btn btn-outline btn-sm">Clear</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th><th>Lecturer</th><th>Course</th><th>Date</th>
            <th>Time</th><th>Duration</th><th>Students</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=0; while ($r = $records->fetch_assoc()): $i++; ?>
          <tr>
            <td class="text-muted"><?=$i?></td>
            <td>
              <div class="fw-600"><?=clean($r['full_name'])?></div>
              <small class="text-muted"><?=clean($r['staff_id']??'')?></small>
            </td>
            <td>
              <div class="fw-600"><?=clean($r['course_code'])?></div>
              <small class="text-muted"><?=clean($r['course_title'])?></small>
            </td>
            <td><?=formatDate($r['attendance_date'],'d M Y')?></td>
            <td><?=formatTime($r['start_time'])?> – <?=formatTime($r['end_time']??'')?></td>
            <td><?=number_format($r['duration_hours'],1)?>h</td>
            <td><?=$r['students_present']?></td>
            <td><?=statusBadge($r['status'])?></td>
            <td>
              <!-- View -->
              <button type="button" class="btn btn-outline btn-sm"
                onclick="viewRecord(<?=htmlspecialchars(json_encode($r))?>)"
                title="View Details"><i class="fas fa-eye"></i></button>
              <?php if ($r['status'] === 'pending'): ?>
              <!-- Verify -->
              <form method="POST" style="display:inline">
                <input type="hidden" name="record_id" value="<?=$r['id']?>">
                <input type="hidden" name="action" value="verify">
                <button type="submit" class="btn btn-success btn-sm" title="Verify" data-confirm="Verify this attendance?">
                  <i class="fas fa-check"></i>
                </button>
              </form>
              <!-- Reject -->
              <button type="button" class="btn btn-danger btn-sm" title="Reject"
                onclick="rejectRecord(<?=$r['id']?>)"><i class="fas fa-times"></i></button>
              <?php endif ?>
            </td>
          </tr>
          <?php endwhile ?>
          <?php if ($i===0): ?>
          <tr><td colspan="9"><div class="empty-state"><div class="empty-icon"><i class="fas fa-clipboard-list"></i></div><h4>No Records Found</h4><p>No attendance records match your filters.</p></div></td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- View Modal -->
<div id="viewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:white;border-radius:14px;padding:28px;max-width:600px;width:100%;max-height:80vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-size:17px;font-weight:700;margin:0">Attendance Details</h3>
      <button onclick="closeModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
    </div>
    <div id="modalContent"></div>
  </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:white;border-radius:14px;padding:28px;max-width:420px;width:100%">
    <h3 style="font-size:17px;font-weight:700;margin-bottom:16px">Reject Attendance</h3>
    <form method="POST">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="record_id" id="rejectId">
      <div class="form-group">
        <label>Reason for Rejection <span class="req">*</span></label>
        <textarea name="reason" class="form-control" rows="3" required placeholder="Enter reason..."></textarea>
      </div>
      <div class="btn-group">
        <button type="submit" class="btn btn-danger">Reject Record</button>
        <button type="button" onclick="closeReject()" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function viewRecord(r) {
  document.getElementById('modalContent').innerHTML = `
    <table style="width:100%;border-collapse:collapse;font-size:13.5px">
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b;width:140px">Lecturer</td><td style="padding:9px 0;font-weight:600">${r.full_name}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Course</td><td style="padding:9px 0;font-weight:600">${r.course_code} — ${r.course_title}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Date</td><td style="padding:9px 0">${r.attendance_date}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Time</td><td style="padding:9px 0">${r.start_time} – ${r.end_time||'—'}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Duration</td><td style="padding:9px 0">${r.duration_hours}h</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Venue</td><td style="padding:9px 0">${r.venue||'—'}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Students</td><td style="padding:9px 0">${r.students_present}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Type</td><td style="padding:9px 0">${r.lecture_type} / ${r.teaching_method}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Topic</td><td style="padding:9px 0">${r.topic_covered||'—'}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Materials</td><td style="padding:9px 0">${r.materials_used||'—'}</td></tr>
      <tr style="border-bottom:1px solid #e2e8f0"><td style="padding:9px 0;color:#64748b">Remarks</td><td style="padding:9px 0">${r.remarks||'—'}</td></tr>
      <tr><td style="padding:9px 0;color:#64748b">Status</td><td style="padding:9px 0;font-weight:600;text-transform:capitalize">${r.status}</td></tr>
      ${r.rejection_reason ? `<tr><td style="padding:9px 0;color:#64748b">Reject Reason</td><td style="padding:9px 0;color:#dc2626">${r.rejection_reason}</td></tr>` : ''}
    </table>`;
  document.getElementById('viewModal').style.display = 'flex';
}

function closeModal() { document.getElementById('viewModal').style.display = 'none'; }
function rejectRecord(id) { document.getElementById('rejectId').value = id; document.getElementById('rejectModal').style.display = 'flex'; }
function closeReject() { document.getElementById('rejectModal').style.display = 'none'; }
</script>
<?php include '../includes/footer.php'; ?>
