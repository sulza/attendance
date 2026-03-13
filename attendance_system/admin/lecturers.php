<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Manage Lecturers';
$conn = getDBConnection();

// Handle actions
$action = $_GET['action'] ?? '';
$id = sanitizeInt($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = clean($_POST['full_name'] ?? '');
    $email  = clean($_POST['email'] ?? '');
    $phone  = clean($_POST['phone'] ?? '');
    $deptId = sanitizeInt($_POST['department_id'] ?? 0);
    $staffId = clean($_POST['staff_id'] ?? '');
    $gender = clean($_POST['gender'] ?? '');
    $qual   = clean($_POST['qualification'] ?? '');
    $spec   = clean($_POST['specialization'] ?? '');
    $status = clean($_POST['status'] ?? 'active');
    $editId = sanitizeInt($_POST['edit_id'] ?? 0);

    if ($editId) {
        $conn->query("UPDATE users SET full_name='$name',email='$email',phone='$phone',department_id=$deptId,
            staff_id='$staffId',gender='$gender',qualification='$qual',specialization='$spec',status='$status'
            WHERE id=$editId AND role='lecturer'");
        setFlash('success', 'Lecturer updated successfully.');
        logActivity($_SESSION['user_id'], 'UPDATE_LECTURER', "Updated lecturer ID: $editId");
    } else {
        $password = password_hash($_POST['password'] ?? 'Pass@1234', PASSWORD_BCRYPT, ['cost'=>12]);
        $check = $conn->query("SELECT id FROM users WHERE email='$email'")->fetch_assoc();
        if ($check) {
            setFlash('danger', 'Email already exists.');
        } else {
            $conn->query("INSERT INTO users (full_name,email,phone,password,role,department_id,staff_id,gender,qualification,specialization,status)
                VALUES ('$name','$email','$phone','$password','lecturer',$deptId,'$staffId','$gender','$qual','$spec','$status')");
            setFlash('success', 'Lecturer added successfully.');
            logActivity($_SESSION['user_id'], 'ADD_LECTURER', "Added: $name");
        }
    }
    header('Location: lecturers.php'); exit;
}

if ($action === 'delete' && $id) {
    $conn->query("UPDATE users SET status='inactive' WHERE id=$id AND role='lecturer'");
    setFlash('success', 'Lecturer deactivated.');
    logActivity($_SESSION['user_id'], 'DEACTIVATE_LECTURER', "Deactivated ID: $id");
    header('Location: lecturers.php'); exit;
}

$editUser = null;
if ($action === 'edit' && $id) {
    $editUser = $conn->query("SELECT * FROM users WHERE id=$id AND role='lecturer'")->fetch_assoc();
}

// Filters
$search = clean($_GET['search'] ?? '');
$dept_filter = sanitizeInt($_GET['dept'] ?? 0);
$status_filter = clean($_GET['status'] ?? '');
$where = "WHERE u.role = 'lecturer'";
if ($search) $where .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.staff_id LIKE '%$search%')";
if ($dept_filter) $where .= " AND u.department_id = $dept_filter";
if ($status_filter) $where .= " AND u.status = '$status_filter'";

$lecturers = $conn->query("SELECT u.*, d.name as dept_name,
    (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id = u.id) as total_sessions,
    (SELECT COALESCE(SUM(duration_hours),0) FROM attendance_records WHERE lecturer_id = u.id AND status='verified') as total_hours
    FROM users u LEFT JOIN departments d ON u.department_id = d.id
    $where ORDER BY u.full_name");

$departments = getDepartments();
$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers','active'=>true],
    ['url'=>'admin/hods.php','icon'=>'fas fa-user-tie','label'=>'HODs'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Academic Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Course Assignments'],
    ['divider'=>'Attendance'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'All Attendance'],
    ['url'=>'admin/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports & Analytics'],
    ['divider'=>'System'],
    ['url'=>'admin/activity_logs.php','icon'=>'fas fa-history','label'=>'Activity Logs'],
    ['url'=>'admin/settings.php','icon'=>'fas fa-cog','label'=>'Settings'],
    ['url'=>'admin/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
];
$breadcrumb = [['label' => 'Lecturers']];
include '../includes/header.php';
?>

<div class="section-header">
  <span class="section-title"><i class="fas fa-chalkboard-teacher" style="color:var(--primary)"></i> Visiting Lecturers</span>
  <button class="btn btn-primary" data-modal="addModal"><i class="fas fa-plus"></i> Add Lecturer</button>
</div>

<!-- Filters -->
<div class="filter-bar mb-2">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" class="form-control search-input" placeholder="Search name, email, ID..." value="<?= clean($_GET['search'] ?? '') ?>">
    <select name="dept" class="form-control">
      <option value="">All Departments</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= $d['id'] ?>" <?= ($dept_filter == $d['id']) ? 'selected' : '' ?>><?= clean($d['name']) ?></option>
      <?php endforeach ?>
    </select>
    <select name="status" class="form-control">
      <option value="">All Status</option>
      <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
    <a href="lecturers.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
  </form>
  <button onclick="exportTableCSV('lecTable','lecturers')" class="btn btn-outline btn-sm" style="margin-left:auto"><i class="fas fa-download"></i> CSV</button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table searchable-table" id="lecTable">
      <thead><tr>
        <th>#</th><th>Name</th><th>Staff ID</th><th>Department</th><th>Qualification</th><th>Sessions</th><th>Hours</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php $i = 1; while ($r = $lecturers->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td>
            <div class="d-flex align-center gap-2">
              <div class="avatar"><?= strtoupper(substr($r['full_name'],0,1)) ?></div>
              <div>
                <div class="fw-bold"><?= clean($r['full_name']) ?></div>
                <div class="text-muted" style="font-size:11px"><?= clean($r['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= clean($r['staff_id'] ?? 'N/A') ?></td>
          <td><?= clean($r['dept_name'] ?? 'N/A') ?></td>
          <td><?= clean($r['qualification'] ?? 'N/A') ?><br><small class="text-muted"><?= clean($r['specialization'] ?? '') ?></small></td>
          <td><?= $r['total_sessions'] ?></td>
          <td><?= number_format($r['total_hours'], 1) ?>h</td>
          <td><span class="badge <?= badgeStatus($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td>
            <div class="btn-group">
              <a href="lecturers.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a>
              <a href="view_lecturer.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i></a>
              <a href="lecturers.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Deactivate this lecturer?"><i class="fas fa-ban"></i></a>
            </div>
          </td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal" <?= ($action === 'edit' && $editUser) ? '' : '' ?>>
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-user-plus"></i> Add Visiting Lecturer</span>
      <button class="modal-close"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Full Name <span class="req">*</span></label><input name="full_name" class="form-control" required></div>
          <div class="form-group"><label>Email <span class="req">*</span></label><input name="email" type="email" class="form-control" required></div>
          <div class="form-group"><label>Phone</label><input name="phone" class="form-control"></div>
          <div class="form-group"><label>Staff ID</label><input name="staff_id" class="form-control"></div>
          <div class="form-group"><label>Department</label>
            <select name="department_id" class="form-control">
              <option value="0">Select...</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>"><?= clean($d['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Gender</label>
            <select name="gender" class="form-control"><option value="">Select...</option><option>male</option><option>female</option><option>other</option></select>
          </div>
          <div class="form-group"><label>Qualification</label><input name="qualification" class="form-control" placeholder="e.g. PhD Computer Science"></div>
          <div class="form-group"><label>Specialization</label><input name="specialization" class="form-control"></div>
          <div class="form-group"><label>Password <span class="req">*</span></label>
            <div class="input-icon"><input type="password" name="password" class="form-control" value="Pass@1234"><button type="button" class="password-toggle"><i class="fas fa-eye"></i></button></div>
            <div class="form-hint">Default: Pass@1234</div>
          </div>
          <div class="form-group"><label>Status</label>
            <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Lecturer</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($action === 'edit' && $editUser): ?>
<div class="modal-overlay open" id="editModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i class="fas fa-user-edit"></i> Edit Lecturer</span>
      <a href="lecturers.php" class="modal-close"><i class="fas fa-times"></i></a>
    </div>
    <form method="POST">
      <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Full Name</label><input name="full_name" class="form-control" value="<?= clean($editUser['full_name']) ?>" required></div>
          <div class="form-group"><label>Email</label><input name="email" type="email" class="form-control" value="<?= clean($editUser['email']) ?>" required></div>
          <div class="form-group"><label>Phone</label><input name="phone" class="form-control" value="<?= clean($editUser['phone'] ?? '') ?>"></div>
          <div class="form-group"><label>Staff ID</label><input name="staff_id" class="form-control" value="<?= clean($editUser['staff_id'] ?? '') ?>"></div>
          <div class="form-group"><label>Department</label>
            <select name="department_id" class="form-control">
              <option value="0">Select...</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $editUser['department_id'] == $d['id'] ? 'selected' : '' ?>><?= clean($d['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Gender</label>
            <select name="gender" class="form-control">
              <?php foreach (['male','female','other'] as $g): ?>
                <option <?= $editUser['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Qualification</label><input name="qualification" class="form-control" value="<?= clean($editUser['qualification'] ?? '') ?>"></div>
          <div class="form-group"><label>Specialization</label><input name="specialization" class="form-control" value="<?= clean($editUser['specialization'] ?? '') ?>"></div>
          <div class="form-group"><label>Status</label>
            <select name="status" class="form-control">
              <?php foreach (['active','inactive','suspended'] as $s): ?>
                <option <?= $editUser['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="lecturers.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif ?>

<?php include '../includes/footer.php'; ?>
