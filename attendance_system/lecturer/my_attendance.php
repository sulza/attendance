<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('lecturer');
$pageTitle = 'My Attendance Records';
$conn = getDBConnection();
$uid  = (int)$_SESSION['user_id'];

// ── Filters ───────────────────────────────────────────────────────────────────
$s_course = sanitizeInt($_GET['course'] ?? 0);
$s_status = clean($_GET['status'] ?? '');
$s_from   = clean($_GET['from']   ?? date('Y-m-01'));
$s_to     = clean($_GET['to']     ?? date('Y-m-d'));

$where = "WHERE ar.lecturer_id = $uid";
if ($s_course) $where .= " AND ar.course_id = $s_course";
if ($s_status) $where .= " AND ar.status = '$s_status'";
if ($s_from)   $where .= " AND ar.attendance_date >= '$s_from'";
if ($s_to)     $where .= " AND ar.attendance_date <= '$s_to'";

// ── CSV Download ──────────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $data = $conn->query("
        SELECT ar.attendance_date AS 'Date',
               c.course_code      AS 'Course Code',
               c.course_title     AS 'Course Title',
               ar.start_time      AS 'Start',
               ar.end_time        AS 'End',
               ar.duration_hours  AS 'Hours',
               ar.students_present AS 'Students',
               ar.lecture_type    AS 'Type',
               ar.teaching_method AS 'Method',
               ar.topic_covered   AS 'Topic',
               ar.venue           AS 'Venue',
               ar.status          AS 'Status',
               ar.rejection_reason AS 'Rejection Reason'
        FROM attendance_records ar
        JOIN courses c ON ar.course_id = c.id
        $where
        ORDER BY ar.attendance_date DESC");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="my_attendance_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Course Code','Course Title','Start','End','Hours','Students',
                   'Type','Method','Topic','Venue','Status','Rejection Reason']);
    while ($r = $data->fetch_assoc()) fputcsv($out, array_values($r));
    fclose($out); exit;
}

// ── Records ───────────────────────────────────────────────────────────────────
$records = $conn->query("
    SELECT ar.*, c.course_code, c.course_title,
           v.full_name AS verifier,
           s.session_name, s.semester
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    LEFT JOIN users v ON ar.verified_by = v.id
    LEFT JOIN academic_sessions s ON ar.session_id = s.id
    $where
    ORDER BY ar.attendance_date DESC, ar.start_time DESC");

// ── Totals ────────────────────────────────────────────────────────────────────
$totals = $conn->query("
    SELECT COUNT(*)                           AS total,
           COALESCE(SUM(ar.duration_hours),0) AS hrs,
           SUM(ar.status='verified')          AS verified,
           SUM(ar.status='pending')           AS pending,
           SUM(ar.status='rejected')          AS rejected,
           COALESCE(AVG(ar.students_present),0) AS avg_students
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    $where")->fetch_assoc();

// ── All-time totals (no filter) ───────────────────────────────────────────────
$allTime = $conn->query("
    SELECT COUNT(*) AS total, COALESCE(SUM(duration_hours),0) AS hrs,
           SUM(status='verified') AS verified
    FROM attendance_records WHERE lecturer_id=$uid")->fetch_assoc();

// ── Courses for filter dropdown ───────────────────────────────────────────────
$myCourses = $conn->query("
    SELECT DISTINCT c.id, c.course_code, c.course_title
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    WHERE ar.lecturer_id = $uid
    ORDER BY c.course_code");

$conn->close();

$sidebarItems = [
    ['url'=>'lecturer/dashboard.php',       'icon'=>'fas fa-tachometer-alt',    'label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'lecturer/log_attendance.php',  'icon'=>'fas fa-plus-circle',       'label'=>'Log Attendance'],
    ['url'=>'lecturer/my_attendance.php',   'icon'=>'fas fa-clipboard-list',    'label'=>'My Attendance', 'active'=>true],
    ['divider'=>'Courses'],
    ['url'=>'lecturer/my_courses.php',      'icon'=>'fas fa-book',              'label'=>'My Courses'],
    ['divider'=>'Account'],
    ['url'=>'lecturer/profile.php',         'icon'=>'fas fa-user',              'label'=>'My Profile'],
    ['url'=>'lecturer/change_password.php', 'icon'=>'fas fa-lock',              'label'=>'Change Password'],
];
$breadcrumb = [['label' => 'My Attendance']];
include '../includes/header.php';
?>

<!-- Page heading -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:21px;font-weight:800;margin:0 0 3px">My Attendance Records</h2>
        <p style="color:var(--text-muted);font-size:13px;margin:0">
            <?= formatDate($s_from) ?> &mdash; <?= formatDate($s_to) ?>
            &nbsp;&bull;&nbsp; <?= (int)$totals['total'] ?> record<?= $totals['total'] != 1 ? 's':'' ?> found
        </p>
    </div>
    <div style="display:flex;gap:8px">
        <a href="log_attendance.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Log New
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['download'=>1])) ?>" class="btn btn-success btn-sm">
            <i class="fas fa-file-csv"></i> CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline btn-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
        <div><div class="stat-value"><?= $totals['total'] ?></div><div class="stat-label">Filtered Records</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-value"><?= number_format($totals['hrs'], 1) ?>h</div><div class="stat-label">Total Hours</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-value"><?= $totals['verified'] ?></div><div class="stat-label">Verified</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div><div class="stat-value"><?= $totals['pending'] ?></div><div class="stat-label">Pending</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div><div class="stat-value"><?= $totals['rejected'] ?></div><div class="stat-label">Rejected</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div>
        <div><div class="stat-value"><?= number_format($totals['avg_students'], 0) ?></div><div class="stat-label">Avg Students</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-history"></i></div>
        <div><div class="stat-value"><?= $allTime['total'] ?></div><div class="stat-label">All-Time Records</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-medal"></i></div>
        <div><div class="stat-value"><?= number_format($allTime['hrs'], 1) ?>h</div><div class="stat-label">All-Time Hours</div></div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-filter"></i> Filters</span>
        <a href="my_attendance.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="form-grid-3" style="margin-bottom:14px">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="from" class="form-control" value="<?= $s_from ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="to" class="form-control" value="<?= $s_to ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending"  <?= $s_status==='pending'  ? 'selected':'' ?>>Pending</option>
                        <option value="verified" <?= $s_status==='verified' ? 'selected':'' ?>>Verified</option>
                        <option value="rejected" <?= $s_status==='rejected' ? 'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <select name="course" class="form-control">
                        <option value="">All Courses</option>
                        <?php while ($c = $myCourses->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $s_course == $c['id'] ? 'selected':'' ?>>
                            <?= clean($c['course_code']) ?> — <?= clean(mb_substr($c['course_title'],0,30)) ?>
                        </option>
                        <?php endwhile ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Filter
            </button>
        </form>
    </div>
</div>

<!-- Records table -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-table"></i> Records</span>
        <input type="text" id="tableSearch" class="form-control" placeholder="Search..."
               style="width:180px" oninput="filterTable(this.value)">
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="recordsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Session</th>
                    <th>Time</th>
                    <th>Hours</th>
                    <th>Students</th>
                    <th>Type</th>
                    <th>Topic</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Verified By</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = $records->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td style="white-space:nowrap">
                        <div class="fw-600" style="font-size:13px"><?= formatDate($r['attendance_date'], 'd M Y') ?></div>
                        <div class="text-muted" style="font-size:11px"><?= date('D', strtotime($r['attendance_date'])) ?></div>
                    </td>
                    <td>
                        <div class="fw-600" style="font-size:13px"><?= clean($r['course_code']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= clean(mb_substr($r['course_title'],0,28)) ?><?= mb_strlen($r['course_title'])>28?'…':'' ?></div>
                    </td>
                    <td style="font-size:11px;color:var(--text-muted)">
                        <?= $r['session_name'] ? clean(mb_substr($r['session_name'],0,20)) : '—' ?>
                        <?= $r['semester'] ? '<br>'.ucfirst($r['semester']) : '' ?>
                    </td>
                    <td style="white-space:nowrap;font-size:12px">
                        <?= formatTime($r['start_time']) ?><br>
                        <span class="text-muted"><?= formatTime($r['end_time']) ?></span>
                    </td>
                    <td>
                        <?= $r['duration_hours']
                            ? '<strong>' . number_format($r['duration_hours'],2) . 'h</strong>'
                            : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td><?= (int)$r['students_present'] ?></td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:10px;text-transform:capitalize">
                            <?= str_replace('_',' ',$r['lecture_type']??'') ?>
                        </span>
                    </td>
                    <td style="max-width:160px;font-size:12px">
                        <?= clean(mb_substr($r['topic_covered']??'—',0,45)) ?><?= mb_strlen($r['topic_covered']??'')>45?'…':'' ?>
                    </td>
                    <td style="font-size:12px"><?= clean($r['venue']??'—') ?></td>
                    <td>
                        <?= statusBadge($r['status']) ?>
                        <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?>
                        <br>
                        <button onclick="showReason(<?= htmlspecialchars(json_encode($r['rejection_reason'])) ?>)"
                                style="background:none;border:none;color:#ef4444;font-size:11px;cursor:pointer;padding:2px 0;text-decoration:underline">
                            <i class="fas fa-info-circle"></i> See reason
                        </button>
                        <?php endif ?>
                        <?php if ($r['status'] === 'verified' && $r['verified_at']): ?>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px">
                            <?= formatDate($r['verified_at'], 'd M Y') ?>
                        </div>
                        <?php endif ?>
                    </td>
                    <td style="font-size:12px"><?= clean($r['verifier']??'—') ?></td>
                </tr>
                <?php endwhile ?>

                <?php if ($i === 1): ?>
                <tr><td colspan="12">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h4>No Records Found</h4>
                        <p>No attendance records match your filters.</p>
                        <a href="log_attendance.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Log Attendance
                        </a>
                    </div>
                </td></tr>
                <?php endif ?>
            </tbody>
            <?php if ($i > 1): ?>
            <tfoot>
                <tr style="background:var(--gray-50);font-weight:700">
                    <td colspan="5" style="padding:10px 16px">TOTALS</td>
                    <td style="padding:10px 16px"><?= number_format($totals['hrs'], 2) ?>h</td>
                    <td colspan="6"></td>
                </tr>
            </tfoot>
            <?php endif ?>
        </table>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div id="reasonModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:14px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid #e2e8f0">
            <h3 style="font-size:16px;font-weight:700;margin:0;color:#ef4444">
                <i class="fas fa-times-circle" style="margin-right:8px"></i>Rejection Reason
            </h3>
            <button onclick="document.getElementById('reasonModal').style.display='none'"
                    style="background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1">&times;</button>
        </div>
        <div style="padding:22px">
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;font-size:14px;color:#991b1b;line-height:1.6" id="reasonText"></div>
            <div style="margin-top:16px;font-size:13px;color:var(--text-muted)">
                <i class="fas fa-info-circle" style="margin-right:5px"></i>
                Please review and re-submit your attendance with the necessary corrections.
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
            <a href="log_attendance.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Log New Record
            </a>
            <button onclick="document.getElementById('reasonModal').style.display='none'" class="btn btn-outline btn-sm">
                Close
            </button>
        </div>
    </div>
</div>

<script>
function showReason(reason) {
    document.getElementById('reasonText').textContent = reason;
    document.getElementById('reasonModal').style.display = 'flex';
}

function filterTable(val) {
    val = val.toLowerCase();
    document.querySelectorAll('#recordsTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php include '../includes/footer.php'; ?>