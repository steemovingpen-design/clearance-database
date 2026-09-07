<?php
// students/generate_certificate.php
require_once '../includes/config.php';

if (!isset($_SESSION['student_matric'])) {
    header("Location: login.php");
    exit();
}

$matric_no = $_SESSION['student_matric'];

// Fetch student details
$stmt = mysqli_prepare($conn, "SELECT * FROM students WHERE matric_no = ?");
mysqli_stmt_bind_param($stmt, "s", $matric_no);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$student) {
    header("Location: login.php");
    exit();
}

// Fetch all approved clearance documents
$docs_stmt = mysqli_prepare($conn, "SELECT unit_name, uploaded_at, status FROM clearance_documents WHERE matric_no = ? AND status = 'Approved'");
mysqli_stmt_bind_param($docs_stmt, "s", $matric_no);
mysqli_stmt_execute($docs_stmt);
$docs_res = mysqli_stmt_get_result($docs_stmt);

$approved_units = [];
while ($row = mysqli_fetch_assoc($docs_res)) {
    $approved_units[$row['unit_name']] = $row;
}
mysqli_stmt_close($docs_stmt);

// Clearance requires all 6 units
$required_units = ['Bursary', 'Library', 'Dean', 'Medical', 'SUG', 'Department'];
$approved_count = count($approved_units);

if ($approved_count < count($required_units)) {
    echo "<!DOCTYPE html><html><head><title>Clearance Incomplete</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f5f7fa;} .box{background:#fff;padding:40px;border-radius:16px;display:inline-block;box-shadow:0 10px 30px rgba(0,0,0,0.1);max-width:500px;} a{color:#2c7da0;text-decoration:none;font-weight:bold;}</style></head><body><div class='box'><h2>Clearance Incomplete</h2><p>You have only completed <strong>$approved_count/6</strong> clearances. All units must be approved to generate the digital clearance certificate.</p><br><a href='dashboard.php'>&larr; Return to Dashboard</a></div></body></html>";
    exit();
}

// Face image path resolution
$photo_src = 'https://ui-avatars.com/api/?background=2c7da0&color=fff&rounded=true&bold=true&size=140&name=' . urlencode($student['fullname']);
if (!empty($student['face_image'])) {
    if (strpos($student['face_image'], 'http') === 0 || strpos($student['face_image'], '/') === 0) {
        $photo_src = $student['face_image'];
    } else {
        $relative_disk_path = '../' . ltrim($student['face_image'], '/');
        if (file_exists($relative_disk_path)) {
            $photo_src = $relative_disk_path;
        }
    }
}

$cert_id = $student['unique_clearance_id'] ?? ('CLR-' . strtoupper(substr(md5($matric_no), 0, 8)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Certificate - <?php echo htmlspecialchars($student['matric_no']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #2b3a4a;
            padding: 40px 20px;
            color: #1e293b;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .action-bar {
            width: 100%;
            max-width: 920px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-action {
            background: #2c7da0;
            color: white;
            padding: 10px 22px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: 0.2s;
        }
        .btn-action:hover {
            background: #1f5e7e;
            transform: translateY(-2px);
        }
        .btn-print {
            background: #27ae60;
        }
        .btn-print:hover {
            background: #1e8449;
        }

        .cert-container {
            width: 100%;
            max-width: 920px;
            background: #ffffff;
            border: 12px solid #0f2b3d;
            padding: 50px 60px;
            border-radius: 8px;
            position: relative;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            overflow: hidden;
        }
        .cert-container::before {
            content: '';
            position: absolute;
            top: 10px; left: 10px; right: 10px; bottom: 10px;
            border: 2px solid #c59b27;
            pointer-events: none;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 6.5rem;
            font-weight: 900;
            color: rgba(44, 125, 160, 0.04);
            letter-spacing: 12px;
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
        }

        .cert-header {
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 28px;
        }
        .cert-header h1 {
            font-family: 'Cinzel', serif;
            font-size: 1.9rem;
            color: #0f2b3d;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .cert-header .sub-head {
            font-size: 0.92rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }
        .cert-badge {
            display: inline-block;
            margin-top: 12px;
            background: #eafaf1;
            border: 1px solid #2ecc71;
            color: #196f3d;
            padding: 5px 18px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .student-showcase {
            display: flex;
            align-items: center;
            gap: 30px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 30px;
        }
        .student-photo {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid #2c7da0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .student-meta {
            flex: 1;
        }
        .student-meta h2 {
            font-size: 1.4rem;
            color: #0f2b3d;
            margin-bottom: 6px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
            font-size: 0.88rem;
            color: #475569;
        }
        .meta-grid strong {
            color: #1e293b;
        }

        .units-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .units-table th {
            background: #0f2b3d;
            color: white;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 12px 16px;
            text-align: left;
        }
        .units-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }
        .units-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .status-tag {
            color: #27ae60;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .qr-placeholder {
            border: 2px dashed #94a3b8;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
            background: #ffffff;
        }
        .cert-hash {
            font-family: monospace;
            font-weight: bold;
            color: #0f2b3d;
            font-size: 0.95rem;
            margin-top: 4px;
        }
        .sign-box {
            text-align: center;
            border-top: 2px solid #0f2b3d;
            width: 220px;
            padding-top: 6px;
            font-size: 0.82rem;
            color: #475569;
        }

        @media print {
            body { background: white; padding: 0; }
            .action-bar { display: none; }
            .cert-container { border: 8px solid #0f2b3d; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <a href="dashboard.php" class="btn-action"><i class="fas fa-arrow-left"></i> Dashboard</a>
    <button onclick="window.print()" class="btn-action btn-print"><i class="fas fa-print"></i> Print / Save as PDF</button>
</div>

<div class="cert-container">
    <div class="watermark">CLEARED & VERIFIED</div>

    <div class="cert-header">
        <div class="sub-head"><i class="fas fa-university"></i> Academic Affairs & Registry</div>
        <h1>Official Clearance Certificate</h1>
        <div class="cert-badge"><i class="fas fa-check-double"></i> All 6 Institutional Desks Cleared</div>
    </div>

    <div class="student-showcase">
        <img src="<?php echo htmlspecialchars($photo_src); ?>" class="student-photo" alt="Verified Student Biometrics">
        <div class="student-meta">
            <h2><?php echo htmlspecialchars($student['fullname']); ?></h2>
            <div class="meta-grid">
                <div><strong>Matric No:</strong> <?php echo htmlspecialchars($student['matric_no']); ?></div>
                <div><strong>Faculty:</strong> <?php echo htmlspecialchars($student['faculty'] ?? 'N/A'); ?></div>
                <div><strong>Department:</strong> <?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></div>
                <div><strong>Academic Level:</strong> <?php echo htmlspecialchars($student['level'] ?? 'N/A'); ?></div>
            </div>
        </div>
    </div>

    <table class="units-table">
        <thead>
            <tr>
                <th>Clearance Unit Desk</th>
                <th>Verification Endorsement</th>
                <th>Audit Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $unit_labels = [
                    'Bursary' => 'Bursary & Student Accounts',
                    'Library' => 'University Main Library',
                    'Dean' => 'Dean of Student Affairs',
                    'Medical' => 'University Health Center',
                    'SUG' => 'Student Union Secretariat',
                    'Department' => 'Departmental Academic Board'
                ];
                foreach ($unit_labels as $u_key => $u_title):
                    $u_info = $approved_units[$u_key] ?? null;
                    $time_str = $u_info ? date('M d, Y - h:i A', strtotime($u_info['uploaded_at'])) : 'Verified';
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($u_title); ?></strong></td>
                <td><span class="status-tag"><i class="fas fa-check-circle"></i> Approved & Signed Off</span></td>
                <td style="color: #64748b; font-size: 0.8rem;"><?php echo htmlspecialchars($time_str); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cert-footer">
        <div class="qr-placeholder">
            <div><i class="fas fa-fingerprint fa-2x" style="color:#2c7da0; margin-bottom:4px;"></i></div>
            <div>VERIFICATION SECURITY HASH</div>
            <div class="cert-hash"><?php echo htmlspecialchars($cert_id); ?></div>
        </div>

        <div class="sign-box">
            <strong>Registrar / Clearance Officer</strong><br>
            <span>Electronic Signature Attached</span>
        </div>
    </div>
</div>

</body>
</html>
