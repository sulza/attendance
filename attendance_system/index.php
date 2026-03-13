<?php
// 1. Buffering prevents "headers already sent" errors
ob_start(); 

require_once 'config/database.php';
require_once 'includes/functions.php';

// 2. Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Handle Auto-Redirect for logged-in users
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    $dashboards = [
        'admin'    => 'admin/dashboard.php',
        'hod'      => 'hod/dashboard.php',
        'lecturer' => 'lecturer/dashboard.php'
    ];

    if (array_key_exists($role, $dashboards)) {
        header('Location: ' . BASE_URL . $dashboards[$role]);
        exit;
    }
}

// 4. Handle Login Logic
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['email']) ? clean($_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $conn = getDBConnection();
        
        // Securely prepare query
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

 
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . (int)$user['id']);
            
            // Log Activity
            logActivity($user['id'], 'LOGIN', 'User logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            // Set Sessions
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['dept_id']   = $user['department_id'];

            // Redirect
            $redirect = ($user['role'] === 'admin' ? 'admin/dashboard.php' : ($user['role'] === 'hod' ? 'hod/dashboard.php' : 'lecturer/dashboard.php'));
            header('Location: ' . BASE_URL . $redirect);
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — VLA Attendance System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-container">
    <div class="login-banner">
      <div>
        <div class="login-logo">VLA <span>Visiting Lecturer Attendance</span></div>
        <p style="font-size:13px;opacity:.8;margin-top:12px;line-height:1.6">A modern, comprehensive attendance management solution for visiting lecturers.</p>
      </div>
      <div>
        <div class="login-tagline">Track. Verify.<br>Manage Seamlessly.</div>
        <ul class="login-features" style="margin-top:20px">
          <li><i class="fas fa-check"></i> Real-time attendance logging</li>
          <li><i class="fas fa-shield-alt"></i> Role-based access control</li>
          <li><i class="fas fa-file-download"></i> Exportable reports & analytics</li>
          <li><i class="fas fa-bell"></i> Instant notifications</li>
          <li><i class="fas fa-certificate"></i> HOD verification workflow</li>
        </ul>
      </div>
      <div style="font-size:12px;opacity:.5">&copy; <?= date('Y') ?> University Attendance Management</div>
    </div>

    <div class="login-form-side">
      <h2>Welcome Back</h2>
      <p>Sign in to your account to continue</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error) ?></span></div>
      <?php endif ?>

      <form method="POST" class="login-form">
        <div class="form-group">
          <label>Email Address <span class="req">*</span></label>
          <div class="input-icon">
            <i class="fas fa-envelope"></i>
            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Password <span class="req">*</span></label>
          <div class="input-icon">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" id="pwdInput" class="form-control" required>
            <button type="button" class="password-toggle" id="toggleBtn"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary login-btn">Sign In</button>
      </form>

      <div style="margin-top:28px;padding:14px;background:#f8f9fa;border-radius:8px;font-size:12px">
        <strong style="color:#6c757d">Demo Credentials</strong>
        <div style="display:grid;gap:4px;color:#adb5bd;margin-top:5px">
          <span><b>Admin:</b> admin@university.edu</span>
          <span><b>HOD:</b> hod.csc@university.edu</span>
          <span><b>Lecturer:</b> lecturer1@university.edu</span>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
    document.getElementById('toggleBtn')?.addEventListener('click', function() {
        const pwd = document.getElementById('pwdInput');
        pwd.type = pwd.type === 'password' ? 'text' : 'password';
    });
</script>
</body>
</html>