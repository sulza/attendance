<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('lecturer');
$pageTitle = 'Log Attendance';
$user = getCurrentUser();
$conn = getDBConnection();
$uid = (int)$_SESSION['user_id'];
$session = getCurrentSession();

if (!$session) {
    setFlash('danger', 'No active academic session found. Please contact admin.');
    header('Location: dashboard.php'); exit;
}
$sessId = (int)$session['id'];

// Get assigned courses for this session
$assignedCourses = $conn->query("SELECT ca.id as assign_id, c.id, c.course_code, c.course_title, c.credit_units, c.level
    FROM course_assignments ca JOIN courses c ON ca.course_id = c.id
    WHERE ca.lecturer_id = $uid AND ca.session_id = $sessId ORDER BY c.course_code");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId  = sanitizeInt($_POST['course_id']);
    $date      = clean($_POST['attendance_date']);
    $startTime = clean($_POST['start_time']);
    $endTime   = clean($_POST['end_time']);
    $topic     = clean($_POST['topic_covered'] ?? '');
    $venue     = clean($_POST['venue'] ?? '');
    $students  = sanitizeInt($_POST['students_present'] ?? 0);
    $lecType   = clean($_POST['lecture_type'] ?? 'theory');
    $method    = clean($_POST['teaching_method'] ?? 'lecture');
    $materials = clean($_POST['materials_used'] ?? '');
    $remarks   = clean($_POST['remarks'] ?? '');
    $duration  = calcDuration($startTime, $endTime);
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

    // Validate
    $errors = [];
    if (!$courseId) $errors[] = 'Please select a course.';
    if (!$date)     $errors[] = 'Date is required.';
    if (!$startTime) $errors[] = 'Start time is required.';
    if (!$endTime)   $errors[] = 'End time is required.';
    if ($duration <= 0) $errors[] = 'End time must be after start time.';

    // Check duplicate
    $dup = $conn->query("SELECT id FROM attendance_records WHERE lecturer_id=$uid AND course_id=$courseId AND attendance_date='$date' AND start_time='$startTime'")->fetch_assoc();
    if ($dup) $errors[] = 'An attendance record already exists for this course on this date and time.';

    if (empty($errors)) {
        $conn->query("INSERT INTO attendance_records 
            (lecturer_id, course_id, session_id, attendance_date, start_time, end_time, duration_hours,
            topic_covered, venue, students_present, lecture_type, teaching_method, materials_used, remarks, sign_in_ip)
            VALUES ($uid, $courseId, $sessId, '$date', '$startTime', '$endTime', $duration,
            '$topic', '$venue', $students, '$lecType', '$method', '$materials', '$remarks', '$ip')");

        $newId = $conn->insert_id;
        logActivity($uid, 'LOG_ATTENDANCE', "Logged attendance for course ID $courseId on $date");

        // Notify HOD
        $hod = $conn->query("SELECT u.id FROM users u 
            JOIN courses c ON u.department_id = c.department_id 
            WHERE u.role='hod' AND c.id=$courseId LIMIT 1")->fetch_assoc();
        if ($hod) {
            sendNotification($hod['id'], 'New Attendance Logged', 
                $user['full_name'] . ' logged attendance for ' . date('d M Y', strtotime($date)), 'info');
        }

        setFlash('success', 'Attendance logged successfully! Record #' . $newId . ' is pending verification.');
        $conn->close();
        header('Location: my_attendance.php');
        exit;
    }
}
$conn->close();

$sidebarItems = [
    ['url'=>'lecturer/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'lecturer/log_attendance.php','icon'=>'fas fa-plus-circle','label'=>'Log Attendance','active'=>true],
    ['url'=>'lecturer/my_attendance.php','icon'=>'fas fa-clipboard-list','label'=>'My Records'],
    ['divider'=>'Courses'],
    ['url'=>'lecturer/my_courses.php','icon'=>'fas fa-book','label'=>'My Courses'],
    ['divider'=>'Account'],
    ['url'=>'lecturer/profile.php','icon'=>'fas fa-user','label'=>'My Profile'],
    ['url'=>'lecturer/change_password.php','icon'=>'fas fa-lock','label'=>'Change Password'],
];
$breadcrumb = [['label'=>'Log Attendance']];
include '../includes/header.php';
?>

<!-- Session Banner -->
<div style="background:var(--primary-light);border:1.5px solid var(--primary);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
  <i class="fas fa-info-circle" style="color:var(--primary)"></i>
  <span style="font-size:13px"><strong>Current Session:</strong> <?= clean($session['session_name']) ?> – <?= ucfirst($session['semester']) ?> Semester
    (<?= formatDate($session['start_date']) ?> – <?= formatDate($session['end_date']) ?>)</span>
</div>

<?php if (!empty($errors)): ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i><span><?= clean($e) ?></span></div>
  <?php endforeach ?>
<?php endif ?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><i class="fas fa-clipboard-plus"></i> Log Attendance Record</span>
  </div>
  <div class="card-body">
    <form method="POST">

      <!-- Row 1: Course & Date -->
      <div class="form-grid-2 mb-3">
        <div class="form-group">
          <label>Course <span class="req">*</span></label>
          <select name="course_id" class="form-control" required>
            <option value="">-- Select your assigned course --</option>
            <?php while ($c = $assignedCourses->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>" <?= (isset($_POST['course_id']) && $_POST['course_id'] == $c['id']) ? 'selected' : '' ?>>
                <?= clean($c['course_code']) ?> — <?= clean($c['course_title']) ?> (Level <?= $c['level'] ?>)
              </option>
            <?php endwhile ?>
          </select>
          <div class="form-hint">Only courses assigned to you this session are shown.</div>
        </div>
        <div class="form-group">
          <label>Attendance Date <span class="req">*</span></label>
          <input type="date" name="attendance_date" class="form-control" required data-today
                 value="<?= clean($_POST['attendance_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <!-- Row 2: Time -->
      <div class="form-grid-3 mb-3">
        <div class="form-group">
          <label>Start Time <span class="req">*</span></label>
          <input type="time" id="start_time" name="start_time" class="form-control" required
                 value="<?= clean($_POST['start_time'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>End Time <span class="req">*</span></label>
          <input type="time" id="end_time" name="end_time" class="form-control" required
                 value="<?= clean($_POST['end_time'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Duration (Hours)</label>
          <input type="number" id="duration_hours" name="duration_hours" class="form-control" step="0.01" readonly
                 placeholder="Auto-calculated" value="<?= clean($_POST['duration_hours'] ?? '') ?>"
                 style="background:var(--gray-50)">
          <div class="form-hint">Calculated automatically</div>
        </div>
      </div>

      <!-- Row 3: Topic & Venue -->
      <div class="form-grid-2 mb-3">
        <div class="form-group">
          <label>Topic Covered <span class="req">*</span></label>
          <input type="text" name="topic_covered" class="form-control" required
                 placeholder="e.g. Introduction to Binary Trees"
                 value="<?= clean($_POST['topic_covered'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Venue / Room</label>
          <input type="text" name="venue" class="form-control"
                 placeholder="e.g. CS Lab 2, Main Hall"
                 value="<?= clean($_POST['venue'] ?? '') ?>">
        </div>
      </div>

      <!-- Row 4: Students & Type -->
      <div class="form-grid-3 mb-3">
        <div class="form-group">
          <label>Number of Students Present</label>
          <input type="number" name="students_present" class="form-control" min="0"
                 value="<?= clean($_POST['students_present'] ?? 0) ?>">
        </div>
        <div class="form-group">
          <label>Lecture Type</label>
          <select name="lecture_type" class="form-control">
            <option value="theory"     <?= (($_POST['lecture_type'] ?? '') === 'theory')     ? 'selected' : '' ?>>Theory</option>
            <option value="practical"  <?= (($_POST['lecture_type'] ?? '') === 'practical')  ? 'selected' : '' ?>>Practical / Lab</option>
            <option value="seminar"    <?= (($_POST['lecture_type'] ?? '') === 'seminar')    ? 'selected' : '' ?>>Seminar</option>
            <option value="tutorial"   <?= (($_POST['lecture_type'] ?? '') === 'tutorial')   ? 'selected' : '' ?>>Tutorial</option>
            <option value="field_work" <?= (($_POST['lecture_type'] ?? '') === 'field_work') ? 'selected' : '' ?>>Field Work</option>
          </select>
        </div>
        <div class="form-group">
          <label>Teaching Method</label>
          <select name="teaching_method" class="form-control">
            <option value="lecture"      <?= (($_POST['teaching_method'] ?? '') === 'lecture')      ? 'selected' : '' ?>>Lecture</option>
            <option value="discussion"   <?= (($_POST['teaching_method'] ?? '') === 'discussion')   ? 'selected' : '' ?>>Discussion</option>
            <option value="demonstration"<?= (($_POST['teaching_method'] ?? '') === 'demonstration')? 'selected' : '' ?>>Demonstration</option>
            <option value="project"      <?= (($_POST['teaching_method'] ?? '') === 'project')      ? 'selected' : '' ?>>Project-based</option>
            <option value="workshop"     <?= (($_POST['teaching_method'] ?? '') === 'workshop')     ? 'selected' : '' ?>>Workshop</option>
          </select>
        </div>
      </div>

      <!-- Row 5: Materials & Remarks -->
      <div class="form-grid-2 mb-3">
        <div class="form-group">
          <label>Teaching Materials Used</label>
          <textarea name="materials_used" class="form-control" rows="3"
                    placeholder="e.g. Projector, handouts, textbook Chapter 5..."><?= clean($_POST['materials_used'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Remarks / Additional Notes</label>
          <textarea name="remarks" class="form-control" rows="3"
                    placeholder="Any additional observations..."><?= clean($_POST['remarks'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Declaration -->
      <div style="background:var(--gray-50);border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);padding:16px;margin-bottom:20px">
        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
          <input type="checkbox" name="declaration" required style="margin-top:3px;accent-color:var(--primary)">
          <span style="font-size:13px;color:var(--gray-700)">
            I, <strong><?= clean($user['full_name']) ?></strong>, hereby declare that the information provided above is true and correct to the best of my knowledge. I understand that any false information may result in disciplinary action.
          </span>
        </label>
      </div>

      <div class="btn-group">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Submit Attendance</button>
        <a href="dashboard.php" class="btn btn-outline btn-lg"><i class="fas fa-times"></i> Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
