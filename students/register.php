<?php
// Ensure session is initialized
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

// Ensure upload directory for student photos exists
$photo_target_dir = "../uploads/students_photos/";
if (!file_exists($photo_target_dir)) {
    mkdir($photo_target_dir, 0777, true);
}

// =========================================================
// AJAX ENDPOINT 1: FETCH DATA FROM ACADEMIC AFFAIRS DATABASE
// =========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'fetch_academic_record') {
    header('Content-Type: application/json');
    $matric_no = trim($_POST['matric_no'] ?? '');

    if (empty($matric_no)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid Matriculation Number.']);
        exit;
    }

    // Check if student is already registered in the biometric students table
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE matric_no = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $matric_no);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);

    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'This student has already completed biometric registration. Redirecting to login...',
            'already_registered' => true
        ]);
        mysqli_stmt_close($check_stmt);
        exit;
    }
    mysqli_stmt_close($check_stmt);

    // Fetch official student details from academic_affairs_data
    $stmt = mysqli_prepare($conn, "SELECT matric_no, fullname, email, phone, faculty, department, level FROM academic_affairs_data WHERE matric_no = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Query error: ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $matric_no);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($academic_record = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'status' => 'success',
            'data'   => $academic_record
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Matriculation number not found in Academic Affairs Database. Please contact administration.'
        ]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// =========================================================
// AJAX ENDPOINT 2: PROCESS & SAVE BIOMETRIC REGISTRATION & PHOTO
// =========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'register_student') {
    header('Content-Type: application/json');

    $matric_no   = trim($_POST['matric_no'] ?? '');
    $fullname    = trim($_POST['fullname'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $faculty     = trim($_POST['faculty'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $level       = trim($_POST['level'] ?? '');
    $descriptor  = trim($_POST['descriptor'] ?? '');
    $face_image  = trim($_POST['face_image'] ?? '');

    if (empty($matric_no) || empty($fullname) || empty($email) || empty($descriptor)) {
        echo json_encode(['status' => 'error', 'message' => 'All academic fields and a face scan must be captured before registration.']);
        exit;
    }

    // Check once more if already registered
    $check_dup = mysqli_prepare($conn, "SELECT id FROM students WHERE matric_no = ?");
    mysqli_stmt_bind_param($check_dup, "s", $matric_no);
    mysqli_stmt_execute($check_dup);
    mysqli_stmt_store_result($check_dup);
    if (mysqli_stmt_num_rows($check_dup) > 0) {
        mysqli_stmt_close($check_dup);
        echo json_encode(['status' => 'error', 'message' => 'Student record already exists. Please proceed to login.', 'already_registered' => true]);
        exit;
    }
    mysqli_stmt_close($check_dup);

    // Save captured face image to disk
    $image_db_path = null;
    if (!empty($face_image)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $face_image)) {
            $base64_data = substr($face_image, strpos($face_image, ',') + 1);
            $decoded_data = base64_decode($base64_data);
            if ($decoded_data !== false) {
                $clean_matric = preg_replace('/[^A-Za-z0-9_-]/', '-', $matric_no);
                $file_name = $clean_matric . "_" . time() . ".jpg";
                $dest_path = $photo_target_dir . $file_name;
                if (file_put_contents($dest_path, $decoded_data)) {
                    $image_db_path = "uploads/students_photos/" . $file_name;
                }
            }
        }
    }

    // Clear any active sessions
    session_unset();
    session_destroy();

    // Save student record with biometric vector and captured face photo
    $sql = "INSERT INTO students (matric_no, fullname, email, phone, faculty, department, level, face_descriptor, face_image) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "sssssssss", $matric_no, $fullname, $email, $phone, $faculty, $department, $level, $descriptor, $image_db_path);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Biometric registration complete! Your facial identity has been verified and saved.',
            'redirect' => 'login.php'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to store registration record: ' . mysqli_error($conn)]);
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
    <title>ElevateClear | Student Biometric Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0a1e2c 0%, #0f2b3d 100%); color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 30px 20px; }
        .reg-card { background: rgba(255, 255, 255, 0.98); color: #1f3e4a; border-radius: 28px; padding: 35px 40px; width: 100%; max-width: 820px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        .reg-header { text-align: center; margin-bottom: 24px; }
        .reg-header h2 { font-weight: 700; color: #0f2b3d; margin-bottom: 6px; }
        .reg-header p { font-size: 0.88rem; color: #5a6e7c; }
        
        .grid-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }
        @media (max-width: 768px) { .grid-container { grid-template-columns: 1fr; } }

        .camera-section { text-align: center; }
        .camera-box { width: 210px; height: 210px; background: #1a2c3e; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 4px solid #2c7da0; box-shadow: 0 8px 20px rgba(0,0,0,0.15); position: relative; }
        video { width: 100%; height: 100%; object-fit: cover; }
        #capturedPhotoPreview { width: 100%; height: 100%; object-fit: cover; display: none; }
        .captured-badge { position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); background: rgba(46, 204, 113, 0.95); color: white; padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: none; }

        .search-box { display: flex; gap: 8px; margin-bottom: 16px; }
        .form-group { margin-bottom: 12px; text-align: left; }
        .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: #0f2b3d; margin-bottom: 4px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 10px 14px; border-radius: 10px; border: 2px solid #e2edf2; font-size: 0.88rem; font-family: inherit; outline: none; transition: 0.2s; }
        .form-control:focus { border-color: #2c7da0; box-shadow: 0 0 0 3px rgba(44, 125, 160, 0.15); }
        .form-control[readonly] { background-color: #f4f8fa; color: #5a6e7c; cursor: not-allowed; }

        .btn-action { padding: 11px 18px; background: #2c7da0; border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: 0.2s; white-space: nowrap; }
        .btn-action:hover:not(:disabled) { background: #1f4e6e; }
        .btn-retake { background: #e74c3c; display: none; margin-top: 8px; }
        .btn-retake:hover:not(:disabled) { background: #c0392b; }
        
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(115deg, #2c7da0, #1f4e6e); border: none; border-radius: 50px; color: white; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 15px; }
        .btn-submit:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(44, 125, 160, 0.3); }
        .btn-submit:disabled, .btn-action:disabled { background: #94a3b8; cursor: not-allowed; opacity: 0.7; }

        .status-msg { font-size: 0.85rem; color: #2c7da0; font-weight: 600; margin-bottom: 18px; padding: 10px; border-radius: 12px; background: #f0f6fc; text-align: center; }
        .status-error { color: #e74c3c; background: #ffe8e6; }
        .status-success { color: #2ecc71; background: #eafaf1; }

        .login-link { text-align: center; margin-top: 18px; font-size: 0.85rem; color: #5a6e7c; }
        .login-link a { color: #2c7da0; font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="reg-card">
    <div class="reg-header">
        <h2><i class="fas fa-user-plus" style="color: #2c7da0;"></i> Biometric Registration</h2>
        <p>Fetch your academic record, verify details, and capture your facial identity</p>
    </div>

    <div class="status-msg" id="statusMsg"><i class="fas fa-spinner fa-spin"></i> Initializing Neural Models...</div>

    <div class="grid-container">
        <div class="camera-section">
            <div class="camera-box">
                <video id="video" autoplay muted playsinline></video>
                <img id="capturedPhotoPreview" alt="Captured Face Snapshot">
                <div class="captured-badge" id="capturedBadge"><i class="fas fa-check"></i> Face Captured</div>
            </div>
            
            <button type="button" id="captureBtn" class="btn-action" style="width: 100%; border-radius: 50px;" disabled>
                <i class="fas fa-camera"></i> Capture Face Scan
            </button>
            <button type="button" id="retakeBtn" class="btn-action btn-retake" style="width: 100%; border-radius: 50px;">
                <i class="fas fa-redo"></i> Retake Photo
            </button>
            <canvas id="snapshotCanvas" style="display:none;"></canvas>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label>Matriculation Number</label>
                <div class="search-box">
                    <input type="text" id="matricNo" class="form-control" placeholder="e.g. CS2026/001" required autocomplete="off">
                    <button type="button" id="fetchRecordBtn" class="btn-action">
                        <i class="fas fa-search"></i> Fetch Data
                    </button>
                </div>
            </div>

            <form id="regForm">
                <input type="hidden" id="faceDescriptor" name="descriptor">
                <input type="hidden" id="faceImage" name="face_image">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="fullname" class="form-control" readonly required placeholder="Auto-populated">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="email" class="form-control" readonly required placeholder="Auto-populated">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" class="form-control" readonly required placeholder="Auto-populated">
                </div>
                <div class="form-group">
                    <label>Faculty</label>
                    <input type="text" id="faculty" class="form-control" readonly required placeholder="Auto-populated">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="department" class="form-control" readonly required placeholder="Auto-populated">
                </div>
                <div class="form-group">
                    <label>Level</label>
                    <input type="text" id="level" class="form-control" readonly required placeholder="Auto-populated">
                </div>

                <button type="submit" id="submitBtn" class="btn-submit" disabled>
                    <i class="fas fa-user-check"></i> Complete Registration
                </button>
            </form>

            <div class="login-link">
                Already registered? <a href="login.php">Sign in with Face ID</a>
            </div>
        </div>
    </div>
</div>

<script>
    const video = document.getElementById('video');
    const photoPreview = document.getElementById('capturedPhotoPreview');
    const capturedBadge = document.getElementById('capturedBadge');
    const statusMsg = document.getElementById('statusMsg');
    const captureBtn = document.getElementById('captureBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    const fetchRecordBtn = document.getElementById('fetchRecordBtn');
    const submitBtn = document.getElementById('submitBtn');
    const regForm = document.getElementById('regForm');
    const faceDescriptorInput = document.getElementById('faceDescriptor');
    const faceImageInput = document.getElementById('faceImage');
    const snapshotCanvas = document.getElementById('snapshotCanvas');

    let modelsReady = false;
    let academicRecordLoaded = false;
    let faceCaptured = false;
    let mediaStream = null;

    function setStatus(text, type = '') {
        statusMsg.className = 'status-msg ' + (type ? 'status-' + type : '');
        statusMsg.innerHTML = text;
    }

    function checkReadyState() {
        submitBtn.disabled = !(academicRecordLoaded && faceCaptured);
    }

    async function startCamera() {
        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: "user" } 
            });
            video.srcObject = mediaStream;
            await video.play();
            return true;
        } catch (err) {
            console.error("Camera stream error:", err);
            return false;
        }
    }

    window.addEventListener('DOMContentLoaded', async () => {
        try {
            await faceapi.nets.ssdMobilenetv1.loadFromUri('../models');
            await faceapi.nets.faceLandmark68Net.loadFromUri('../models');
            await faceapi.nets.faceRecognitionNet.loadFromUri('../models');

            const cameraStarted = await startCamera();
            if (cameraStarted) {
                modelsReady = true;
                setStatus('<i class="fas fa-check-circle"></i> Camera active. Enter Matric No and click "Fetch Data".');
                captureBtn.disabled = false;
            } else {
                setStatus('<i class="fas fa-exclamation-circle"></i> Camera permission denied or device not found.', 'error');
            }
        } catch (err) {
            setStatus('<i class="fas fa-exclamation-circle"></i> Failed to initialize camera or neural models.', 'error');
            console.error(err);
        }
    });

    fetchRecordBtn.addEventListener('click', async () => {
        const matricNo = document.getElementById('matricNo').value.trim();
        if (!matricNo) {
            setStatus('<i class="fas fa-exclamation-circle"></i> Please enter a Matriculation Number first.', 'error');
            return;
        }

        setStatus('<i class="fas fa-spinner fa-spin"></i> Fetching official record from Academic Affairs...');
        fetchRecordBtn.disabled = true;

        try {
            const response = await fetch('register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=fetch_academic_record&matric_no=${encodeURIComponent(matricNo)}`
            });

            const result = await response.json();

            if (result.status === 'success') {
                const rec = result.data;
                document.getElementById('fullname').value   = rec.fullname || '';
                document.getElementById('email').value      = rec.email || '';
                document.getElementById('phone').value      = rec.phone || '';
                document.getElementById('faculty').value    = rec.faculty || '';
                document.getElementById('department').value = rec.department || '';
                document.getElementById('level').value      = rec.level || '';

                academicRecordLoaded = true;
                setStatus('<i class="fas fa-check-circle"></i> Academic record retrieved! Now click "Capture Face Scan".', 'success');
                checkReadyState();
            } else {
                setStatus(`<i class="fas fa-times-circle"></i> ${result.message}`, 'error');
                academicRecordLoaded = false;

                if (result.already_registered) {
                    setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                }
            }
        } catch (err) {
            setStatus('<i class="fas fa-exclamation-triangle"></i> Network error while fetching academic record.', 'error');
            console.error(err);
        } finally {
            fetchRecordBtn.disabled = false;
        }
    });

    captureBtn.addEventListener('click', async () => {
        if (!modelsReady) return;

        setStatus('<i class="fas fa-spinner fa-spin"></i> Analyzing facial structure & capturing snapshot...');
        captureBtn.disabled = true;

        const detection = await faceapi.detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            setStatus('<i class="fas fa-user-slash"></i> No face detected. Align face clearly in frame.', 'error');
            captureBtn.disabled = false;
            return;
        }

        // 1. Capture snapshot to hidden canvas
        snapshotCanvas.width = video.videoWidth || 320;
        snapshotCanvas.height = video.videoHeight || 240;
        const ctx = snapshotCanvas.getContext('2d');
        ctx.drawImage(video, 0, 0, snapshotCanvas.width, snapshotCanvas.height);
        const photoDataUrl = snapshotCanvas.toDataURL('image/jpeg', 0.88);

        // 2. Store values in hidden inputs
        faceDescriptorInput.value = JSON.stringify(Array.from(detection.descriptor));
        faceImageInput.value = photoDataUrl;
        faceCaptured = true;

        // 3. Display captured preview image
        photoPreview.src = photoDataUrl;
        photoPreview.style.display = 'block';
        video.style.display = 'none';
        capturedBadge.style.display = 'block';
        captureBtn.style.display = 'none';
        retakeBtn.style.display = 'block';

        setStatus('<i class="fas fa-check-circle"></i> Biometric scan & photo captured successfully! Ready to complete registration.', 'success');
        checkReadyState();
    });

    retakeBtn.addEventListener('click', () => {
        photoPreview.style.display = 'none';
        capturedBadge.style.display = 'none';
        video.style.display = 'block';
        captureBtn.style.display = 'block';
        captureBtn.disabled = false;
        retakeBtn.style.display = 'none';

        faceCaptured = false;
        faceDescriptorInput.value = '';
        faceImageInput.value = '';
        checkReadyState();
        setStatus('<i class="fas fa-camera"></i> Align your face and click "Capture Face Scan".');
    });

    regForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!academicRecordLoaded || !faceCaptured) {
            setStatus('<i class="fas fa-exclamation-triangle"></i> Complete academic lookup and face scan first.', 'error');
            return;
        }

        setStatus('<i class="fas fa-spinner fa-spin"></i> Saving biometric identity & creating account...');
        submitBtn.disabled = true;

        const formData = new URLSearchParams();
        formData.append('action', 'register_student');
        formData.append('matric_no', document.getElementById('matricNo').value.trim());
        formData.append('fullname', document.getElementById('fullname').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('phone', document.getElementById('phone').value);
        formData.append('faculty', document.getElementById('faculty').value);
        formData.append('department', document.getElementById('department').value);
        formData.append('level', document.getElementById('level').value);
        formData.append('descriptor', faceDescriptorInput.value);
        formData.append('face_image', faceImageInput.value);

        try {
            const response = await fetch('register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const result = await response.json();

            if (result.status === 'success') {
                setStatus(`<i class="fas fa-check-circle"></i> ${result.message}`, 'success');
                setTimeout(() => { 
                    window.location.href = result.redirect || 'login.php'; 
                }, 1400);
            } else {
                setStatus(`<i class="fas fa-times-circle"></i> ${result.message}`, 'error');
                submitBtn.disabled = false;
                if (result.already_registered) {
                    setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                }
            }
        } catch (err) {
            setStatus('<i class="fas fa-exclamation-triangle"></i> Error submitting registration.', 'error');
            console.error(err);
            submitBtn.disabled = false;
        }
    });
</script>
</body>
</html>