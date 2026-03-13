<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'System Settings';
$user = getCurrentUser();
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = ['institution_name','institution_address','system_email','require_hod_approval','max_lecture_hours','session_timeout','allow_backdating'];
    foreach ($keys as $k) {
        $v = clean($_POST[$k] ?? '');
        $esc = $conn->real_escape_string($v);
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES('$k','$esc') ON DUPLICATE KEY UPDATE setting_value='$esc'");
    }
    logActivity((int)$_SESSION['user_id'], 'UPDATE_SETTINGS', 'System settings updated');
    setFlash('Settings saved successfully.', 'success');
    header('Location: settings.php'); exit;
}

// Load settings
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($r=$settingsRes->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];
$conn->close();

function sv(array $s, string $k, string $d=''): string { return htmlspecialchars($s[$k] ?? $d); }

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
    ['url'=>'admin/activity_logs.php','icon'=>'fas fa-history','label'=>'Activity Logs'],
    ['divider'=>'System'],
    ['url'=>'admin/settings.php','icon'=>'fas fa-cog','label'=>'Settings','active'=>true],
    ['url'=>'admin/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
];
include '../includes/header.php';
?>
<div class="page-header">
  <div><h2>System Settings</h2><p>Configure application-wide settings</p></div>
</div>

<form method="POST">
<div class="grid-2">
  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-university"></i> Institution Settings</span></div>
    <div class="card-body">
      <div class="form-group mb-3">
        <label>Institution Name</label>
        <input type="text" name="institution_name" class="form-control" value="<?=sv($settings,'institution_name','University')?>">
      </div>
      <div class="form-group mb-3">
        <label>Institution Address</label>
        <textarea name="institution_address" class="form-control" rows="2"><?=sv($settings,'institution_address')?></textarea>
      </div>
      <div class="form-group mb-3">
        <label>System Email</label>
        <input type="email" name="system_email" class="form-control" value="<?=sv($settings,'system_email')?>">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-sliders-h"></i> Attendance Rules</span></div>
    <div class="card-body">
      <div class="form-group mb-3">
        <label>Require HOD Verification</label>
        <select name="require_hod_approval" class="form-control">
          <option value="1" <?=($settings['require_hod_approval']??'1')==='1'?'selected':''?>>Yes – Required</option>
          <option value="0" <?=($settings['require_hod_approval']??'1')==='0'?'selected':''?>>No – Auto-approved</option>
        </select>
      </div>
      <div class="form-group mb-3">
        <label>Max Lecture Hours (per session)</label>
        <input type="number" name="max_lecture_hours" class="form-control" min="1" max="12" value="<?=sv($settings,'max_lecture_hours','4')?>">
      </div>
      <div class="form-group mb-3">
        <label>Allow Backdating (days)</label>
        <input type="number" name="allow_backdating" class="form-control" min="0" max="30" value="<?=sv($settings,'allow_backdating','7')?>">
        <div class="form-hint">Lecturers can log attendance up to this many days in the past.</div>
      </div>
      <div class="form-group mb-3">
        <label>Session Timeout (seconds)</label>
        <input type="number" name="session_timeout" class="form-control" min="300" value="<?=sv($settings,'session_timeout','3600')?>">
      </div>
    </div>
  </div>
</div>

<div class="btn-group mt-3">
  <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
</div>
</form>
<?php include '../includes/footer.php'; ?>
