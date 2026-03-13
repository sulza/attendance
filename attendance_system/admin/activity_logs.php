<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Activity Logs';
$user = getCurrentUser();
$conn = getDBConnection();

$search = clean($_GET['q'] ?? '');
$where = "WHERE 1=1";
if ($search) $where .= " AND (al.action LIKE '%$search%' OR u.full_name LIKE '%$search%' OR al.ip_address LIKE '%$search%')";

$logs = $conn->query("
    SELECT al.*, u.full_name, u.role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where ORDER BY al.created_at DESC LIMIT 500");
$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Academic Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Course Assignments'],
    ['divider'=>'Reports'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'All Attendance'],
    ['url'=>'admin/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports'],
    ['url'=>'admin/activity_logs.php','icon'=>'fas fa-history','label'=>'Activity Logs','active'=>true],
    ['divider'=>'System'],
    ['url'=>'admin/settings.php','icon'=>'fas fa-cog','label'=>'Settings'],
    ['url'=>'admin/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
];
include '../includes/header.php';
?>
<div class="page-header">
  <div><h2>Activity Logs</h2><p>System-wide activity audit trail</p></div>
</div>

<div class="card mb-3">
  <div class="card-body" style="padding:14px 20px">
    <form method="GET" class="d-flex align-items-center gap-2">
      <input type="text" name="q" class="form-control" placeholder="Search by user, action, IP..." value="<?=clean($search)?>" style="max-width:340px">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
      <?php if ($search): ?><a href="activity_logs.php" class="btn btn-outline btn-sm">Clear</a><?php endif ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Date/Time</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP Address</th></tr></thead>
      <tbody>
        <?php $i=0; while($r=$logs->fetch_assoc()): $i++;
        $actionColor = str_starts_with($r['action'],'LOGIN') ? 'bg-success' : (str_starts_with($r['action'],'FAILED') ? 'bg-danger' : (str_contains($r['action'],'DELETE') ? 'bg-danger' : (str_contains($r['action'],'VERIFY') ? 'bg-success' : 'bg-secondary')));
        ?>
        <tr>
          <td style="white-space:nowrap"><div><?=date('d M Y', strtotime($r['created_at']))?></div><small class="text-muted"><?=date('H:i:s', strtotime($r['created_at']))?></small></td>
          <td><?=clean($r['full_name']??'System')?></td>
          <td><?=$r['role'] ? roleBadge($r['role']) : '<span class="text-muted">—</span>'?></td>
          <td><span class="badge <?=$actionColor?>" style="font-size:11px"><?=clean($r['action'])?></span></td>
          <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=clean($r['description']??'')?></td>
          <td><code style="font-size:12px"><?=clean($r['ip_address']??'')?></code></td>
        </tr>
        <?php endwhile ?>
        <?php if (!$i): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="fas fa-history"></i></div><h4>No Logs Found</h4></div></td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Need roleBadge in database.php - already added
include '../includes/footer.php'; ?>
