<?php
ob_start();
session_start();

// Already logged in — redirect based on role
if (isset($_SESSION['admin_id'])) {
    $role = $_SESSION['admin_role'] ?? '';
    ob_end_clean();
    header('Location: ' . ($role === 'admin' ? 'dashboard.php' : '../teacher/dashboard.php'));
    exit();
}

require_once '../includes/db.php';

$error = '';

// Flash error from session (PRG pattern)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// ── Brute Force Protection ────────────────────────────
$clientIp  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$clientIp  = filter_var(explode(',', $clientIp)[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
$maxAttempts  = 10;
$lockDuration = 3 * 5; // 15 minutes

function getRecentAttempts($conn, $ip, $lockDuration) {
    $since = date('Y-m-d H:i:s', time() - $lockDuration);
    $res = $conn->query("SELECT COUNT(*) as c FROM login_attempts WHERE ip_address='$ip' AND attempted_at > '$since'");
    return (int)($res->fetch_assoc()['c'] ?? 0);
}

function logFailedAttempt($conn, $ip, $username) {
    $u = $conn->real_escape_string($username);
    $conn->query("INSERT INTO login_attempts (ip_address, username) VALUES ('$ip', '$u')");
}

function clearAttempts($conn, $ip) {
    $conn->query("DELETE FROM login_attempts WHERE ip_address='$ip'");
}

// Clean old attempts (>1 hour)
$conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

$attempts = getRecentAttempts($conn, $clientIp, $lockDuration);
$isLocked = ($attempts >= $maxAttempts);
$lockMinsLeft = 0;
if ($isLocked) {
    $firstAttempt = $conn->query("SELECT MIN(attempted_at) as t FROM login_attempts WHERE ip_address='$clientIp' AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetch_assoc()['t'];
    $lockMinsLeft = max(1, (int)ceil(($lockDuration - (time() - strtotime($firstAttempt))) / 60));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLocked) {
        $error = "Too many failed attempts. Please wait {$lockMinsLeft} minute(s) before trying again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $error = 'Please enter username and password.';
        } else {
            // Check both admin and teacher roles
            $stmt = $conn->prepare("
                SELECT * FROM users
                WHERE (username = ? OR email = ?)
                  AND role IN ('admin','teacher')
                  AND status = 'active'
                LIMIT 1
            ");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // ── Session security ────────────────────
                    session_regenerate_id(true); // prevent session fixation
                    clearAttempts($conn, $clientIp);

                    $_SESSION['admin_id']     = $user['id'];
                    $_SESSION['admin_name']   = $user['full_name'];
                    $_SESSION['admin_role']   = $user['role'];
                    $_SESSION['admin_photo']  = $user['photo'];
                    $_SESSION['login_time']   = time();
                    $_SESSION['login_ip']     = $clientIp;

                    if ($user['role'] === 'teacher') {
                        $_SESSION['teacher_id']    = $user['id'];
                        $_SESSION['teacher_name']  = $user['full_name'];
                        $_SESSION['teacher_role']  = $user['role'];
                        $_SESSION['teacher_photo'] = $user['photo'];
                    }

                    $stmt->close();
                    ob_end_clean();

                    header('Location: ' . ($user['role'] === 'admin' ? 'dashboard.php' : '../teacher/dashboard.php'));
                    exit();
                } else {
                    logFailedAttempt($conn, $clientIp, $username);
                    $attempts++;
                    $remaining = $maxAttempts - $attempts;
                    $_SESSION['login_error'] = $remaining > 0
                        ? "Invalid username or password. {$remaining} attempt(s) remaining."
                        : "Too many failed attempts. Account locked for 15 minutes.";
                    ob_end_clean();
                    header('Location: login.php');
                    exit();
                }
            } else {
                logFailedAttempt($conn, $clientIp, $username);
                $attempts++;
                $remaining = $maxAttempts - $attempts;
                $_SESSION['login_error'] = $remaining > 0
                    ? "Invalid username or password. {$remaining} attempt(s) remaining."
                    : "Too many failed attempts. Account locked for 15 minutes.";
                ob_end_clean();
                header('Location: login.php');
                exit();
            }
            if (isset($stmt)) $stmt->close();
        }
    }
}

$schoolName = getSetting($conn, 'school_name');
$schoolMotto = getSetting($conn, 'school_motto');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — <?= htmlspecialchars($schoolName) ?></title>
    <link rel="stylesheet" href="../assets/vendors/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendors/fonts/poppins.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #0d3d1c 0%, #1b6b35 50%, #0f3a1a 100%);
            position: relative;
            overflow: hidden;
        }
        /* Background pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.05) 1px, transparent 1px),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 1;
        }

        /* School header */
        .school-header {
            text-align: center;
            margin-bottom: 28px;
            color: white;
        }
        .school-header img {
            width: 72px; height: 72px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            margin-bottom: 12px;
        }
        .school-header h2 {
            font-size: 18px;
            font-weight: 800;
            color: white;
            line-height: 1.3;
            margin-bottom: 4px;
        }
        .school-header p {
            font-size: 12.5px;
            color: rgba(255,255,255,0.7);
        }

        /* Login Card */
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.35);
        }
        .login-card-title {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-card-title h4 {
            font-size: 20px;
            font-weight: 800;
            color: #1b6b35;
            margin-bottom: 6px;
        }
        .login-card-title p {
            font-size: 13px;
            color: #6b7c72;
        }

        /* Role indicator tabs */
        .role-tabs {
            display: flex;
            background: #f5f7f6;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 24px;
            gap: 4px;
        }
        .role-tab {
            flex: 1;
            text-align: center;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            color: #6b7c72;
            cursor: default;
            transition: all 0.25s;
        }
        .role-tab i { margin-right: 6px; }
        .role-tab.admin-tab  { background: transparent; }
        .role-tab.teacher-tab { background: transparent; }

        /* Form styles */
        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #1a2e1e;
            margin-bottom: 6px;
        }
        .form-control {
            border: 2px solid #dde8e1;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            transition: all 0.25s;
            font-family: 'Poppins', sans-serif;
        }
        .form-control:focus {
            border-color: #1b6b35;
            box-shadow: 0 0 0 3px rgba(27,107,53,0.12);
            outline: none;
        }
        .input-group-text {
            background: #f5f7f6;
            border: 2px solid #dde8e1;
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: #1b6b35;
            padding: 0 14px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .input-group .form-control:focus {
            border-left: none;
        }
        .input-group:focus-within .input-group-text {
            border-color: #1b6b35;
        }
        .toggle-pwd {
            border: 2px solid #dde8e1;
            border-left: none;
            border-radius: 0 10px 10px 0;
            background: #f5f7f6;
            padding: 0 14px;
            cursor: pointer;
            color: #6b7c72;
            transition: all 0.2s;
        }
        .toggle-pwd:hover { color: #1b6b35; }

        /* Login button */
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #0d3d1c, #1b6b35);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 4px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(27,107,53,0.35);
        }
        .btn-login:active { transform: translateY(0); }

        /* Role indicator (dynamic) */
        .role-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1.5px solid;
            display: none;
        }
        .role-indicator.admin {
            background: #e8f5ec;
            border-color: #1b6b35;
            color: #1b6b35;
        }
        .role-indicator.teacher {
            background: #fdf0ef;
            border-color: #b5281f;
            color: #b5281f;
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 22px;
        }
        .back-link a {
            color: rgba(255,255,255,0.75);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link a:hover { color: #ffc53d; }

        /* Info note */
        .login-info {
            text-align: center;
            margin-top: 18px;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        @media (max-width: 480px) {
            .login-card { padding: 32px 24px; }
            .school-header img { width: 60px; height: 60px; }
            .school-header h2 { font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">

        <!-- School Header -->
        <div class="school-header">
            <img src="../assets/images/logo.jpg" alt="Logo"
                 onerror="this.src='https://ui-avatars.com/api/?name=HPSS&background=1b6b35&color=fff&size=72'">
            <h2><?= htmlspecialchars($schoolName) ?></h2>
            <p><?= htmlspecialchars($schoolMotto) ?></p>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <div class="login-card-title">
                <h4><i class="fas fa-sign-in-alt me-2"></i>Staff Login</h4>
                <!-- <p>Admin ra Teacher duitai yही login page use garnu</p> -->
            </div>

            <!-- Error Message -->
            <?php if($error): ?>
            <div class="alert alert-danger d-flex align-items-start gap-2 mb-4"
                 style="font-size:13.5px;border-radius:10px;border:none;background:#fdf0ef;color:#7c1a14;">
                <i class="fas fa-exclamation-circle mt-1 flex-shrink-0"></i>
                <div>
                    <strong><?= $isLocked ? 'Account Locked' : 'Login Failed' ?></strong><br>
                    <?= htmlspecialchars($error) ?>
                    <?php if(!$isLocked): ?>
                    <div style="font-size:12px;margin-top:4px;opacity:0.8;">
                        Admin ra Teacher duitai yahi page bata login garnu.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if($isLocked && !$error): ?>
            <div class="alert d-flex align-items-start gap-2 mb-4"
                 style="font-size:13.5px;border-radius:10px;background:#fdf0ef;color:#7c1a14;border:none;">
                <i class="fas fa-lock mt-1 flex-shrink-0"></i>
                <div><strong>Account Locked</strong><br>
                    Too many failed attempts. Please wait <?= $lockMinsLeft ?> minute(s).
                </div>
            </div>
            <?php endif; ?>

            <!-- Role indicator — shows dynamically -->
            <div class="role-indicator admin" id="roleAdmin">
                <i class="fas fa-shield-alt"></i>
                Admin account detected — will open Admin Dashboard
            </div>
            <div class="role-indicator teacher" id="roleTeacher">
                <i class="fas fa-chalkboard-teacher"></i>
                Teacher account detected — will open Teacher Portal
            </div>

            <!-- Login Form -->
            <form method="POST" autocomplete="off" id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" id="usernameInput"
                               class="form-control"
                               placeholder="Enter your username or email"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required autocomplete="username">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="pwdInput"
                               class="form-control"
                               placeholder="Enter your password"
                               required autocomplete="current-password">
                        <button type="button" class="toggle-pwd" id="togglePwd">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Role chips -->
                <div class="role-tabs mb-4">
                    <div class="role-tab admin-tab" id="tabAdmin">
                        <i class="fas fa-user-shield"></i>Admin
                    </div>
                    <div style="display:flex;align-items:center;color:#dde8e1;font-size:18px;">|</div>
                    <div class="role-tab teacher-tab" id="tabTeacher">
                        <i class="fas fa-chalkboard-teacher"></i>Teacher
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn" <?= $isLocked ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-in-alt me-2"></i><?= $isLocked ? 'Account Locked' : 'Login' ?>
                </button>
            </form>
        </div>

        <!-- Back link -->
        <div class="back-link">
            <a href="../index.php">
                <i class="fas fa-arrow-left me-1"></i>Back to Website
            </a>
        </div>
    </div>

    <script src="../assets/vendors/bootstrap/jquery.min.js"></script>
    <script src="../assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function(){

        // Password toggle
        $('#togglePwd').on('click', function(){
            const inp = $('#pwdInput');
            const icon = $('#eyeIcon');
            if(inp.attr('type') === 'password'){
                inp.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                inp.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        // Known admin usernames — highlight tab
        const knownAdmins = ['admin'];

        function updateRoleTabs() {
            const val = $('#usernameInput').val().toLowerCase().trim();
            $('#roleAdmin, #roleTeacher').hide();
            $('#tabAdmin, #tabTeacher').css({'background':'transparent','color':'#6b7c72'});

            if (!val) return;

            // If it matches known admin usernames — show admin tab
            if (knownAdmins.indexOf(val) !== -1) {
                $('#tabAdmin').css({'background':'#1b6b35','color':'white'});
                $('#roleAdmin').show();
            } else if (val.length >= 3) {
                // Otherwise assume teacher
                $('#tabTeacher').css({'background':'#b5281f','color':'white'});
                $('#roleTeacher').show();
            }
        }

        $('#usernameInput').on('input', updateRoleTabs);
        updateRoleTabs(); // on page load if value present

        // Loading state on submit
        $('#loginForm').on('submit', function(){
            $('#loginBtn').html('<span class="spinner-border spinner-border-sm me-2"></span>Logging in...')
                          .prop('disabled', true);
        });
    });
    </script>
</body>
</html>
