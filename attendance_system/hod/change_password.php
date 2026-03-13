<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('hod');
$pageTitle = 'Change Password';
$conn = getDBConnection();
$uid  = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new1    = $_POST['new_password']     ?? '';
    $new2    = $_POST['confirm_password'] ?? '';

    $user = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();

    if (!password_verify($current, $user['password'])) {
        setFlash('danger', 'Current password is incorrect.');
    } elseif (strlen($new1) < 8) {
        setFlash('danger', 'New password must be at least 8 characters.');
    } elseif (!preg_match('/[A-Z]/', $new1)) {
        setFlash('danger', 'Password must contain at least one uppercase letter.');
    } elseif (!preg_match('/[0-9]/', $new1)) {
        setFlash('danger', 'Password must contain at least one number.');
    } elseif ($new1 !== $new2) {
        setFlash('danger', 'New passwords do not match.');
    } else {
        $hash = password_hash($new1, PASSWORD_BCRYPT, ['cost' => 12]);
        $conn->query("UPDATE users SET password='$hash' WHERE id=$uid");
        setFlash('success', 'Password changed successfully.');
        logActivity($uid, 'CHANGE_PASSWORD', 'HOD changed their password');
    }
    header('Location: change_password.php'); exit;
}
$conn->close();

$sidebarItems = [
    ['url'=>'hod/dashboard.php',      'icon'=>'fas fa-tachometer-alt',    'label'=>'Dashboard'],
    ['divider'=>'Attendance'],
    ['url'=>'hod/attendance.php',     'icon'=>'fas fa-clipboard-check',   'label'=>'Attendance Records'],
    ['url'=>'hod/reports.php',        'icon'=>'fas fa-chart-bar',          'label'=>'Reports & Downloads'],
    ['divider'=>'Department'],
    ['url'=>'hod/lecturers.php',      'icon'=>'fas fa-chalkboard-teacher','label'=>'Lecturers'],
    ['divider'=>'Account'],
    ['url'=>'hod/profile.php',        'icon'=>'fas fa-user',               'label'=>'My Profile'],
    ['url'=>'hod/change_password.php','icon'=>'fas fa-lock',               'label'=>'Change Password', 'active'=>true],
];
$breadcrumb = [['label' => 'Change Password']];
include '../includes/header.php';
?>

<div style="max-width:520px">

    <div style="margin-bottom:20px">
        <h2 style="font-size:21px;font-weight:800;margin:0 0 4px">Change Password</h2>
        <p style="color:var(--text-muted);font-size:13px;margin:0">
            Keep your account secure with a strong, unique password.
        </p>
    </div>

    <!-- Form card -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-lock"></i> Update Password</span>
        </div>
        <div class="card-body">
            <form method="POST" id="pwdForm">

                <!-- Current password -->
                <div class="form-group" style="margin-bottom:18px">
                    <label>Current Password <span class="req">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="current_password" id="currentPwd"
                               class="form-control" required placeholder="Enter current password">
                        <button type="button" class="password-toggle" onclick="togglePwd('currentPwd','eyeCurrent')">
                            <i class="fas fa-eye" id="eyeCurrent"></i>
                        </button>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">

                <!-- New password -->
                <div class="form-group" style="margin-bottom:18px">
                    <label>New Password <span class="req">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" name="new_password" id="newPwd"
                               class="form-control" required minlength="8"
                               placeholder="Enter new password"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="password-toggle" onclick="togglePwd('newPwd','eyeNew')">
                            <i class="fas fa-eye" id="eyeNew"></i>
                        </button>
                    </div>

                    <!-- Strength bar -->
                    <div style="margin-top:10px">
                        <div style="height:5px;background:var(--gray-200);border-radius:3px;overflow:hidden">
                            <div id="strengthBar" style="height:100%;width:0%;border-radius:3px;transition:all .3s"></div>
                        </div>
                        <div id="strengthLabel" style="font-size:11px;color:var(--text-muted);margin-top:4px"></div>
                    </div>

                    <!-- Requirements checklist -->
                    <div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:6px">
                        <div id="req-len"   class="pwd-req"><i class="fas fa-circle"></i> At least 8 characters</div>
                        <div id="req-upper" class="pwd-req"><i class="fas fa-circle"></i> One uppercase letter</div>
                        <div id="req-num"   class="pwd-req"><i class="fas fa-circle"></i> One number</div>
                        <div id="req-diff"  class="pwd-req"><i class="fas fa-circle"></i> Different from current</div>
                    </div>
                </div>

                <!-- Confirm password -->
                <div class="form-group" style="margin-bottom:24px">
                    <label>Confirm New Password <span class="req">*</span></label>
                    <div class="input-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" name="confirm_password" id="confirmPwd"
                               class="form-control" required
                               placeholder="Re-enter new password"
                               oninput="checkMatch()">
                        <button type="button" class="password-toggle" onclick="togglePwd('confirmPwd','eyeConfirm')">
                            <i class="fas fa-eye" id="eyeConfirm"></i>
                        </button>
                    </div>
                    <div id="matchMsg" style="font-size:12px;margin-top:5px"></div>
                </div>

                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                    <a href="profile.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </div>

            </form>
        </div>
    </div>

    <!-- Tips card -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-shield-alt"></i> Password Security Tips</span>
        </div>
        <div class="card-body">
            <ul style="margin:0;padding-left:18px;color:var(--text-muted);font-size:13px;line-height:2">
                <li>Use a mix of uppercase, lowercase, numbers, and symbols.</li>
                <li>Never reuse passwords from other accounts or services.</li>
                <li>Avoid personal information such as names or birthdays.</li>
                <li>Consider using a passphrase — e.g. <em>Blue$Elephant9River</em>.</li>
                <li>Change your password regularly, at least every 90 days.</li>
            </ul>
        </div>
    </div>

</div>

<style>
.pwd-req {
    font-size: 11px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 5px;
}
.pwd-req.met { color: #10b981; }
.pwd-req.met .fa-circle::before { content: "\f058"; }
</style>

<script>
function togglePwd(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

function checkStrength(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    const hasLen   = val.length >= 8;
    const hasUpper = /[A-Z]/.test(val);
    const hasNum   = /[0-9]/.test(val);
    const hasSym   = /[^A-Za-z0-9]/.test(val);

    document.getElementById('req-len').classList.toggle('met',   hasLen);
    document.getElementById('req-upper').classList.toggle('met', hasUpper);
    document.getElementById('req-num').classList.toggle('met',   hasNum);

    let score = [hasLen, hasUpper, hasNum, hasSym, val.length >= 12].filter(Boolean).length;
    const levels = [
        { w:'0%',   color:'#e2e8f0', text:'' },
        { w:'25%',  color:'#ef4444', text:'Weak' },
        { w:'50%',  color:'#f59e0b', text:'Fair' },
        { w:'75%',  color:'#3b82f6', text:'Good' },
        { w:'100%', color:'#10b981', text:'Strong' },
    ];
    const lvl = levels[Math.min(score, 4)];
    bar.style.width      = lvl.w;
    bar.style.background = lvl.color;
    label.textContent    = lvl.text;
    label.style.color    = lvl.color;

    checkMatch();
}

function checkMatch() {
    const nv  = document.getElementById('newPwd').value;
    const cv  = document.getElementById('confirmPwd').value;
    const cur = document.getElementById('currentPwd').value;
    const msg = document.getElementById('matchMsg');

    document.getElementById('req-diff').classList.toggle('met', nv.length > 0 && nv !== cur);

    if (!cv) { msg.textContent = ''; return; }
    msg.innerHTML = nv === cv
        ? '<span style="color:#10b981"><i class="fas fa-check-circle"></i> Passwords match</span>'
        : '<span style="color:#ef4444"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
}
</script>

<?php include '../includes/footer.php'; ?>