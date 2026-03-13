<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Manage HODs';
$conn = getDBConnection();

// Handle actions
$action = $_GET['action'] ?? '';
$id     = sanitizeInt($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = clean($_POST['full_name']    ?? '');
    $email  = clean($_POST['email']        ?? '');
    $phone  = clean($_POST['phone']        ?? '');
    $deptId = sanitizeInt($_POST['department_id'] ?? 0);
    $staffId = clean($_POST['staff_id']   ?? '');
    $gender  = clean($_POST['gender']     ?? '');
    $status  = clean($_POST['status']     ?? 'active');
    $editId  = sanitizeInt($_POST['edit_id'] ?? 0);

    if ($editId) {
        $conn->query("UPDATE users SET full_name='$name', email='$email', phone='$phone',
            department_id=$deptId, staff_id='$staffId', gender='$gender', status='$status'
            WHERE id=$editId AND role='hod'");
        setFlash('success', 'HOD updated successfully.');
        logActivity((int)$_SESSION['user_id'], 'UPDATE_HOD', "Updated HOD ID: $editId");
    } else {
        $password = password_hash($_POST['password'] ?? 'Pass@1234', PASSWORD_BCRYPT, ['cost' => 12]);
        $check = $conn->query("SELECT id FROM users WHERE email='$email'")->fetch_assoc();
        if ($check) {
            setFlash('danger', 'An account with this email already exists.');
        } else {
            $conn->query("INSERT INTO users (full_name, email, phone, password, role, department_id, staff_id, gender, status)
                VALUES ('$name', '$email', '$phone', '$password', 'hod', $deptId, '$staffId', '$gender', '$status')");
            setFlash('success', 'HOD added successfully.');
            logActivity((int)$_SESSION['user_id'], 'ADD_HOD', "Added HOD: $name");
        }
    }
    header('Location: hods.php'); exit;
}

if ($action === 'delete' && $id) {
    $conn->query("UPDATE users SET status='inactive' WHERE id=$id AND role='hod'");
    setFlash('success', 'HOD deactivated successfully.');
    logActivity((int)$_SESSION['user_id'], 'DEACTIVATE_HOD', "Deactivated HOD ID: $id");
    header('Location: hods.php'); exit;
}

if ($action === 'activate' && $id) {
    $conn->query("UPDATE users SET status='active' WHERE id=$id AND role='hod'");
    setFlash('success', 'HOD activated successfully.');
    header('Location: hods.php'); exit;
}

$editUser = null;
if ($action === 'edit' && $id) {
    $editUser = $conn->query("SELECT * FROM users WHERE id=$id AND role='hod'")->fetch_assoc();
}

// Filters
$search        = clean($_GET['search'] ?? '');
$dept_filter   = sanitizeInt($_GET['dept'] ?? 0);
$status_filter = clean($_GET['status'] ?? '');

$where = "WHERE u.role = 'hod'";
if ($search)        $where .= " AND (u.full_name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.staff_id LIKE '%$search%')";
if ($dept_filter)   $where .= " AND u.department_id = $dept_filter";
if ($status_filter) $where .= " AND u.status = '$status_filter'";

$hods = $conn->query("
    SELECT u.*, d.name as dept_name,
        (SELECT COUNT(DISTINCT ar.lecturer_id)
            FROM attendance_records ar
            JOIN users l ON ar.lecturer_id = l.id
            WHERE l.department_id = u.department_id) as dept_lecturers,
        (SELECT COUNT(*)
            FROM attendance_records ar
            JOIN courses c ON ar.course_id = c.id
            WHERE c.department_id = u.department_id AND ar.status = 'pending') as pending_verifications,
        (SELECT COUNT(*)
            FROM attendance_records ar
            JOIN courses c ON ar.course_id = c.id
            WHERE c.department_id = u.department_id AND ar.verified_by = u.id) as total_verified
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    $where
    ORDER BY u.full_name");

$departments = getDepartments();

// Count stats
$totalHods    = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='hod'")->fetch_assoc()['c'];
$activeHods   = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='hod' AND status='active'")->fetch_assoc()['c'];
$totalPending = $conn->query("SELECT COUNT(*) as c FROM attendance_records WHERE status='pending'")->fetch_assoc()['c'];

$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php',  'icon'=>'fas fa-tachometer-alt',      'label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php',  'icon'=>'fas fa-chalkboard-teacher',  'label'=>'Lecturers'],
    ['url'=>'admin/hods.php',       'icon'=>'fas fa-user-tie',             'label'=>'HODs', 'active'=>true],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building',             'label'=>'Departments'],
    ['url'=>'admin/courses.php',    'icon'=>'fas fa-book',                 'label'=>'Courses'],
    ['url'=>'admin/sessions.php',   'icon'=>'fas fa-calendar-alt',         'label'=>'Academic Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link',                 'label'=>'Course Assignments'],
    ['divider'=>'Attendance'],
    ['url'=>'admin/attendance.php', 'icon'=>'fas fa-clipboard-check',      'label'=>'All Attendance'],
    ['url'=>'admin/reports.php',    'icon'=>'fas fa-chart-bar',            'label'=>'Reports & Analytics'],
    ['divider'=>'System'],
    ['url'=>'admin/activity_logs.php','icon'=>'fas fa-history',            'label'=>'Activity Logs'],
    ['url'=>'admin/settings.php',   'icon'=>'fas fa-cog',                  'label'=>'Settings'],
    ['url'=>'admin/profile.php',    'icon'=>'fas fa-user',                 'label'=>'My Profile'],
];
$breadcrumb = [['label' => 'HODs']];
include '../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
        <div>
            <div class="stat-value"><?= $totalHods ?></div>
            <div class="stat-label">Total HODs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value"><?= $activeHods ?></div>
            <div class="stat-label">Active HODs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $totalPending ?></div>
            <div class="stat-label">Pending Verifications</div>
        </div>
    </div>
</div>

<!-- Header -->
<div class="section-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <span class="section-title" style="font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px">
        <i class="fas fa-user-tie" style="color:var(--primary)"></i> Heads of Department
    </span>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
        <i class="fas fa-plus"></i> Add HOD
    </button>
</div>

<!-- Filters -->
<div class="filter-bar" style="margin-bottom:16px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:none;border:none;padding:0">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, ID..."
               value="<?= clean($search) ?>" style="width:220px">
        <select name="dept" class="form-control" style="width:180px">
            <option value="">All Departments</option>
            <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
            <option value="<?= $d['id'] ?>" <?= $dept_filter == $d['id'] ? 'selected' : '' ?>>
                <?= clean($d['name']) ?>
            </option>
            <?php endwhile ?>
        </select>
        <select name="status" class="form-control" style="width:140px">
            <option value="">All Status</option>
            <option value="active"    <?= $status_filter === 'active'    ? 'selected' : '' ?>>Active</option>
            <option value="inactive"  <?= $status_filter === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="hods.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
    </form>
</div>

<!-- Table -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table" id="hodTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Staff ID</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Pending</th>
                    <th>Verified</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = $hods->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="background:#fef3c7;color:#d97706">
                                <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-600"><?= clean($r['full_name']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= clean($r['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= clean($r['staff_id'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['dept_name']): ?>
                            <span class="badge badge-primary"><?= clean($r['dept_name']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif ?>
                    </td>
                    <td><?= clean($r['phone'] ?? '—') ?></td>
                    <td>
                        <?php if ($r['pending_verifications'] > 0): ?>
                            <span class="badge badge-warning"><?= $r['pending_verifications'] ?></span>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="badge badge-success"><?= $r['total_verified'] ?></span>
                    </td>
                    <td>
                        <span style="font-size:12px;color:var(--text-muted)">
                            <?= $r['last_login'] ? formatDate($r['last_login'], 'd M Y') : 'Never' ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $r['status'] === 'active' ? 'badge-success' : ($r['status'] === 'suspended' ? 'badge-danger' : 'badge-secondary') ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:5px">
                            <!-- Edit -->
                            <a href="hods.php?action=edit&id=<?= $r['id'] ?>"
                               class="btn btn-outline btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <!-- Deactivate / Activate -->
                            <?php if ($r['status'] === 'active'): ?>
                            <a href="hods.php?action=delete&id=<?= $r['id'] ?>"
                               class="btn btn-danger btn-sm" title="Deactivate"
                               onclick="return confirm('Deactivate this HOD?')">
                                <i class="fas fa-ban"></i>
                            </a>
                            <?php else: ?>
                            <a href="hods.php?action=activate&id=<?= $r['id'] ?>"
                               class="btn btn-success btn-sm" title="Activate"
                               onclick="return confirm('Activate this HOD?')">
                                <i class="fas fa-check"></i>
                            </a>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile ?>

                <?php if ($i === 1): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-user-tie"></i></div>
                            <h4>No HODs Found</h4>
                            <p>No heads of department match your search criteria.</p>
                            <button class="btn btn-primary btn-sm"
                                onclick="document.getElementById('addModal').style.display='flex'">
                                <i class="fas fa-plus"></i> Add First HOD
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     ADD HOD MODAL
     ============================================================ -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:14px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e2e8f0">
            <h3 style="font-size:17px;font-weight:700;margin:0">
                <i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Add New HOD
            </h3>
            <button onclick="document.getElementById('addModal').style.display='none'"
                    style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <form method="POST">
            <div style="padding:24px">
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Dr. John Smith">
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="hod@university.edu">
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+234-800-000-0000">
                    </div>
                    <div class="form-group">
                        <label>Staff ID</label>
                        <input type="text" name="staff_id" class="form-control" placeholder="e.g. HOD001">
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Department <span class="req">*</span></label>
                        <select name="department_id" class="form-control" required>
                            <option value="">-- Select Department --</option>
                            <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                            <option value="<?= $d['id'] ?>"><?= clean($d['name']) ?></option>
                            <?php endwhile ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Password <span class="req">*</span></label>
                        <input type="password" name="password" class="form-control" value="Pass@1234">
                        <div class="form-hint">Default: Pass@1234 — advise HOD to change on first login.</div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 14px 14px">
                <button type="button" class="btn btn-outline"
                        onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save HOD
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     EDIT HOD MODAL (opens automatically when ?action=edit)
     ============================================================ -->
<?php if ($action === 'edit' && $editUser): ?>
<div id="editModal" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:14px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:20px 24px;border-bottom:1px solid #e2e8f0">
            <h3 style="font-size:17px;font-weight:700;margin:0">
                <i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>Edit HOD
            </h3>
            <a href="hods.php" style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;text-decoration:none;line-height:1">&times;</a>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>">
            <div style="padding:24px">
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control" required
                               value="<?= clean($editUser['full_name']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= clean($editUser['email']) ?>">
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= clean($editUser['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Staff ID</label>
                        <input type="text" name="staff_id" class="form-control"
                               value="<?= clean($editUser['staff_id'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-grid-2 mb-3">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">-- Select Department --</option>
                            <?php $departments->data_seek(0); while ($d = $departments->fetch_assoc()): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= $editUser['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                <?= clean($d['name']) ?>
                            </option>
                            <?php endwhile ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach (['male', 'female', 'other'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($editUser['gender'] ?? '') === $g ? 'selected' : '' ?>>
                                <?= ucfirst($g) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <?php foreach (['active', 'inactive', 'suspended'] as $s): ?>
                        <option value="<?= $s ?>" <?= $editUser['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="alert alert-info" style="font-size:13px">
                    <i class="fas fa-info-circle"></i>
                    <span>To reset this HOD's password, use the <strong>Change Password</strong> option from their profile.</span>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;border-radius:0 0 14px 14px">
                <a href="hods.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update HOD
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif ?>

<?php include '../includes/footer.php'; ?>