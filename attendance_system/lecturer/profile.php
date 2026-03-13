<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('lecturer');
$pageTitle = 'My Profile';
$user = getCurrentUser();
$conn = getDBConnection();
$uid  = (int)$_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = clean($_POST['full_name']      ?? '');
    $phone  = clean($_POST['phone']          ?? '');
    $qual   = clean($_POST['qualification']  ?? '');
    $spec   = clean($_POST['specialization'] ?? '');
    $gender = clean($_POST['gender']         ?? '');

    if (!$name) {
        setFlash('danger', 'Full name is required.');
    } else {
        $conn->query("UPDATE users SET full_name='$name', phone='$phone',
            qualification='$qual', specialization='$spec', gender='$gender'
            WHERE id=$uid");
        $_SESSION['full_name'] = $name;
        setFlash('success', 'Profile updated successfully.');
        logActivity($uid, 'UPDATE_PROFILE', 'Lecturer updated their profile');
    }
    header('Location: profile.php'); exit;
}

// Attendance stats for this lecturer
$sess = getCurrentSession();
$sessId = $sess ? (int)$sess['id'] : 0;

$stats = $conn->query("
    SELECT
        COUNT(*)                              AS total,
        SUM(status = 'verified')              AS verified,
        SUM(status = 'pending')               AS pending,
        SUM(status = 'rejected')              AS rejected,
        COALESCE(SUM(duration_hours), 0)      AS total_hours,
        COALESCE(AVG(students_present), 0)    AS avg_students,
        COUNT(DISTINCT course_id)             AS unique_courses
    FROM attendance_records
    WHERE lecturer_id = $uid
")->fetch_assoc();

$sessionStats = $sessId ? $conn->query("
    SELECT COUNT(*) AS total, COALESCE(SUM(duration_hours),0) AS hrs
    FROM attendance_records
    WHERE lecturer_id=$uid AND session_id=$sessId
")->fetch_assoc() : ['total'=>0,'hrs'=>0];

// Assigned courses
$courses = $conn->query("
    SELECT c.course_code, c.course_title, c.credit_units, c.level,
           d.name AS dept_name,
           (SELECT COUNT(*) FROM attendance_records WHERE lecturer_id=$uid AND course_id=c.id) AS sessions_logged
    FROM course_assignments ca
    JOIN courses c ON ca.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE ca.lecturer_id = $uid
    " . ($sessId ? "AND ca.session_id = $sessId" : "") . "
    ORDER BY c.course_code");

// Recent attendance
$recentAttendance = $conn->query("
    SELECT ar.attendance_date, ar.duration_hours, ar.status, ar.topic_covered,
           c.course_code, c.course_title
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    WHERE ar.lecturer_id = $uid
    ORDER BY ar.attendance_date DESC, ar.created_at DESC
    LIMIT 5");

// Recent activity logs
$recentLogs = $conn->query("
    SELECT * FROM activity_logs WHERE user_id=$uid ORDER BY created_at DESC LIMIT 6");

$conn->close();

$sidebarItems = [
    ['url'=>'lecturer/dashboard.php',       'icon'=>'fas fa-tachometer-alt',    'label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'lecturer/log_attendance.php',  'icon'=>'fas fa-plus-circle',       'label'=>'Log Attendance'],
    ['url'=>'lecturer/my_attendance.php',   'icon'=>'fas fa-clipboard-list',    'label'=>'My Attendance'],
    ['divider'=>'Courses'],
    ['url'=>'lecturer/my_courses.php',      'icon'=>'fas fa-book',              'label'=>'My Courses'],
    ['divider'=>'Account'],
    ['url'=>'lecturer/profile.php',         'icon'=>'fas fa-user',              'label'=>'My Profile', 'active'=>true],
    ['url'=>'lecturer/change_password.php', 'icon'=>'fas fa-lock',              'label'=>'Change Password'],
];
$breadcrumb = [['label' => 'My Profile']];
include '../includes/header.php';
?>

<div style="max-width:960px">

    <!-- Profile hero -->
    <div class="card" style="margin-bottom:20px">
        <div style="background:linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%);border-radius:14px 14px 0 0;padding:32px 28px;position:relative;overflow:hidden">
            <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.08)"></div>
            <div style="position:absolute;bottom:-20px;right:60px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06)"></div>
            <div style="display:flex;align-items:center;gap:20px;position:relative;flex-wrap:wrap">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.2);backdrop-filter:blur(10px);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:#fff;flex-shrink:0">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 style="color:#fff;font-size:22px;font-weight:800;margin:0 0 4px"><?= clean($user['full_name']) ?></h2>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span style="background:rgba(255,255,255,.2);color:#fff;font-size:12px;padding:3px 10px;border-radius:20px;font-weight:600">
                            <i class="fas fa-chalkboard-teacher" style="margin-right:5px"></i>Visiting Lecturer
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
                    <?php if ($user['qualification'] || $user['specialization']): ?>
                    <div style="margin-top:6px;color:rgba(255,255,255,.75);font-size:12px">
                        <?= clean($user['qualification'] ?? '') ?>
                        <?= ($user['qualification'] && $user['specialization']) ? ' &mdash; ' : '' ?>
                        <?= clean($user['specialization'] ?? '') ?>
                    </div>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <!-- Quick info bar -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));border-top:1px solid var(--border)">
            <?php
            $bars = [
                ['Staff ID',        clean($user['staff_id'] ?? '—')],
                ['Phone',           clean($user['phone']    ?? '—')],
                ['Total Sessions',  $stats['total']],
                ['Total Hours',     number_format($stats['total_hours'], 1) . 'h'],
                ['This Session',    $sessionStats['total'] . ' sessions'],
            ];
            foreach ($bars as $idx => [$label, $value]):
            ?>
            <div style="padding:14px 16px;text-align:center<?= $idx < count($bars)-1 ? ';border-right:1px solid var(--border)' : '' ?>">
                <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px"><?= $label ?></div>
                <div style="font-weight:700;font-size:14px"><?= $value ?></div>
            </div>
            <?php endforeach ?>
        </div>
    </div>

    <!-- Attendance stats -->
    <div class="stats-grid" style="margin-bottom:20px">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
            <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Records</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div><div class="stat-value"><?= $stats['verified'] ?></div><div class="stat-label">Verified</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            <div><div class="stat-value"><?= $stats['pending'] ?></div><div class="stat-label">Pending</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-value"><?= number_format($stats['total_hours'], 1) ?>h</div><div class="stat-label">Total Hours</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-book"></i></div>
            <div><div class="stat-value"><?= $stats['unique_courses'] ?></div><div class="stat-label">Courses Taught</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
            <div><div class="stat-value"><?= number_format($stats['avg_students'], 0) ?></div><div class="stat-label">Avg Students</div></div>
        </div>
    </div>

    <div class="grid-2" style="align-items:start;margin-bottom:20px">

        <!-- Edit form -->
        <div class="card" style="margin-bottom:0">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= clean($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Email Address</label>
                        <input type="email" class="form-control" value="<?= clean($user['email']) ?>" disabled>
                        <div class="form-hint">Contact admin to change your email.</div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Phone Number</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= clean($user['phone'] ?? '') ?>"
                               placeholder="+234-800-000-0000">
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
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
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Qualification</label>
                        <input type="text" name="qualification" class="form-control"
                               value="<?= clean($user['qualification'] ?? '') ?>"
                               placeholder="e.g. PhD Computer Science">
                    </div>
                    <div class="form-group" style="margin-bottom:14px">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control"
                               value="<?= clean($user['specialization'] ?? '') ?>"
                               placeholder="e.g. Machine Learning">
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

        <!-- Right column -->
        <div style="display:flex;flex-direction:column;gap:20px">

            <!-- Recent attendance -->
            <div class="card" style="margin-bottom:0">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-clipboard-check"></i> Recent Attendance</span>
                    <a href="my_attendance.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding:0">
                    <?php if ($recentAttendance->num_rows === 0): ?>
                    <div style="padding:24px;text-align:center;color:var(--text-muted)">
                        <i class="fas fa-clipboard-list" style="font-size:24px;opacity:.3;display:block;margin-bottom:6px"></i>
                        <p style="margin:0;font-size:13px">No attendance records yet.</p>
                    </div>
                    <?php else: ?>
                    <ul style="list-style:none;margin:0;padding:0">
                        <?php $first = true; while ($rec = $recentAttendance->fetch_assoc()): ?>
                        <li style="display:flex;gap:12px;padding:12px 18px;<?= $first ? '' : 'border-top:1px solid var(--border)' ?>">
                            <?php $first = false; ?>
                            <div style="flex-shrink:0;width:38px;text-align:center">
                                <div style="font-size:16px;font-weight:800;color:var(--primary);line-height:1"><?= date('d', strtotime($rec['attendance_date'])) ?></div>
                                <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase"><?= date('M', strtotime($rec['attendance_date'])) ?></div>
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:600"><?= clean($rec['course_code']) ?> — <?= clean(mb_substr($rec['course_title'], 0, 30)) ?><?= mb_strlen($rec['course_title']) > 30 ? '…':'' ?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:2px">
                                    <?= $rec['duration_hours'] ? number_format($rec['duration_hours'], 1) . 'h' : '' ?>
                                    <?php if ($rec['topic_covered']): ?>
                                     &bull; <?= clean(mb_substr($rec['topic_covered'], 0, 35)) ?><?= mb_strlen($rec['topic_covered']) > 35 ? '…':'' ?>
                                    <?php endif ?>
                                </div>
                            </div>
                            <div style="flex-shrink:0"><?= statusBadge($rec['status']) ?></div>
                        </li>
                        <?php endwhile ?>
                    </ul>
                    <?php endif ?>
                </div>
            </div>

            <!-- Assigned courses -->
            <div class="card" style="margin-bottom:0">
                <div class="card-header">
                    <span class="card-title"><i class="fas fa-book"></i> Assigned Courses</span>
                    <a href="my_courses.php" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="card-body" style="padding:0">
                    <?php if ($courses->num_rows === 0): ?>
                    <div style="padding:24px;text-align:center;color:var(--text-muted)">
                        <i class="fas fa-book" style="font-size:24px;opacity:.3;display:block;margin-bottom:6px"></i>
                        <p style="margin:0;font-size:13px">No courses assigned for the current session.</p>
                    </div>
                    <?php else: ?>
                    <ul style="list-style:none;margin:0;padding:0">
                        <?php $first = true; while ($c = $courses->fetch_assoc()): ?>
                        <li style="display:flex;align-items:center;gap:12px;padding:12px 18px;<?= $first ? '' : 'border-top:1px solid var(--border)' ?>">
                            <?php $first = false; ?>
                            <div style="width:40px;height:40px;border-radius:9px;background:var(--primary-light);color:var(--primary);display:grid;place-items:center;flex-shrink:0;font-size:14px;font-weight:700">
                                <?= strtoupper(substr($c['course_code'], 0, 2)) ?>
                            </div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:600"><?= clean($c['course_code']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted)"><?= clean(mb_substr($c['course_title'], 0, 35)) ?><?= mb_strlen($c['course_title']) > 35 ? '…':'' ?></div>
                            </div>
                            <div style="text-align:right;flex-shrink:0">
                                <div style="font-size:12px;font-weight:600;color:var(--primary)"><?= $c['sessions_logged'] ?> sessions</div>
                                <div style="font-size:10px;color:var(--text-muted)">Level <?= $c['level'] ?></div>
                            </div>
                        </li>
                        <?php endwhile ?>
                    </ul>
                    <?php endif ?>
                </div>
            </div>

        </div><!-- /right column -->

    </div><!-- /grid -->

    <!-- Account info footer -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-info-circle"></i> Account Information</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
            <?php
            $info = [
                ['Role',          'Visiting Lecturer',                                    'fas fa-chalkboard-teacher', '#0ea5e9'],
                ['Department',    $user['dept_name'] ?? 'Not Assigned',                   'fas fa-building',           '#6366f1'],
                ['Staff ID',      $user['staff_id']  ?? 'Not Assigned',                   'fas fa-id-badge',           '#d97706'],
                ['Member Since',  $user['created_at'] ? formatDate($user['created_at'], 'd M Y') : 'N/A', 'fas fa-calendar', '#3b82f6'],
                ['Last Login',    $user['last_login']  ? formatDate($user['last_login'], 'd M Y, H:i') : 'Never', 'fas fa-clock', '#10b981'],
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