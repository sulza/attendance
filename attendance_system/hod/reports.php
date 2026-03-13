<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('hod');
$pageTitle = 'Reports & Downloads';
$user   = getCurrentUser();
$conn   = getDBConnection();
$uid    = (int)$_SESSION['user_id'];
$deptId = (int)($user['department_id'] ?? 0);

// Department scope — HOD only sees their own dept
$deptWhere = $deptId ? "AND c.department_id = $deptId" : '';

// ── Filters ───────────────────────────────────────────────────────────────────
$s_lec  = sanitizeInt($_GET['lecturer']   ?? 0);
$s_from = clean($_GET['from']             ?? date('Y-m-01'));
$s_to   = clean($_GET['to']               ?? date('Y-m-d'));
$s_stat = clean($_GET['status']           ?? '');
$s_sess = sanitizeInt($_GET['session_id'] ?? 0);

$where = "WHERE 1=1 $deptWhere";
if ($s_lec)  $where .= " AND ar.lecturer_id = $s_lec";
if ($s_from) $where .= " AND ar.attendance_date >= '$s_from'";
if ($s_to)   $where .= " AND ar.attendance_date <= '$s_to'";
if ($s_stat) $where .= " AND ar.status = '$s_stat'";
if ($s_sess) $where .= " AND ar.session_id = $s_sess";

// ── CSV Download ──────────────────────────────────────────────────────────────
if (isset($_GET['download'])) {
    $data = $conn->query("
        SELECT ar.attendance_date AS 'Date',
               u.full_name        AS 'Lecturer',
               u.staff_id         AS 'Staff ID',
               c.course_code      AS 'Course Code',
               c.course_title     AS 'Course Title',
               ar.start_time      AS 'Start',
               ar.end_time        AS 'End',
               ar.duration_hours  AS 'Hours',
               ar.students_present AS 'Students',
               ar.lecture_type    AS 'Type',
               ar.topic_covered   AS 'Topic',
               ar.venue           AS 'Venue',
               ar.status          AS 'Status',
               v.full_name        AS 'Verified By',
               ar.rejection_reason AS 'Rejection Reason'
        FROM attendance_records ar
        JOIN users u ON ar.lecturer_id = u.id
        JOIN courses c ON ar.course_id = c.id
        LEFT JOIN users v ON ar.verified_by = v.id
        $where
        ORDER BY ar.attendance_date DESC");

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="dept_report_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Lecturer','Staff ID','Course Code','Course Title','Start','End',
                   'Hours','Students','Type','Topic','Venue','Status','Verified By','Rejection Reason']);
    while ($r = $data->fetch_assoc()) fputcsv($out, array_values($r));
    fclose($out); exit;
}

// ── Totals ────────────────────────────────────────────────────────────────────
$totals = $conn->query("
    SELECT COUNT(*)                               AS total,
           COALESCE(SUM(ar.duration_hours), 0)    AS total_hours,
           SUM(ar.status = 'verified')             AS verified,
           SUM(ar.status = 'pending')              AS pending,
           SUM(ar.status = 'rejected')             AS rejected,
           COALESCE(AVG(ar.students_present), 0)   AS avg_students,
           COUNT(DISTINCT ar.lecturer_id)           AS unique_lecturers
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    $where
")->fetch_assoc();

// ── Summary by Lecturer ───────────────────────────────────────────────────────
$by_lecturer = $conn->query("
    SELECT u.full_name, u.staff_id,
           COUNT(ar.id)                          AS sessions,
           COALESCE(SUM(ar.duration_hours), 0)   AS hrs,
           SUM(ar.status = 'verified')           AS verified,
           SUM(ar.status = 'pending')            AS pending,
           SUM(ar.status = 'rejected')           AS rejected,
           COALESCE(SUM(ar.students_present), 0) AS total_students,
           COALESCE(AVG(ar.students_present), 0) AS avg_students
    FROM attendance_records ar
    JOIN users u ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    $where
    GROUP BY u.id
    ORDER BY hrs DESC");

// ── Summary by Course ─────────────────────────────────────────────────────────
$by_course = $conn->query("
    SELECT c.course_code, c.course_title,
           COUNT(ar.id)                          AS sessions,
           COALESCE(SUM(ar.duration_hours), 0)   AS hrs,
           SUM(ar.status = 'verified')           AS verified,
           SUM(ar.status = 'pending')            AS pending,
           COALESCE(AVG(ar.students_present), 0) AS avg_students
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    $where
    GROUP BY c.id
    ORDER BY hrs DESC");

// ── Trend data ────────────────────────────────────────────────────────────────
$trend_raw = $conn->query("
    SELECT attendance_date,
           COUNT(*) AS cnt,
           COALESCE(SUM(duration_hours), 0) AS hrs
    FROM attendance_records ar
    JOIN courses c ON ar.course_id = c.id
    $where
    GROUP BY attendance_date
    ORDER BY attendance_date ASC");
$trend_labels = $trend_counts = $trend_hours = [];
while ($t = $trend_raw->fetch_assoc()) {
    $trend_labels[] = date('d M', strtotime($t['attendance_date']));
    $trend_counts[] = (int)$t['cnt'];
    $trend_hours[]  = (float)$t['hrs'];
}

// ── Detailed records ──────────────────────────────────────────────────────────
$records = $conn->query("
    SELECT ar.*,
           u.full_name AS lecturer_name, u.staff_id,
           c.course_code, c.course_title,
           v.full_name AS verifier_name,
           s.session_name, s.semester
    FROM attendance_records ar
    JOIN users u ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    LEFT JOIN users v ON ar.verified_by = v.id
    LEFT JOIN academic_sessions s ON ar.session_id = s.id
    $where
    ORDER BY ar.attendance_date DESC, u.full_name
    LIMIT 500");

// ── Filter options ────────────────────────────────────────────────────────────
$lecturers = $conn->query("
    SELECT DISTINCT u.id, u.full_name, u.staff_id
    FROM users u
    JOIN attendance_records ar ON ar.lecturer_id = u.id
    JOIN courses c ON ar.course_id = c.id
    WHERE u.role = 'lecturer'" . ($deptId ? " AND u.department_id = $deptId" : '') . "
    ORDER BY u.full_name");
$sessions = $conn->query("SELECT * FROM academic_sessions ORDER BY start_date DESC");

// Dept name for heading
$deptName = $user['dept_name'] ?? 'Department';

$conn->close();

// ── Sidebar ───────────────────────────────────────────────────────────────────
$sidebarItems = [
    ['url'=>'hod/dashboard.php',   'icon'=>'fas fa-tachometer-alt',     'label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'hod/attendance.php',  'icon'=>'fas fa-clipboard-check',    'label'=>'Attendance Records'],
    ['url'=>'hod/reports.php',     'icon'=>'fas fa-chart-bar',           'label'=>'Reports & Downloads', 'active'=>true],
    ['divider'=>'Department'],
    ['url'=>'hod/lecturers.php',   'icon'=>'fas fa-chalkboard-teacher', 'label'=>'Lecturers'],
    ['divider'=>'Account'],
    ['url'=>'hod/profile.php',     'icon'=>'fas fa-user',                'label'=>'My Profile'],
    ['url'=>'hod/change_password.php','icon'=>'fas fa-lock',             'label'=>'Change Password'],
];
$breadcrumb = [['label' => 'Reports & Downloads']];
include '../includes/header.php';
?>

<!-- Page heading -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:21px;font-weight:800;margin:0 0 3px">Reports &amp; Downloads</h2>
        <p style="color:var(--text-muted);font-size:13px;margin:0">
            <i class="fas fa-building" style="margin-right:4px"></i><?= clean($deptName) ?>
            &nbsp;&bull;&nbsp; <?= formatDate($s_from) ?> &mdash; <?= formatDate($s_to) ?>
            &nbsp;&bull;&nbsp; <?= (int)$totals['total'] ?> record<?= $totals['total'] != 1 ? 's' : '' ?>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="?<?= http_build_query(array_merge($_GET, ['download'=>1])) ?>"
           class="btn btn-success btn-sm">
            <i class="fas fa-file-csv"></i> Download CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline btn-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- ── FILTERS ────────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-filter"></i> Filters</span>
        <a href="reports.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Clear</a>
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
                        <option value="pending"  <?= $s_stat==='pending'  ? 'selected':'' ?>>Pending</option>
                        <option value="verified" <?= $s_stat==='verified' ? 'selected':'' ?>>Verified</option>
                        <option value="rejected" <?= $s_stat==='rejected' ? 'selected':'' ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lecturer</label>
                    <select name="lecturer" class="form-control">
                        <option value="">All Lecturers</option>
                        <?php while ($l = $lecturers->fetch_assoc()): ?>
                        <option value="<?= $l['id'] ?>" <?= $s_lec == $l['id'] ? 'selected':'' ?>>
                            <?= clean($l['full_name']) ?><?= $l['staff_id'] ? " ({$l['staff_id']})" : '' ?>
                        </option>
                        <?php endwhile ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Session</label>
                    <select name="session_id" class="form-control">
                        <option value="">All Sessions</option>
                        <?php while ($sess = $sessions->fetch_assoc()): ?>
                        <option value="<?= $sess['id'] ?>" <?= $s_sess == $sess['id'] ? 'selected':'' ?>>
                            <?= clean($sess['session_name']) ?> &mdash; <?= ucfirst($sess['semester'] ?? '') ?>
                        </option>
                        <?php endwhile ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Generate Report
            </button>
        </form>
    </div>
</div>

<!-- ── STATS ──────────────────────────────────────────────────────────────── -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
        <div><div class="stat-value"><?= number_format($totals['total']) ?></div><div class="stat-label">Total Records</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-value"><?= number_format($totals['total_hours'], 1) ?>h</div><div class="stat-label">Total Hours</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-value"><?= number_format($totals['verified']) ?></div><div class="stat-label">Verified</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
        <div><div class="stat-value"><?= number_format($totals['pending']) ?></div><div class="stat-label">Pending</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div><div class="stat-value"><?= number_format($totals['rejected']) ?></div><div class="stat-label">Rejected</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= number_format($totals['unique_lecturers']) ?></div><div class="stat-label">Lecturers</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
        <div><div class="stat-value"><?= number_format($totals['avg_students'], 0) ?></div><div class="stat-label">Avg Students</div></div>
    </div>
</div>

<!-- ── CHARTS ─────────────────────────────────────────────────────────────── -->
<?php if (!empty($trend_labels)): ?>
<div class="grid-2" style="margin-bottom:20px">
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-chart-line"></i> Daily Attendance Trend</span>
        </div>
        <div class="card-body" style="padding:16px">
            <canvas id="trendChart" height="200"></canvas>
        </div>
    </div>
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-chart-pie"></i> Status Breakdown</span>
        </div>
        <div class="card-body" style="padding:16px;display:flex;align-items:center;justify-content:center;gap:24px">
            <div style="width:160px;height:160px"><canvas id="statusChart"></canvas></div>
            <div>
                <?php
                $ttl = max(1, (int)$totals['total']);
                foreach ([
                    ['#10b981','Verified', $totals['verified']],
                    ['#f59e0b','Pending',  $totals['pending']],
                    ['#ef4444','Rejected', $totals['rejected']],
                ] as [$color, $label, $count]):
                    $pct = round($count / $ttl * 100);
                ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                    <span style="width:12px;height:12px;border-radius:3px;background:<?= $color ?>;display:inline-block;flex-shrink:0"></span>
                    <span style="font-size:13px"><?= $label ?> &mdash; <strong><?= $count ?> (<?= $pct ?>%)</strong></span>
                </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>
<?php endif ?>

<!-- ── BY LECTURER ────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chalkboard-teacher"></i> Summary by Lecturer</span>
        <button onclick="exportTableCSV('lecTable','lecturer_summary')" class="btn btn-outline btn-sm">
            <i class="fas fa-download"></i> CSV
        </button>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="lecTable">
            <thead>
                <tr>
                    <th>#</th><th>Lecturer</th><th>Staff ID</th><th>Sessions</th>
                    <th>Total Hours</th><th>Avg Hrs</th><th>Avg Students</th>
                    <th>Verified</th><th>Pending</th><th>Rejected</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = $by_lecturer->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td class="fw-600"><?= clean($r['full_name']) ?></td>
                    <td><?= clean($r['staff_id'] ?? '—') ?></td>
                    <td><?= $r['sessions'] ?></td>
                    <td><strong><?= number_format($r['hrs'], 2) ?>h</strong></td>
                    <td><?= $r['sessions'] > 0 ? number_format($r['hrs'] / $r['sessions'], 2) . 'h' : '—' ?></td>
                    <td><?= number_format($r['avg_students'], 0) ?></td>
                    <td><span class="badge badge-success"><?= $r['verified'] ?></span></td>
                    <td><span class="badge badge-warning"><?= $r['pending'] ?></span></td>
                    <td><span class="badge badge-danger"><?= $r['rejected'] ?></span></td>
                </tr>
                <?php endwhile ?>
                <?php if ($i === 1): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:28px">No records for the selected filters.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── BY COURSE ──────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-book"></i> Summary by Course</span>
        <button onclick="exportTableCSV('courseTable','course_summary')" class="btn btn-outline btn-sm">
            <i class="fas fa-download"></i> CSV
        </button>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="courseTable">
            <thead>
                <tr>
                    <th>#</th><th>Course Code</th><th>Course Title</th>
                    <th>Sessions</th><th>Total Hours</th><th>Avg Students</th>
                    <th>Verified</th><th>Pending</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = $by_course->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td><span class="badge badge-primary"><?= clean($r['course_code']) ?></span></td>
                    <td><?= clean($r['course_title']) ?></td>
                    <td><?= $r['sessions'] ?></td>
                    <td><strong><?= number_format($r['hrs'], 2) ?>h</strong></td>
                    <td><?= number_format($r['avg_students'], 0) ?></td>
                    <td><span class="badge badge-success"><?= $r['verified'] ?></span></td>
                    <td><span class="badge badge-warning"><?= $r['pending'] ?></span></td>
                </tr>
                <?php endwhile ?>
                <?php if ($i === 1): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:28px">No records found.</td></tr>
                <?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── DETAIL TABLE ───────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-table"></i> Detailed Records</span>
        <div style="display:flex;gap:8px;align-items:center">
            <input type="text" id="detailSearch" class="form-control" placeholder="Search..."
                   style="width:170px" oninput="filterDetail(this.value)">
            <button onclick="exportTableCSV('detailTable','attendance_detail')" class="btn btn-outline btn-sm">
                <i class="fas fa-download"></i> CSV
            </button>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="detailTable">
            <thead>
                <tr>
                    <th>#</th><th>Date</th><th>Lecturer</th><th>Course</th>
                    <th>Time</th><th>Hours</th><th>Students</th><th>Type</th><th>Topic</th>
                    <th>Venue</th><th>Status</th><th>Verified By</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($r = $records->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td style="white-space:nowrap"><?= formatDate($r['attendance_date'], 'd M Y') ?></td>
                    <td>
                        <div class="fw-600" style="font-size:13px"><?= clean($r['lecturer_name']) ?></div>
                        <?php if ($r['staff_id']): ?>
                        <div class="text-muted" style="font-size:11px"><?= clean($r['staff_id']) ?></div>
                        <?php endif ?>
                    </td>
                    <td>
                        <div class="fw-600" style="font-size:12px"><?= clean($r['course_code']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= clean(mb_substr($r['course_title'], 0, 28)) ?><?= mb_strlen($r['course_title']) > 28 ? '…':'' ?></div>
                    </td>
                    <td style="font-size:12px;white-space:nowrap">
                        <?= formatTime($r['start_time']) ?><br>
                        <span class="text-muted"><?= formatTime($r['end_time']) ?></span>
                    </td>
                    <td><?= $r['duration_hours'] ? '<strong>'.number_format($r['duration_hours'],2).'h</strong>' : '—' ?></td>
                    <td><?= (int)$r['students_present'] ?></td>
                    <td>
                        <span class="badge badge-secondary" style="font-size:10px;text-transform:capitalize">
                            <?= str_replace('_', ' ', $r['lecture_type'] ?? '') ?>
                        </span>
                    </td>
                    <td style="max-width:150px;font-size:12px">
                        <?= clean(mb_substr($r['topic_covered'] ?? '—', 0, 45)) ?><?= mb_strlen($r['topic_covered'] ?? '') > 45 ? '…':'' ?>
                    </td>
                    <td style="font-size:12px"><?= clean($r['venue'] ?? '—') ?></td>
                    <td><?= statusBadge($r['status']) ?></td>
                    <td style="font-size:12px"><?= clean($r['verifier_name'] ?? '—') ?></td>
                </tr>
                <?php endwhile ?>
                <?php if ($i === 1): ?>
                <tr><td colspan="12">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-search"></i></div>
                        <h4>No Records Found</h4>
                        <p>Try adjusting your filters or date range.</p>
                    </div>
                </td></tr>
                <?php endif ?>
            </tbody>
            <?php if ($i > 1): ?>
            <tfoot>
                <tr style="background:var(--gray-50);font-weight:700">
                    <td colspan="5" style="padding:10px 16px">TOTALS</td>
                    <td style="padding:10px 16px"><?= number_format($totals['total_hours'], 2) ?>h</td>
                    <td colspan="6"></td>
                </tr>
            </tfoot>
            <?php endif ?>
        </table>
    </div>
    <?php if ($i > 501): ?>
    <div style="padding:12px 20px;background:#fef3c7;font-size:13px;color:#92400e;border-top:1px solid #fde68a">
        <i class="fas fa-info-circle"></i> Showing first 500 records. Use <strong>Download CSV</strong> for the full dataset.
    </div>
    <?php endif ?>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($trend_labels)): ?>
(function(){
    const ctx = document.getElementById('trendChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [
                { label:'Sessions', data:<?= json_encode($trend_counts) ?>, borderColor:'#6366f1',
                  backgroundColor:'rgba(99,102,241,0.1)', tension:0.4, fill:true, pointRadius:3, yAxisID:'y' },
                { label:'Hours',    data:<?= json_encode($trend_hours)  ?>, borderColor:'#10b981',
                  backgroundColor:'transparent', tension:0.4, fill:false, pointRadius:3, yAxisID:'y1' }
            ]
        },
        options: {
            responsive:true, interaction:{mode:'index',intersect:false},
            plugins:{ legend:{position:'bottom',labels:{font:{size:11}}} },
            scales:{
                x:  { grid:{display:false}, ticks:{font:{size:10},maxRotation:45} },
                y:  { position:'left',  beginAtZero:true, ticks:{stepSize:1,font:{size:10}}, title:{display:true,text:'Sessions',font:{size:10}} },
                y1: { position:'right', beginAtZero:true, grid:{drawOnChartArea:false}, ticks:{font:{size:10}}, title:{display:true,text:'Hours',font:{size:10}} }
            }
        }
    });
})();

(function(){
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type:'doughnut',
        data:{
            labels:['Verified','Pending','Rejected'],
            datasets:[{ data:[<?= (int)$totals['verified'] ?>,<?= (int)$totals['pending'] ?>,<?= (int)$totals['rejected'] ?>],
                        backgroundColor:['#10b981','#f59e0b','#ef4444'], borderWidth:2, borderColor:'#fff' }]
        },
        options:{ responsive:true, cutout:'68%', plugins:{legend:{display:false}} }
    });
})();
<?php endif ?>

function filterDetail(val) {
    val = val.toLowerCase();
    document.querySelectorAll('#detailTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}

function exportTableCSV(tableId, filename) {
    const rows = document.querySelectorAll('#' + tableId + ' tr');
    const csv  = [];
    rows.forEach(row => {
        const cols = row.querySelectorAll('td,th');
        csv.push([...cols].map(c => '"' + c.innerText.replace(/"/g,'""').trim() + '"').join(','));
    });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(new Blob([csv.join('\n')],{type:'text/csv'}));
    a.download = filename + '_<?= date('Ymd') ?>.csv';
    a.click();
}
</script>

<?php include '../includes/footer.php'; ?>