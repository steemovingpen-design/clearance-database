<?php
// Ensure session is active at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

// If student is already logged in, send them straight to dashboard
if (isset($_SESSION['student_matric'])) {
    header("Location: dashboard.php");
    exit();
}

// ==========================================
// AJAX ENDPOINT 1: FETCH FACE DESCRIPTOR
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'fetch_descriptor') {
    header('Content-Type: application/json');
    $matric_no = trim($_POST['matric_no'] ?? '');

    if (empty($matric_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid Matriculation Number.']);
        exit;
    }

    // Use prepared statements to protect against SQL Injection
    $stmt = mysqli_prepare($conn, "SELECT face_descriptor FROM students WHERE matric_no = ?");
    mysqli_stmt_bind_param($stmt, "s", $matric_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['face_descriptor'])) {
            echo json_encode(['status' => 'success', 'descriptor' => $row['face_descriptor']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No biometric profile found for this student. Contact Admin.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Matriculation number not found.']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// ==========================================
// AJAX ENDPOINT 2: SECURE SESSION AUTHENTICATION
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'authenticate_session') {
    header('Content-Type: application/json');
    $matric_no = trim($_POST['matric_no'] ?? '');

    // Verify student exists in the database
    $stmt = mysqli_prepare($conn, "SELECT id, fullname, matric_no FROM students WHERE matric_no = ?");
    mysqli_stmt_bind_param($stmt, "s", $matric_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($student = mysqli_fetch_assoc($result)) {
        // Set essential session variables upon client-side face verification
        $_SESSION['student_id'] = $student['id'];
        $_SESSION['student_matric'] = $student['matric_no'];
        $_SESSION['student_name'] = $student['fullname'];

        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Authentication failed. Student record not found.']);
    }
    mysqli_stmt_close($stmt);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElevateClear | Biometric Student Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0a1e2c 0%, #0f2b3d 100%); color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .login-card { background: rgba(255, 255, 255, 0.98); color: #1f3e4a; border-radius: 28px; padding: 40px 35px; width: 100%; max-width: 440px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .login-card h2 { font-weight: 700; color: #0f2b3d; margin-bottom: 6px; letter-spacing: -0.5px; }
        .login-card p { font-size: 0.85rem; color: #5a6e7c; margin-bottom: 24px; }
        .camera-box { width: 170px; height: 170px; background: #1a2c3e; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 4px solid #2c7da0; box-shadow: 0 8px 20px rgba(0,0,0,0.15); position: relative; }
        video { width: 100%; height: 100%; object-fit: cover; }
        input { width: 100%; padding: 14px 18px; border-radius: 50px; border: 2px solid #e2edf2; margin-bottom: 16px; font-size: 0.95rem; font-family: inherit; outline: none; transition: 0.2s; box-sizing: border-box; }
        input:focus { border-color: #2c7da0; box-shadow: 0 0 0 4px rgba(44, 125, 160, 0.15); }
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(115deg, #2c7da0, #1f4e6e); border: none; border-radius: 50px; color: white; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(44, 125, 160, 0.3); }
        .btn-submit:disabled { background: #94a3b8; cursor: not-allowed; opacity: 0.7; }
        .status-msg { font-size: 0.85rem; color: #2c7da0; font-weight: 600; margin-bottom: 18px; padding: 8px; border-radius: 12px; background: #f0f6fc; }
        .status-error { color: #e74c3c; background: #ffe8e6; }
        .status-success { color: #2ecc71; background: #eafaf1; }
    </style>
</head>
<body>

<div class="login-card">
    <h2><i class="fas fa-user-shield" style="color: #2c7da0;"></i> Student Biometric Portal</h2>
    <p>Look at the camera & enter your Matriculation Number</p>
    
    <div class="camera-box">
        <video id="video" autoplay muted playsinline></video>
    </div>
    
    <div class="status-msg" id="statusMsg"><i class="fas fa-spinner fa-spin"></i> Initializing Neural Models...</div>

    <form id="loginForm">
        <input type="text" id="matricNo" placeholder="Enter Matric Number (e.g. CS2026/001)" required autocomplete="off">
        <button type="submit" id="loginBtn" class="btn-submit" disabled>
            <i class="fas fa-id-badge"></i> Verify Face & Login
        </button>
    </form>
    <div style="margin-top: 18px; font-size: 0.85rem; color: #5a6e7c;">
        First time here? <a href="register.php" style="color: #2c7da0; font-weight: 600; text-decoration: none;">Register Biometrics &rarr;</a>
    </div>
</div>

<script>
    const video = document.getElementById('video');
    const statusMsg = document.getElementById('statusMsg');
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    function setStatus(text, type = '') {
        statusMsg.className = 'status-msg ' + (type ? 'status-' + type : '');
        statusMsg.innerHTML = text;
    }

    // Load Neural Networks and Camera Stream
    window.addEventListener('DOMContentLoaded', async () => {
        try {
            await faceapi.nets.ssdMobilenetv1.loadFromUri('../models');
            await faceapi.nets.faceLandmark68Net.loadFromUri('../models');
            await faceapi.nets.faceRecognitionNet.loadFromUri('../models');
            
            const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 } });
            video.srcObject = stream;
            await video.play();

            setStatus('<i class="fas fa-check-circle"></i> Camera active. Enter Matric No to proceed.');
            loginBtn.disabled = false;
        } catch (err) {
            setStatus('<i class="fas fa-exclamation-circle"></i> Failed to initialize camera or models.', 'error');
            console.error(err);
        }
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const matricNo = document.getElementById('matricNo').value.trim();
        
        setStatus('<i class="fas fa-spinner fa-spin"></i> Fetching registered biometric descriptor...');
        loginBtn.disabled = true;

        try {
            // STEP 1: Fetch stored face descriptor vector from database
            const response = await fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=fetch_descriptor&matric_no=${encodeURIComponent(matricNo)}`
            });
            const data = await response.json();

            if (data.status !== 'success') {
                setStatus(`<i class="fas fa-times-circle"></i> ${data.message}`, 'error');
                loginBtn.disabled = false;
                return;
            }

            const storedDescriptor = new Float32Array(JSON.parse(data.descriptor));

            // STEP 2: Capture live face from web camera
            setStatus('<i class="fas fa-camera"></i> Scanning live face structure...');
            const detection = await faceapi.detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                setStatus('<i class="fas fa-user-slash"></i> No face detected in frame. Align face with camera.', 'error');
                loginBtn.disabled = false;
                return;
            }

            // STEP 3: Calculate Euclidean Distance (Threshold < 0.50 for match)
            const distance = faceapi.euclideanDistance(detection.descriptor, storedDescriptor);
            
            if (distance < 0.50) {
                setStatus('<i class="fas fa-user-check"></i> Biometric match verified! Establishing session...', 'success');
                
                // STEP 4: Authenticate PHP session on the server side
                const authResponse = await fetch('login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=authenticate_session&matric_no=${encodeURIComponent(matricNo)}`
                });
                const authData = await authResponse.json();

                if (authData.status === 'success') {
                    setTimeout(() => { 
                        window.location.href = authData.redirect; 
                    }, 800);
                } else {
                    setStatus(`<i class="fas fa-exclamation-triangle"></i> ${authData.message}`, 'error');
                    loginBtn.disabled = false;
                }
            } else {
                setStatus('<i class="fas fa-user-times"></i> Verification failed: Face does not match account.', 'error');
                loginBtn.disabled = false;
            }
        } catch (err) {
            setStatus('<i class="fas fa-exclamation-triangle"></i> System error during verification.', 'error');
            console.error(err);
            loginBtn.disabled = false;
        }
    });
</script>
</body>
</html>