<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('hod');
$pageTitle = 'My Profile';
$user = getCurrentUser();
$conn = getDBConnection();
$uid  = (int)$_SESSION['user_id'];
$deptId = (int)($user['department_id'] ?? 0);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = clean($_POST['full_name']  ?? '');
    $phone  = clean($_POST['phone']      ?? '');
    $gender = clean($_POST['gender']     ?? '');

    if (!$name) {
        setFlash('danger', 'Full name is required.');
    } else {
        $conn->query("UPDATE users SET full_name='$name', phone='$phone', gender='$gender' WHERE id=$uid");
        $_SESSION['full_name'] = $name;
        setFlash('success', 'Profile updated successfully.');
        logActivity($uid, 'UPDATE_PROFILE', 'HOD updated their profile');
    }
    header('Location: profile.php'); exit;
}

// Dept stats for this HOD
$deptStats = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE department_id=$deptId AND role='lecturer' AND status='active') AS lecturers,
        (SELECT COUNT(*) FROM courses WHERE department_id=$deptId AND status='active') AS courses,
        (SELECT COUNT(*) FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE c.department_id=$deptId AND ar.status='pending') AS pending,
        (SELECT COUNT(*) FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE c.department_id=$deptId AND ar.verified_by=$uid) AS verified_by_me,
        (SELECT COUNT(*) FROM attendance_records ar JOIN courses c ON ar.course_id=c.id WHERE c.department_id=$deptId) AS total_records
")->fetch_assoc();

// Recent activity
$recentLogs = $conn->query("
    SELECT * FROM activity_logs WHERE user_id=$uid ORDER BY created_at DESC LIMIT 8");

$loginCount = $conn->query("
    SELECT COUNT(*) AS c FROM activity_logs WHERE user_id=$uid AND action='LOGIN'")->fetch_assoc()['c'];

$conn->close();

$sidebarItems = [
    ['url'=>'hod/dashboard.php',      'icon'=>'fas fa-tachometer-alt',    'label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'hod/attendance.php',     'icon'=>'fas fa-clipboard-check',   'label'=>'Attendance Records'],
    ['url'=>'hod/reports.php',        'icon'=>'fas fa-chart-bar',          'label'=>'Reports & Downloads'],
    ['divider'=>'Department'],
    ['url'=>'hod/lecturers.php',      'icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['divider'=>'Account'],
    ['url'=>'hod/profile.php',        'icon'=>'fas fa-user',               'label'=>'My Profile', 'active'=>true],
    ['url'=>'hod/change_password.php','icon'=>'fas fa-lock',               'label'=>'Change Password'],
];
$breadcrumb = [['label' => 'My Profile']];
include '../includes/header.php';
?>

<div style="max-width:900px">

    <!-- Profile hero -->
    <div class="card" style="margin-bottom:20px">
        <div style="background:linear-gradient(135deg,#d97706 0%,#f59e0b 100%);border-radius:14px 14px 0 0;padding:32px 28px;position:relative;overflow:hidden">
            <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.08)"></div>
            <div style="position:absolute;bottom:-20px;right:60px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06)"></div>
            <div style="display:flex;align-items:center;gap:20px;position:relative">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.2);backdrop-filter:blur(10px);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;flex-shrink:0">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 style="color:#fff;font-size:22px;font-weight:800;margin:0 0 4px"><?= clean($user['full_name']) ?></h2>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span style="background:rgba(255,255,255,.2);color:#fff;font-size:12px;padding:3px 10px;border-radius:20px;font-weight:600">
                            <i class="fas fa-user-tie" style="margin-right:5px"></i>Head of Department
                        </span>
                        <span style="color:rgba(255,255,255,.85);font-size:13px">
                            <i class="fas fa-envelope" style="margin-right:5px"></i><?= clean($user['email']) ?>
                        </span>
                        <?php if ($user['dept_name']): ?>
                        <span style="color:rgba(255,255,255,.85);font-size:13px">
                            <i class="fas fa-building" style="margin-right:5px"></i><?= clean($user['dept_name']) ?>
                        </span>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick stats bar -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));border-top:1px solid var(--border)">
            <?php
            $bars = [
                ['Staff ID',     clean($user['staff_id'] ?? '—')],
                ['Phone',        clean($user['phone']    ?? '—')],
                ['Total Logins', $loginCount],
                ['Last Login',   $user['last_login'] ? formatDate($user['last_login'], 'd M Y') : 'Never'],
                ['Status',       '<span class="badge badge-success">' . ucfirst($user['status'] ?? 'active') . '</span>'],
            ];
            foreach ($bars as $idx => [$label, $value]):
            ?>
            <div style="padding:14px 18px;text-align:center<?= $idx < count($bars)-1 ? ';border-right:1px solid var(--border)' : '' ?>">
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px"><?= $label ?></div>
                <div style="font-weight:700;font-size:14px"><?= $value ?></div>
            </div>
            <?php endforeach ?>
        </div>
    </div>

    <!-- Department stats -->
    <div class="stats-grid" style="margin-bottom:20px">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-chalkboard-teacher"></i></div>
            <div><div class="stat-value"><?= $deptStats['lecturers'] ?></div><div class="stat-label">Active Lecturers</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-book"></i></div>
            <div><div class="stat-value"><?= $deptStats['courses'] ?></div><div class="stat-label">Active Courses</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            <div><div class="stat-value"><?= $deptStats['pending'] ?></div><div class="stat-label">Pending Verifications</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div><div class="stat-value"><?= $deptStats['verified_by_me'] ?></div><div class="stat-label">Verified by Me</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-clipboard-list"></i></div>
            <div><div class="stat-value"><?= $deptStats['total_records'] ?></div><div class="stat-label">Dept Records</div></div>
        </div>
    </div>

    <div class="grid-2" style="align-items:start">

        <!-- Edit form -->
        <div class="card" style="margin-bottom:0">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group" style="margin-bottom:16px">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= clean($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:16px">
                        <label>Email Address</label>
                        <input type="email" class="form-control"
                               value="<?= clean($user['email']) ?>" disabled>
                        <div class="form-hint">Contact admin to change your email.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:16px">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= clean($user['phone'] ?? '') ?>"
                               placeholder="+234-800-000-0000">
                    </div>
                    <div class="form-group" style="margin-bottom:16px">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach (['male','female','other'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($user['gender'] ?? '') === $g ? 'selected':'' ?>>
                                <?= ucfirst($g) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:16px">
                        <label>Staff ID</label>
                        <input type="text" class="form-control"
                               value="<?= clean($user['staff_id'] ?? '') ?>" disabled>
                        <div class="form-hint">Managed by admin.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:20px">
                        <label>Department</label>
                        <input type="text" class="form-control"
                               value="<?= clean($user['dept_name'] ?? 'Not Assigned') ?>" disabled>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="change_password.php" class="btn btn-outline">
                            <i class="fas fa-lock"></i> Change Password
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="card" style="margin-bottom:0">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-history"></i> Recent Activity</span>
            </div>
            <div class="card-body" style="padding:0">
                <?php if ($recentLogs->num_rows === 0): ?>
                <div style="padding:30px;text-align:center;color:var(--text-muted)">
                    <i class="fas fa-history" style="font-size:28px;margin-bottom:8px;opacity:.3;display:block"></i>
                    <p style="margin:0;font-size:13px">No recent activity found.</p>
                </div>
                <?php else: ?>
                <ul style="list-style:none;margin:0;padding:0">
                    <?php $first = true; while ($log = $recentLogs->fetch_assoc()): ?>
                    <li style="display:flex;gap:12px;padding:14px 20px;<?= $first ? '' : 'border-top:1px solid var(--border)' ?>">
                        <?php
                        $first = false;
                        $iconMap = [
                            'LOGIN'           => ['fas fa-sign-in-alt',    '#6366f1','#eef2ff'],
                            'LOGOUT'          => ['fas fa-sign-out-alt',   '#64748b','#f1f5f9'],
                            'UPDATE_PROFILE'  => ['fas fa-user-edit',      '#10b981','#ecfdf5'],
                            'VERIFY_ATTENDANCE'  => ['fas fa-check-circle','#10b981','#ecfdf5'],
                            'REJECT_ATTENDANCE'  => ['fas fa-times-circle','#ef4444','#fef2f2'],
                            'CHANGE_PASSWORD' => ['fas fa-lock',           '#d97706','#fef3c7'],
                        ];
                        $action = strtoupper($log['action'] ?? '');
                        [$icon, $color, $bg] = $iconMap[$action] ?? ['fas fa-circle','#64748b','#f1f5f9'];
                        ?>
                        <div style="width:34px;height:34px;border-radius:8px;background:<?= $bg ?>;color:<?= $color ?>;display:grid;place-items:center;flex-shrink:0;font-size:13px">
                            <i class="<?= $icon ?>"></i>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:13px;font-weight:600;color:var(--text)">
                                <?= clean(ucwords(strtolower(str_replace('_', ' ', $log['action'])))) ?>
                            </div>
                            <?php if ($log['description']): ?>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= clean(mb_substr($log['description'], 0, 55)) ?><?= mb_strlen($log['description']) > 55 ? '…':'' ?>
                            </div>
                            <?php endif ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;flex-shrink:0;text-align:right">
                            <?= formatDate($log['created_at'], 'd M') ?><br>
                            <?= date('H:i', strtotime($log['created_at'])) ?>
                        </div>
                    </li>
                    <?php endwhile ?>
                </ul>
                <?php endif ?>
            </div>
        </div>

    </div><!-- /grid -->

    <!-- Account info footer -->
    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-info-circle"></i> Account Information</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <?php
            $info = [
                ['Role',          'Head of Department',                                    'fas fa-user-tie',    '#d97706'],
                ['Department',    $user['dept_name'] ?? 'Not Assigned',                    'fas fa-building',    '#6366f1'],
                ['Member Since',  $user['created_at'] ? formatDate($user['created_at'], 'd M Y') : 'N/A', 'fas fa-calendar', '#3b82f6'],
                ['Last Login',    $user['last_login']  ? formatDate($user['last_login'],  'd M Y, H:i') : 'Never', 'fas fa-clock', '#10b981'],
            ];
            foreach ($info as $idx => [$label, $value, $icon, $color]):
            ?>
            <div style="padding:16px 20px<?= $idx < count($info)-1 ? ';border-right:1px solid var(--border)' : '' ?>">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:36px;height:36px;border-radius:8px;background:<?= $color ?>18;color:<?= $color ?>;display:grid;place-items:center;flex-shrink:0">
                        <i class="<?= $icon ?>"></i>
                    </div>
                    <div>
                        <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px"><?= $label ?></div>
                        <div style="font-size:13px;font-weight:600;color:var(--text)"><?= clean($value) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>