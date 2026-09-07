<?php
// students/dashboard.php

require_once '../includes/config.php';

// Session guard: Kick out users who aren't logged in
if (!isset($_SESSION['student_matric'])) {
    header("Location: login.php");
    exit();
}

$matric_no = $_SESSION['student_matric'];
$message = "";
$messageType = "";

// Ensure upload directories exist safely on the server
$target_dir = "../uploads/documents/";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// ==========================================
// HANDLE DOCUMENT UPLOAD ACTION
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_doc'])) {
    $unit_name = trim($_POST['unit_name'] ?? '');
    $valid_units = ['Bursary', 'Library', 'Dean', 'Medical', 'SUG', 'Department'];

    if (!in_array($unit_name, $valid_units)) {
        $message = "Invalid clearance unit selected.";
        $messageType = "danger";
    } elseif (isset($_FILES['clearance_file']) && $_FILES['clearance_file']['error'] == 0) {
        $file_name = $_FILES['clearance_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = array('pdf', 'jpg', 'jpeg', 'png');

        if (in_array($file_ext, $allowed_exts)) {
            $clean_matric = preg_replace('/[^A-Za-z0-9_-]/', '-', $matric_no);
            $new_file_name = $clean_matric . "_" . $unit_name . "_" . time() . "." . $file_ext;
            $dest_path = $target_dir . $new_file_name;

            if (move_uploaded_file($_FILES['clearance_file']['tmp_name'], $dest_path)) {
                $db_path = "uploads/documents/" . $new_file_name;
                
                // Using prepared statement for security
                $query = "INSERT INTO clearance_documents (matric_no, unit_name, document_path, status, rejection_comment) 
                          VALUES (?, ?, ?, 'Pending', NULL)
                          ON DUPLICATE KEY UPDATE document_path = VALUES(document_path), status = 'Pending', rejection_comment = NULL";
                
                $stmt = mysqli_prepare($conn, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sss", $matric_no, $unit_name, $db_path);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Document uploaded successfully for $unit_name unit!";
                        $messageType = "success";
                    } else {
                        $message = "Database logging error: " . mysqli_error($conn);
                        $messageType = "danger";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $message = "Query preparation failed: " . mysqli_error($conn);
                    $messageType = "danger";
                }
            } else {
                $message = "File system routing error. Check folder permissions.";
                $messageType = "danger";
            }
        } else {
            $message = "Invalid file type. Only PDF, JPG, JPEG, and PNG are allowed.";
            $messageType = "danger";
        }
    } else {
        $message = "Please select a valid file before submitting.";
        $messageType = "danger";
    }
}

// ==========================================
// HANDLE DOCUMENT DELETION ACTION
// ==========================================
if (isset($_GET['delete_unit'])) {
    $del_unit = trim($_GET['delete_unit']);
    
    $find_stmt = mysqli_prepare($conn, "SELECT document_path FROM clearance_documents WHERE matric_no = ? AND unit_name = ? AND status != 'Approved'");
    if ($find_stmt) {
        mysqli_stmt_bind_param($find_stmt, "ss", $matric_no, $del_unit);
        mysqli_stmt_execute($find_stmt);
        $find_res = mysqli_stmt_get_result($find_stmt);
        
        if ($doc = mysqli_fetch_assoc($find_res)) {
            $full_local_path = "../" . ltrim($doc['document_path'], '/');
            if (file_exists($full_local_path)) {
                @unlink($full_local_path);
            }
            
            $del_stmt = mysqli_prepare($conn, "DELETE FROM clearance_documents WHERE matric_no = ? AND unit_name = ?");
            if ($del_stmt) {
                mysqli_stmt_bind_param($del_stmt, "ss", $matric_no, $del_unit);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }
            
            $message = "Document removed. You can now select a new file.";
            $messageType = "success";
        }
        mysqli_stmt_close($find_stmt);
    }
}

// ==========================================
// FETCH REFRESHED STUDENT PROFILE DATA
// ==========================================
$student_stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE matric_no = ?");
mysqli_stmt_bind_param($student_stmt, "s", $matric_no);
mysqli_stmt_execute($student_stmt);
$student_res = mysqli_stmt_get_result($student_stmt);
$student = mysqli_fetch_assoc($student_res);
mysqli_stmt_close($student_stmt);

// Fallback if student somehow not found in students table
if (!$student) {
    header("Location: login.php");
    exit();
}

// ==========================================
// CALCULATE REAL-TIME PROGRESS TRACKER
// ==========================================
$total_units = 6;
$approved_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as approved_count FROM clearance_documents WHERE matric_no = ? AND status = 'Approved'");
mysqli_stmt_bind_param($approved_stmt, "s", $matric_no);
mysqli_stmt_execute($approved_stmt);
$approved_res = mysqli_stmt_get_result($approved_stmt);
$approved_row = mysqli_fetch_assoc($approved_res);
$approved_count = intval($approved_row['approved_count'] ?? 0);
mysqli_stmt_close($approved_stmt);

$progress_percent = round(($approved_count / $total_units) * 100);

// Update progress in database
$upd_stmt = mysqli_prepare($conn, "UPDATE students SET clearance_status = ? WHERE matric_no = ?");
mysqli_stmt_bind_param($upd_stmt, "is", $progress_percent, $matric_no);
mysqli_stmt_execute($upd_stmt);
mysqli_stmt_close($upd_stmt);

// Generate certificate hash upon completion
if ($progress_percent == 100 && empty($student['unique_clearance_id'])) {
    $random_id = "CLR-" . strtoupper(bin2hex(random_bytes(4)));
    $cert_stmt = mysqli_prepare($conn, "UPDATE students SET unique_clearance_id = ? WHERE matric_no = ?");
    mysqli_stmt_bind_param($cert_stmt, "ss", $random_id, $matric_no);
    mysqli_stmt_execute($cert_stmt);
    mysqli_stmt_close($cert_stmt);
    $student['unique_clearance_id'] = $random_id;
}

$docs_uploaded = [];
$docs_stmt = mysqli_prepare($conn, "SELECT * FROM clearance_documents WHERE matric_no = ?");
mysqli_stmt_bind_param($docs_stmt, "s", $matric_no);
mysqli_stmt_execute($docs_stmt);
$docs_res = mysqli_stmt_get_result($docs_stmt);
while ($row = mysqli_fetch_assoc($docs_res)) {
    $docs_uploaded[$row['unit_name']] = $row;
}
mysqli_stmt_close($docs_stmt);

$clearance_steps = [
    'Bursary' => 'Bursary Receipt Verification',
    'Library' => 'Library Pass Clear-Off',
    'Dean' => 'Dean Office Conference Receipt',
    'Medical' => 'Medical Center Health Certificate',
    'SUG' => 'SUG Dues Clearance',
    'Department' => 'Departmental Association Sign-Off'
];

// Determine profile photo URL: check captured photo first
$avatar_fallback = 'https://ui-avatars.com/api/?background=2c7da0&color=fff&rounded=true&bold=true&size=130&name=' . urlencode($student['fullname'] ?? 'Student');
$photo_src = $avatar_fallback;

if (!empty($student['face_image'])) {
    if (strpos($student['face_image'], 'http') === 0 || strpos($student['face_image'], '/') === 0) {
        $photo_src = $student['face_image'];
    } else {
        $relative_disk_path = '../' . ltrim($student['face_image'], '/');
        if (file_exists($relative_disk_path)) {
            $photo_src = $relative_disk_path;
        } else {
            $photo_src = $avatar_fallback;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>ElevateClear | Student Clearance Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #eef2f9;
            display: flex;
            min-height: 100vh;
            color: #1a2c3e;
        }

        .sidebar {
            width: 320px;
            background: linear-gradient(145deg, #0f2b3d 0%, #0a1e2c 100%);
            color: #f0f6fc;
            box-shadow: 12px 0 28px rgba(0, 0, 0, 0.08);
            z-index: 10;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #1e3a4d;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #3d8fbb;
            border-radius: 10px;
        }

        .profile-card {
            text-align: center;
            padding: 32px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }

        .profile-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #48c9b0;
            background: white;
            padding: 3px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.25);
            transition: transform 0.2s;
        }
        .profile-img:hover {
            transform: scale(1.03);
        }

        .profile-info {
            margin-top: 18px;
        }
        .profile-info h3 {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: -0.2px;
            color: white;
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .student-badge {
            background: #2c6e9e;
            display: inline-block;
            padding: 5px 15px;
            border-radius: 60px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 6px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 11px 0;
            font-size: 0.82rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            margin: 0 6px;
        }
        .info-row span:first-child {
            font-weight: 500;
            opacity: 0.7;
        }
        .info-row span:last-child {
            font-weight: 600;
            color: #fff;
            word-break: break-word;
            text-align: right;
            max-width: 60%;
        }
        .logout-btn {
            margin: 28px 20px 24px;
            background: rgba(231, 76, 60, 0.85);
            text-align: center;
            padding: 12px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.2s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .logout-btn i {
            font-size: 1rem;
        }
        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.2);
        }

        .main-content {
            flex: 1;
            padding: 32px 40px;
            background: #f5f9ff;
            overflow-x: auto;
        }

        .page-header {
            margin-bottom: 28px;
        }
        .page-header h2 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1f4e6e, #2c7da0);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.4px;
        }
        .page-header p {
            color: #5a6e7c;
            font-size: 0.95rem;
            margin-top: 8px;
            border-left: 3px solid #48c9b0;
            padding-left: 14px;
        }

        .alert {
            border-radius: 20px;
            padding: 16px 22px;
            margin-bottom: 28px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.03);
        }
        .alert i {
            font-size: 1.4rem;
        }
        .alert-success {
            background: #d9f3e6;
            border-left: 6px solid #2ecc71;
            color: #0e4b2b;
        }
        .alert-danger {
            background: #ffe8e6;
            border-left: 6px solid #e74c3c;
            color: #a52618;
        }

        .tracker-card {
            background: white;
            border-radius: 32px;
            padding: 28px 32px;
            margin-bottom: 38px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0,0,0,0.03);
        }
        .tracker-card h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e4663;
        }
        .progress-container {
            background: #eef2f5;
            border-radius: 60px;
            height: 32px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(95deg, #2ecc71, #1f8a4c);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 18px;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 60px;
            transition: width 0.45s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            color: white;
            letter-spacing: 0.3px;
        }
        .completion-banner {
            text-align: center;
            margin-top: 28px;
            background: #eafaf1;
            border-radius: 36px;
            padding: 24px;
            border: 2px dashed #2ecc71;
        }
        .completion-banner h4 {
            color: #0a4b2a;
            font-size: 1.5rem;
        }
        .clearance-id {
            background: #fff;
            padding: 10px 24px;
            border-radius: 60px;
            font-family: monospace;
            font-weight: bold;
            font-size: 1.1rem;
            display: inline-block;
            margin: 15px 0;
            letter-spacing: 1.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            color: #1f4e6e;
        }
        .btn-cert {
            background: linear-gradient(115deg, #2ecc71, #239b56);
            color: white;
            font-weight: 700;
            border: none;
            padding: 14px 32px;
            border-radius: 48px;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 18px rgba(46,204,113,0.25);
        }
        .btn-cert:hover {
            transform: translateY(-2px);
            background: linear-gradient(115deg, #27ae60, #1e8449);
            box-shadow: 0 12px 22px rgba(46,204,113,0.4);
        }
        .warning-remaining {
            background: #fff3e0;
            border-radius: 28px;
            padding: 10px 18px;
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #c45c00;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 26px;
            margin-top: 8px;
        }
        .unit-card {
            background: white;
            border-radius: 28px;
            overflow: hidden;
            transition: all 0.25s;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #eef2f9;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .unit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 35px -12px rgba(0, 0, 0, 0.1);
            border-color: #cbdde9;
        }
        .card-header {
            padding: 20px 22px 14px 22px;
            border-bottom: 2px solid #f0f3f8;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 14px;
        }
        .status-None {
            background: #eef2fa;
            color: #4b6f8c;
        }
        .status-Pending {
            background: #fff3cf;
            color: #b16f0a;
        }
        .status-Approved {
            background: #e0f7e9;
            color: #1c6e3f;
        }
        .status-Rejected {
            background: #ffe3e1;
            color: #b13b2d;
        }
        .unit-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f3b4c;
        }
        .reject-note {
            margin: 12px 0 0 0;
            background: #fff3f1;
            border-radius: 16px;
            padding: 10px 14px;
            font-size: 0.78rem;
            border-left: 4px solid #e74c3c;
            color: #bc3900;
            line-height: 1.4;
        }
        .card-actions {
            padding: 18px 22px 24px;
            background: #fefefe;
            border-top: 1px solid #edf2f7;
        }
        .file-upload-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .file-label {
            background: #f8fafd;
            border: 1px dashed #bdd4e5;
            border-radius: 40px;
            padding: 10px 14px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-label:hover {
            border-color: #2c7da0;
            background: #f0f7fb;
        }
        .file-label i {
            color: #3b8fc2;
        }
        input[type="file"] {
            display: none;
        }
        .btn-upload {
            background: #2c7da0;
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px 0;clr
            border-radius: 60px;
            cursor: pointer;
            transition: 0.2s;
            font-size: 0.85rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .btn-upload:hover {
            background: #1f5e7e;
            transform: translateY(-1px);
        }
        .doc-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f2f6fc;
            padding: 12px 16px;
            border-radius: 60px;
            gap: 10px;
        }
        .view-link {
            font-weight: 600;
            text-decoration: none;
            color: #226f98;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.8rem;
        }
        .view-link:hover {
            text-decoration: underline;
        }
        .btn-delete {
            background: none;
            color: #e67e22;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.78rem;
            border: 1px solid #e6cfc5;
            padding: 6px 14px;
            border-radius: 50px;
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-delete:hover {
            background: #fceaea;
            color: #c0392b;
            border-color: #e74c3c;
        }
        .locked-badge {
            font-size: 0.72rem;
            background: #e9f7ef;
            padding: 5px 12px;
            border-radius: 50px;
            color: #2c6e3f;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 780px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="profile-card">
        <img src="<?php echo htmlspecialchars($photo_src); ?>" class="profile-img" alt="Student Identity" onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($avatar_fallback); ?>';">
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($student['fullname'] ?? 'Student Name'); ?></h3>
            <div class="student-badge"><i class="fas fa-check-circle"></i> Biometric Verified</div>
        </div>
        <div style="margin-top: 20px;">
            <div class="info-row"><span><i class="fas fa-id-card"></i> Matric No</span><span><?php echo htmlspecialchars($student['matric_no'] ?? 'N/A'); ?></span></div>
            <div class="info-row"><span><i class="fas fa-university"></i> Faculty</span><span><?php echo htmlspecialchars($student['faculty'] ?? '—'); ?></span></div>
            <div class="info-row"><span><i class="fas fa-book"></i> Department</span><span><?php echo htmlspecialchars($student['department'] ?? '—'); ?></span></div>
            <div class="info-row"><span><i class="fas fa-layer-group"></i> Level</span><span><?php echo htmlspecialchars($student['level'] ?? '—'); ?></span></div>
            <div class="info-row"><span><i class="fas fa-phone-alt"></i> Phone</span><span><?php echo htmlspecialchars($student['phone'] ?? '—'); ?></span></div>
            <div class="info-row"><span><i class="fas fa-envelope"></i> Email</span><span><?php echo htmlspecialchars($student['email'] ?? '—'); ?></span></div>
        </div>
    </div>
    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Secure Sign Out</a>
</div>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-clipboard-list" style="color: #2c7da0;"></i> Electronic Clearance Suite</h2>
        <p>Submit institutional receipts & monitor endorsement status in real-time</p>
    </div>

    <?php if(!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <div class="tracker-card">
        <h3><i class="fas fa-chart-line"></i> Clearance Velocity</h3>
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%;">
                <?php echo $progress_percent; ?>% completed
            </div>
        </div>
        <div style="margin-top: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div><i class="fas fa-check-circle" style="color:#2ecc71;"></i> <strong><?php echo $approved_count; ?>/<?php echo $total_units; ?></strong> approvals obtained</div>
            <?php if ($progress_percent < 100): ?>
                <div class="warning-remaining"><i class="fas fa-hourglass-half"></i> <?php echo ($total_units - $approved_count); ?> endorsement(s) remaining</div>
            <?php endif; ?>
        </div>

        <?php if ($progress_percent == 100): ?>
            <div class="completion-banner">
                <h4><i class="fas fa-trophy" style="color:#f39c12;"></i> Fully Cleared! 🎉</h4>
                <p style="margin-top:6px; color:#2c3e50;">All mandatory clearance desks have endorsed your documentation.</p>
                <div class="clearance-id"><i class="fas fa-fingerprint"></i> <?php echo htmlspecialchars($student['unique_clearance_id'] ?? 'CLR-XXXX'); ?></div>
                <div>
                    <a href="generate_certificate.php" target="_blank" class="btn-cert"><i class="fas fa-file-contract"></i> View & Print Clearance Certificate</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <h3 style="margin: 10px 0 20px 0; font-weight: 600;"><i class="fas fa-shield-alt"></i> Mandatory Clearance Desks</h3>
    <div class="grid-container">
        <?php foreach ($clearance_steps as $key => $title): ?>
            <?php 
                $has_upload = isset($docs_uploaded[$key]);
                $status = $has_upload ? $docs_uploaded[$key]['status'] : 'None';
                $comment = ($status === 'Rejected') ? ($docs_uploaded[$key]['rejection_comment'] ?? null) : null;
            ?>
            <div class="unit-card">
                <div class="card-header">
                    <div class="status-badge status-<?php echo $status; ?>">
                        <i class="fas <?php echo $status == 'Approved' ? 'fa-check-circle' : ($status == 'Pending' ? 'fa-clock' : ($status == 'Rejected' ? 'fa-times-circle' : 'fa-hourglass')); ?>"></i>
                        <?php echo $status; ?>
                    </div>
                    <div class="unit-title"><?php echo htmlspecialchars($title); ?></div>
                    <?php if ($status === 'Rejected' && !empty($comment)): ?>
                        <div class="reject-note">
                            <i class="fas fa-comment-dots"></i> <strong>Officer Feedback:</strong> <?php echo htmlspecialchars($comment); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-actions">
                    <?php if (!$has_upload): ?>
                        <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="file-upload-form">
                            <input type="hidden" name="unit_name" value="<?php echo htmlspecialchars($key); ?>">
                            <label class="file-label" for="file-<?php echo $key; ?>">
                                <i class="fas fa-cloud-upload-alt"></i> Choose document (PDF, JPG, PNG)
                            </label>
                            <input type="file" name="clearance_file" id="file-<?php echo $key; ?>" accept=".pdf,.png,.jpg,.jpeg" required>
                            <button type="submit" name="upload_doc" class="btn-upload"><i class="fas fa-paper-plane"></i> Submit File for Review</button>
                        </form>
                    <?php else: ?>
                        <?php
                            $doc_path = $docs_uploaded[$key]['document_path'];
                            $doc_url = (strpos($doc_path, 'http') === 0 || strpos($doc_path, '/') === 0) ? $doc_path : '../' . ltrim($doc_path, '/');
                        ?>
                        <div class="doc-info">
                            <a href="<?php echo htmlspecialchars($doc_url); ?>" target="_blank" class="view-link">
                                <i class="fas fa-external-link-alt"></i> Preview document
                            </a>
                            <?php if ($status !== 'Approved'): ?>
                                <a href="dashboard.php?delete_unit=<?php echo urlencode($key); ?>" onclick="return confirm('⚠️ Remove this document? You can re-upload a corrected version.');" class="btn-delete">
                                    <i class="fas fa-trash-alt"></i> Replace
                                </a>
                            <?php else: ?>
                                <span class="locked-badge"><i class="fas fa-lock"></i> Approved & locked</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const fileName = this.files[0]?.name;
            const label = this.previousElementSibling;
            if (fileName && label && label.classList.contains('file-label')) {
                label.innerHTML = `<i class="fas fa-file-alt"></i> ${fileName.substring(0, 32)}${fileName.length > 32 ? '...' : ''}`;
            }
        });
    });
</script>
</body>
</html>