<?php
// ==========================================
// 1. SYSTEM INITIALIZATION & ERROR REPORTING
// ==========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// 2. DATABASE CONFIGURATION DISCOVERY
// ==========================================
if (file_exists('../includes/config.php')) {
    require_once '../includes/config.php';
} else {
    die("<div style='color:red; font-family:Arial; padding:20px; background:#fff5f5; border:1px solid red; border-radius:4px;'>
            <strong>Critical System Error:</strong> Connection file missing at <code>../includes/config.php</code>.
         </div>");
}

$message = "";

// ==========================================
// 3. SECURE FORM SUBMISSION PROCESSING
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Scrub inputs to defend against SQL Injection
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $message = "Please complete both fields to authenticate.";
    } else {
        // Locate user in database using prepared statement
        $stmt = mysqli_prepare($conn, "SELECT * FROM officers WHERE username = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) == 1) {
                $officer = mysqli_fetch_assoc($result);
                
                // Password Validation Engine (Supports plain-text or password_verify)
                if ($password === $officer['password'] || password_verify($password, $officer['password'])) {
                    
                    // Establish session tokens mapping to your system anchors
                    $_SESSION['officer_id'] = $officer['id'];
                    $_SESSION['officer_name'] = $officer['officer_name'] ?? ($officer['fullname'] ?? 'Clearance Officer');
                    $_SESSION['officer_unit'] = $officer['unit_name'] ?? ''; 
                    
                    // Redirect immediately to the dashboard
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $message = "Security Violation: Invalid password credentials.";
                }
            } else {
                $message = "Access Denied: Administrative username not registered.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "Database query error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Officer Portal | Secure Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0b2b3b 0%, #1a4a5f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(72, 201, 176, 0.1) 0%, transparent 70%);
            top: -150px;
            right: -100px;
            border-radius: 50%;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(46, 204, 113, 0.08) 0%, transparent 70%);
            bottom: -200px;
            left: -150px;
            border-radius: 50%;
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            z-index: 2;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 48px;
            padding: 48px 40px 52px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 32px 60px -16px rgba(0, 0, 0, 0.4);
        }

        .icon-wrapper {
            text-align: center;
            margin-bottom: 24px;
        }

        .shield-icon {
            background: linear-gradient(145deg, #1f5e6e, #0e3f4b);
            width: 80px;
            height: 80px;
            border-radius: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.25);
            margin-bottom: 8px;
        }

        .shield-icon i {
            font-size: 44px;
            color: #7fffd4;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .login-card h2 {
            font-size: 1.9rem;
            font-weight: 700;
            text-align: center;
            background: linear-gradient(135deg, #1F4B5E, #2C7A6E);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
            margin-bottom: 8px;
        }

        .sub-text {
            text-align: center;
            color: #6a8ba3;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 32px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eef2f8;
            display: inline-block;
            width: auto;
            margin-left: auto;
            margin-right: auto;
        }

        .alert-modern {
            padding: 14px 20px;
            border-radius: 28px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            background: #fff5f5;
            border-left: 4px solid #e74c3c;
            color: #c0392b;
            animation: shake 0.4s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .alert-modern i { font-size: 1.2rem; }

        .form-group { margin-bottom: 24px; }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon { position: absolute; left: 18px; color: #8ba3b5; font-size: 1rem; pointer-events: none; transition: all 0.2s; }

        .form-group input {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border: 2px solid #e2edf2;
            border-radius: 34px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: all 0.25s;
            background: #ffffff;
            color: #1f3e4a;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2c7a6e;
            box-shadow: 0 0 0 4px rgba(44, 122, 110, 0.12);
        }

        .form-group input::placeholder { color: #b9cfdf; font-weight: 400; }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            margin-left: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #4f7a92;
        }

        .toggle-password {
            position: absolute;
            right: 18px;
            background: none;
            border: none;
            color: #8ba3b5;
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            transition: color 0.2s;
        }

        .toggle-password:hover { color: #2c7a6e; }

        .btn-auth {
            width: 100%;
            padding: 15px 20px;
            background: linear-gradient(105deg, #1f4b5e, #2c7a6e);
            border: none;
            border-radius: 44px;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 8px;
            box-shadow: 0 6px 14px rgba(28, 85, 78, 0.25);
        }

        .btn-auth i { font-size: 1rem; transition: transform 0.2s; }
        .btn-auth:hover {
            transform: translateY(-2px);
            background: linear-gradient(105deg, #1a4153, #236857);
            box-shadow: 0 12px 22px rgba(28, 85, 78, 0.35);
        }
        .btn-auth:hover i { transform: translateX(4px); }
        .btn-auth:active { transform: translateY(1px); }

        .secure-footer {
            margin-top: 32px;
            text-align: center;
            font-size: 0.7rem;
            color: #94aebb;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .secure-footer i { font-size: 0.65rem; }
        .secure-footer span { background: #eef3f8; padding: 5px 12px; border-radius: 30px; }

        @media (max-width: 520px) {
            .login-card { padding: 36px 28px 42px; }
            .login-card h2 { font-size: 1.6rem; }
            .shield-icon { width: 65px; height: 65px; }
            .shield-icon i { font-size: 34px; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="icon-wrapper">
            <div class="shield-icon">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
        <h2>Officer Portal</h2>
        <div style="text-align: center;">
            <div class="sub-text"><i class="fas fa-fingerprint"></i> Authorized clearance personnel only</div>
        </div>

        <?php if(!empty($message)): ?>
            <div class="alert-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label><i class="fas fa-id-card"></i> USERNAME / AGENT ID</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" required placeholder="e.g., josh_bursary" autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> SECURITY PASSWORD</label>
                <div class="input-wrapper">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password" name="password" id="passwordField" required placeholder="Enter your password">
                    <button type="button" class="toggle-password" id="togglePasswordBtn" tabindex="-1">
                        <i class="far fa-eye-slash" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-auth">
                <span>Authenticate Secure Connection</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="secure-footer">
            <span><i class="fas fa-shield-alt"></i> 256-bit encrypted channel</span>
            <span><i class="fas fa-clock"></i> Session timeout: 30min</span>
        </div>
    </div>
</div>

<script>
    (function() {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('passwordField');
        const eyeIcon = document.getElementById('eyeIcon');

        if (toggleBtn && passwordInput && eyeIcon) {
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                if (type === 'text') {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            });
        }
    })();
</script>
</body>
</html>