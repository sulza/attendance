<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Manage Departments';
$conn = getDBConnection();
$action = $_GET['action'] ?? '';
$id = sanitizeInt($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = clean($_POST['name']);
    $code    = clean($_POST['code']);
    $faculty = clean($_POST['faculty'] ?? '');
    $editId  = sanitizeInt($_POST['edit_id'] ?? 0);
    if ($editId) {
        $conn->query("UPDATE departments SET name='$name',code='$code',faculty='$faculty' WHERE id=$editId");
        setFlash('success','Department updated.');
    } else {
        $conn->query("INSERT INTO departments (name,code,faculty) VALUES ('$name','$code','$faculty')");
        setFlash('success','Department added.');
    }
    header('Location: departments.php'); exit;
}

$editDept = null;
if ($action === 'edit' && $id) $editDept = $conn->query("SELECT * FROM departments WHERE id=$id")->fetch_assoc();

$depts = $conn->query("SELECT d.*, 
    (SELECT COUNT(*) FROM users WHERE department_id=d.id AND role='lecturer') as lecturers,
    (SELECT COUNT(*) FROM courses WHERE department_id=d.id) as courses
    FROM departments d ORDER BY d.name");
$conn->close();

$sidebarItems = [
    ['url'=>'admin/dashboard.php','icon'=>'fas fa-tachometer-alt','label'=>'Dashboard'],
    ['divider'=>'Management'],
    ['url'=>'admin/lecturers.php','icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['url'=>'admin/hods.php','icon'=>'fas fa-user-tie','label'=>'HODs'],
    ['url'=>'admin/departments.php','icon'=>'fas fa-building','label'=>'Departments','active'=>true],
    ['url'=>'admin/courses.php','icon'=>'fas fa-book','label'=>'Courses'],
    ['url'=>'admin/sessions.php','icon'=>'fas fa-calendar-alt','label'=>'Sessions'],
    ['url'=>'admin/assignments.php','icon'=>'fas fa-link','label'=>'Assignments'],
    ['divider'=>'Reports'],
    ['url'=>'admin/attendance.php','icon'=>'fas fa-clipboard-check','label'=>'Attendance'],
    ['url'=>'admin/reports.php','icon'=>'fas fa-chart-bar','label'=>'Reports'],
];
$breadcrumb=[['label'=>'Departments']];
include '../includes/header.php';
?>
<div class="section-header">
  <span class="section-title"><i class="fas fa-building" style="color:var(--primary)"></i> Departments</span>
  <button class="btn btn-primary" data-modal="addModal"><i class="fas fa-plus"></i> Add Department</button>
</div>
<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Faculty</th><th>Lecturers</th><th>Courses</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $i=1; while ($r=$depts->fetch_assoc()): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><strong><?= clean($r['name']) ?></strong></td>
          <td><span class="badge badge-info"><?= clean($r['code']) ?></span></td>
          <td><?= clean($r['faculty']??'N/A') ?></td>
          <td><?= $r['lecturers'] ?></td>
          <td><?= $r['courses'] ?></td>
          <td><a href="departments.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></a></td>
        </tr>
        <?php endwhile ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Add Department</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
    <form method="POST">
      <div class="modal-body">
        <div class="form-group mb-2"><label>Name <span class="req">*</span></label><input name="name" class="form-control" required></div>
        <div class="form-group mb-2"><label>Code <span class="req">*</span></label><input name="code" class="form-control" required></div>
        <div class="form-group"><label>Faculty</label><input name="faculty" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<?php if ($action==='edit' && $editDept): ?>
<div class="modal-overlay open">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Edit Department</span><a href="departments.php" class="modal-close"><i class="fas fa-times"></i></a></div>
    <form method="POST">
      <input type="hidden" name="edit_id" value="<?= $editDept['id'] ?>">
      <div class="modal-body">
        <div class="form-group mb-2"><label>Name</label><input name="name" class="form-control" value="<?= clean($editDept['name']) ?>" required></div>
        <div class="form-group mb-2"><label>Code</label><input name="code" class="form-control" value="<?= clean($editDept['code']) ?>" required></div>
        <div class="form-group"><label>Faculty</label><input name="faculty" class="form-control" value="<?= clean($editDept['faculty']??'') ?>"></div>
      </div>
      <div class="modal-footer">
        <a href="departments.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif ?>
<?php include '../includes/footer.php'; ?>
