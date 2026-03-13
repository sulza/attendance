<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Manage Courses';
$conn = getDBConnection();
$action = $_GET['action'] ?? '';
$id = sanitizeInt($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = clean($_POST['course_title']);
    $code   = clean($_POST['course_code']);
    $units  = sanitizeInt($_POST['credit_units']);
    $level  = clean($_POST['level']);
    $deptId = sanitizeInt($_POST['department_id']);
    $desc   = clean($_POST['description'] ?? '');
    $status = clean($_POST['status'] ?? 'active');
    $editId = sanitizeInt($_POST['edit_id'] ?? 0);

    if ($editId) {
        $conn->query("UPDATE courses SET course_title='$title',course_code='$code',credit_units=$units,level='$level',department_id=$deptId,description='$desc',status='$status' WHERE id=$editId");
        setFlash('success', 'Course updated.');
    } else {
        $conn->query("INSERT INTO courses (course_title,course_code,credit_units,level,department_id,description,status) VALUES ('$title','$code',$units,'$level',$deptId,'$desc','$status')");
        setFlash('success', 'Course added.');
    }
    header('Location: courses.php'); exit;
}
if ($action === 'delete' && $id) {
    $conn->query("UPDATE courses SET status='inactive' WHERE id=$id");
    setFlash('success', 'Course deactivated.'); header('Location: courses.php'); exit;
}

$editCourse = null;
if ($action === 'edit' && $id) $editCourse = $conn->query("SELECT * FROM courses WHERE id=$id")->fetch_assoc();

$courses = $conn->query("SELECT c.*, d.name as dept_name,
    (SELECT COUNT(*) FROM course_assignments WHERE course_id=c.id) as assigned_lecturers
    FROM courses c LEFT JOIN departments d ON c.department_id = d.id ORDER BY c.course_code");
$departments = getDepartments();
$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/hods.php','icon'=>'fas fa-user-tie','label'=>'HODs'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments'],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses','active'=>true],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Academic Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Course Assignments'],
    ['divider'=>'Attendance'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'All Attendance'],
    ['url'=>'admin/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports'],
];
$breadcrumb = [['label'=>'Courses']];
include '../includes/header.php';
?>
<div class="section-header">
  <span class="section-title"><i class="fas fa-book" style="color:var(--primary)"></i> Course Management</span>
  <button class="btn btn-primary" data-modal="addModal"><i class="fas fa-plus"></i> Add Course</button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>#</th><th>Code</th><th>Title</th><th>Level</th><th>Units</th><th>Department</th><th>Assigned</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $i=1; while ($r = $courses->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><strong><?= clean($r['course_code']) ?></strong></td>
          <td><?= clean($r['course_title']) ?></td>
          <td><?= $r['level'] ?></td>
          <td><?= $r['credit_units'] ?></td>
          <td><?= clean($r['dept_name'] ?? 'N/A') ?></td>
          <td><span class="badge badge-info"><?= $r['assigned_lecturers'] ?></span></td>
          <td><span class="badge <?= badgeStatus($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td>
            <div class="btn-group">
              <a href="courses.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a>
              <a href="courses.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Deactivate this course?"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title"><i class="fas fa-plus-circle"></i> Add Course</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Course Title <span class="req">*</span></label><input name="course_title" class="form-control" required></div>
          <div class="form-group"><label>Course Code <span class="req">*</span></label><input name="course_code" class="form-control" required></div>
          <div class="form-group"><label>Credit Units</label><input name="credit_units" type="number" class="form-control" value="3" min="1"></div>
          <div class="form-group"><label>Level</label>
            <select name="level" class="form-control">
              <?php foreach (['100','200','300','400','500','600','PG'] as $l): ?><option><?= $l ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Department</label>
            <select name="department_id" class="form-control">
              <option value="0">Select...</option>
              <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= clean($d['name']) ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Status</label>
            <select name="status" class="form-control"><option>active</option><option>inactive</option></select>
          </div>
          <div class="form-group full"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<?php if ($action === 'edit' && $editCourse): ?>
<div class="modal-overlay open">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit Course</span><a href="courses.php" class="modal-close"><i class="fas fa-times"></i></a></div>
    <form method="POST">
      <input type="hidden" name="edit_id" value="<?= $editCourse['id'] ?>">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Course Title</label><input name="course_title" class="form-control" value="<?= clean($editCourse['course_title']) ?>" required></div>
          <div class="form-group"><label>Course Code</label><input name="course_code" class="form-control" value="<?= clean($editCourse['course_code']) ?>" required></div>
          <div class="form-group"><label>Credit Units</label><input name="credit_units" type="number" class="form-control" value="<?= $editCourse['credit_units'] ?>"></div>
          <div class="form-group"><label>Level</label>
            <select name="level" class="form-control">
              <?php foreach (['100','200','300','400','500','600','PG'] as $l): ?>
                <option <?= $editCourse['level']===$l?'selected':'' ?>><?= $l ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Department</label>
            <select name="department_id" class="form-control">
              <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $editCourse['department_id']==$d['id']?'selected':'' ?>><?= clean($d['name']) ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group"><label>Status</label>
            <select name="status" class="form-control">
              <?php foreach (['active','inactive'] as $s): ?>
                <option <?= $editCourse['status']===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="form-group full"><label>Description</label><textarea name="description" class="form-control"><?= clean($editCourse['description']??'') ?></textarea></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="courses.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif ?>
<?php include '../includes/footer.php'; ?>
