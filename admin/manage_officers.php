<?php
// admin/manage_officers.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';

$message = "";
$messageType = "";

// ==========================================
// ACTION 1: HANDLE SINGLE OFFICER CREATION
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_officer'])) {
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $plain_password = $_POST['password'];
    $unit_name = mysqli_real_escape_string($conn, $_POST['unit_name']);

    if (empty($fullname) || empty($username) || empty($plain_password) || empty($unit_name)) {
        $message = "All form field entries are strictly required.";
        $messageType = "danger";
    } else {
        $check = mysqli_query($conn, "SELECT * FROM officers WHERE username = '$username'");
        if (mysqli_num_rows($check) > 0) {
            $message = "Error: That username is already assigned to an existing officer.";
            $messageType = "danger";
        } else {
            $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);
            $insert_query = "INSERT INTO officers (username, password, unit_name, fullname) 
                             VALUES ('$username', '$hashed_password', '$unit_name', '$fullname')";
            if (mysqli_query($conn, $insert_query)) {
                $message = "Officer account successfully created and provisioned!";
                $messageType = "success";
            } else {
                $message = "Database Error: " . mysqli_error($conn);
                $messageType = "danger";
            }
        }
    }
}

// ==========================================
// ACTION 2: HANDLE BULK CSV FILE UPLOAD
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_csv'])) {
    if (isset($_FILES['officer_csv']) && $_FILES['officer_csv']['error'] == 0) {
        $file_tmp = $_FILES['officer_csv']['tmp_name'];
        
        if (($handle = fopen($file_tmp, "r")) !== FALSE) {
            fgetcsv($handle, 1000, ",");
            
            $inserted = 0;
            $skipped = 0;
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) >= 4) {
                    $fullname = mysqli_real_escape_string($conn, trim($row[0]));
                    $username = mysqli_real_escape_string($conn, trim($row[1]));
                    $plain_pass = trim($row[2]);
                    $unit_name = mysqli_real_escape_string($conn, trim($row[3]));
                    
                    if (empty($username) || empty($plain_pass) || empty($unit_name)) {
                        $skipped++;
                        continue;
                    }
                    
                    $check = mysqli_query($conn, "SELECT id FROM officers WHERE username = '$username'");
                    if (mysqli_num_rows($check) == 0) {
                        $hashed_pass = password_hash($plain_pass, PASSWORD_BCRYPT);
                        $q = "INSERT INTO officers (username, password, unit_name, fullname) 
                              VALUES ('$username', '$hashed_pass', '$unit_name', '$fullname')";
                        if (mysqli_query($conn, $q)) {
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $skipped++;
                    }
                }
            }
            fclose($handle);
            $message = "CSV Processed! Successfully uploaded <strong>$inserted</strong> officers. (Skipped/Failed: $skipped)";
            $messageType = ($inserted > 0) ? "success" : "danger";
        } else {
            $message = "Error: System failed to read the uploaded CSV data stream.";
            $messageType = "danger";
        }
    } else {
        $message = "Please select a valid, uncorrupted CSV file template.";
        $messageType = "danger";
    }
}

// ==========================================
// ACTION 3: HANDLE OFFICER DELETION
// ==========================================
if (isset($_GET['delete_officer'])) {
    $del_id = intval($_GET['delete_officer']);
    $del_stmt = mysqli_prepare($conn, "DELETE FROM officers WHERE id = ?");
    if ($del_stmt) {
        mysqli_stmt_bind_param($del_stmt, "i", $del_id);
        if (mysqli_stmt_execute($del_stmt)) {
            $message = "Officer account successfully removed.";
            $messageType = "success";
        } else {
            $message = "Failed to remove officer: " . mysqli_error($conn);
            $messageType = "danger";
        }
        mysqli_stmt_close($del_stmt);
    }
}

// ==========================================
// FETCH EXISTING ACCOUNTS (MOVED BEFORE HTML)
// ==========================================
$result = mysqli_query($conn, "SELECT * FROM officers ORDER BY unit_name ASC");
$officer_count = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Vault | Officer Management Console</title>
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
            background: #ecf0f5;
            padding: 28px 32px;
            color: #1e2a3e;
        }

        .admin-wrapper {
            max-width: 1400px;
            margin: 0 auto;
        }

        .admin-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 16px;
        }
        .title-section h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1A3C44, #2C6E6E);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.3px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        .title-section h1 i {
            background: none;
            -webkit-background-clip: unset;
            background-clip: unset;
            color: #2c7a6e;
            font-size: 2rem;
        }
        .title-section p {
            color: #5a6f7e;
            margin-top: 8px;
            font-weight: 500;
            border-left: 3px solid #48b5a0;
            padding-left: 14px;
            font-size: 0.9rem;
        }
        .stats-chip {
            background: white;
            padding: 8px 20px;
            border-radius: 60px;
            font-weight: 600;
            font-size: 0.85rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            color: #1e4663;
        }
        .stats-chip i {
            color: #2c7a6e;
            margin-right: 6px;
        }

        .alert-modern {
            padding: 16px 24px;
            border-radius: 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 500;
            backdrop-filter: blur(2px);
            border-left: 5px solid;
            background: white;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.02);
        }
        .alert-modern i {
            font-size: 1.5rem;
        }
        .alert-success {
            border-left-color: #2ecc71;
            background: #eefbf2;
            color: #1d6f3c;
        }
        .alert-danger {
            border-left-color: #e74c3c;
            background: #fef3f2;
            color: #b53b2a;
        }

        .dashboard-grid {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
        }
        .left-panel {
            flex: 1.2;
            min-width: 320px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }
        .right-panel {
            flex: 2.2;
            min-width: 0;
        }

        .card-elegant {
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .card-header {
            padding: 24px 28px 12px 28px;
            border-bottom: 2px solid #f0f3f8;
        }
        .card-header h3 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1f4b5e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-body {
            padding: 22px 28px 28px 28px;
        }

        .form-modern .input-group {
            margin-bottom: 20px;
        }
        .form-modern label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5d7a8c;
            margin-bottom: 8px;
        }
        .form-modern label i {
            margin-right: 6px;
            width: 20px;
            color: #348f7c;
        }
        .form-modern input, 
        .form-modern select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            font-family: 'Inter', monospace;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: #fefefe;
        }
        .form-modern input:focus, 
        .form-modern select:focus {
            outline: none;
            border-color: #2c7a6e;
            box-shadow: 0 0 0 3px rgba(44, 122, 110, 0.1);
        }
        .btn {
            border: none;
            padding: 12px 20px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: #1f4b5e;
            color: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .btn-primary:hover {
            background: #0f3b4c;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #2c6e5c;
            color: white;
        }
        .btn-success:hover {
            background: #1f5848;
            transform: translateY(-2px);
        }

        .csv-guide-modern {
            background: #fef9e8;
            border-radius: 24px;
            padding: 16px 20px;
            margin-bottom: 24px;
            border: 1px solid #ffe6b3;
        }
        .csv-guide-modern strong {
            color: #c07c2e;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .csv-guide-modern code {
            background: #fff1dd;
            padding: 4px 8px;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .file-drop-zone {
            border: 2px dashed #cbdde9;
            background: #fafdff;
            border-radius: 24px;
            padding: 20px;
            text-align: center;
            transition: 0.2s;
            cursor: pointer;
        }
        .file-drop-zone:hover {
            border-color: #2c7a6e;
            background: #f6fbf9;
        }
        .badge-unit {
            background: #e0f0f5;
            color: #1d6f8b;
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-block;
        }
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #95adc2;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 16px 20px;
            background: #f8fafd;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #486c7e;
            border-bottom: 1px solid #e4edf2;
        }
        .data-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #eff3f8;
            font-size: 0.85rem;
            font-weight: 500;
            color: #1f3849;
        }
        .data-table tr:hover td {
            background: #fbfeff;
        }
        .officers-table-wrapper {
            overflow-x: auto;
            border-radius: 24px;
        }
        .small-note {
            font-size: 0.7rem;
            color: #7b8c9e;
            margin-top: 12px;
            text-align: center;
        }
        @media (max-width: 860px) {
            body {
                padding: 20px;
            }
            .dashboard-grid {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <div class="admin-header">
        <div class="title-section">
            <h1>
                <i class="fas fa-user-shield"></i> 
                Officer Vault · Clearance Command
            </h1>
            <p><i class="fas fa-database"></i> Centralized personnel registry — full provisioning & batch CSV ingestion</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="../index.php" style="background: white; padding: 8px 18px; border-radius: 60px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 2px 6px rgba(0,0,0,0.04); color: #1e4663; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fas fa-home" style="color: #2c7a6e;"></i> Main Portal
            </a>
            <div class="stats-chip">
                <i class="fas fa-users"></i> Active officers · <?php echo $officer_count; ?>
            </div>
        </div>
    </div>

    <?php if(!empty($message)): ?>
        <div class="alert-modern alert-<?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-circle-exclamation'; ?>"></i>
            <span><?php echo $message; ?></span>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="left-panel">
            <div class="card-elegant">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus" style="color:#2c7a6e;"></i> Manual Registration</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="manage_officers.php" class="form-modern">
                        <input type="hidden" name="add_officer" value="1">
                        <div class="input-group">
                            <label><i class="fas fa-user-circle"></i> Full Name</label>
                            <input type="text" name="fullname" required placeholder="e.g., Dr. Adanna Okafor" autocomplete="off">
                        </div>
                        <div class="input-group">
                            <label><i class="fas fa-fingerprint"></i> Username</label>
                            <input type="text" name="username" required placeholder="e.g., adanna_bursary" autocomplete="off">
                        </div>
                        <div class="input-group">
                            <label><i class="fas fa-lock"></i> Temporary Password</label>
                            <input type="password" name="password" required placeholder="••••••••">
                        </div>
                        <div class="input-group">
                            <label><i class="fas fa-building"></i> Assigned Unit Desk</label>
                            <select name="unit_name" required>
                                <option value="">— Select clearance desk —</option>
                                <option value="Bursary">🏦 Bursary Desk</option>
                                <option value="Library">📚 Library Desk</option>
                                <option value="Dean">🎓 Dean of Students</option>
                                <option value="Medical">🏥 Medical Center</option>
                                <option value="SUG">🗳️ SUG Secretariat</option>
                                <option value="Department">📖 Departmental Board</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Create Officer Account</button>
                    </form>
                </div>
            </div>

            <div class="card-elegant">
                <div class="card-header">
                    <h3><i class="fas fa-file-csv" style="color:#27ae60;"></i> Bulk Import Engine</h3>
                </div>
                <div class="card-body">
                    <div class="csv-guide-modern">
                        <strong><i class="fas fa-lightbulb"></i> CSV Schema requirements</strong>
                        <p style="font-size:0.75rem; margin: 8px 0;">Headers (first row) must be exactly:</p>
                        <code>fullname,username,password,unit_name</code>
                        <p style="margin-top: 10px; font-size:0.7rem;"><i class="fas fa-check-circle"></i> Valid unit values: Bursary, Library, Dean, Medical, SUG, Department</p>
                        <hr style="margin: 12px 0;">
                        <span><i class="fas fa-download"></i> Example: <em>John Doe, jdoe_lib, Pass123, Library</em></span>
                    </div>
                    <form method="POST" action="manage_officers.php" enctype="multipart/form-data" id="csvForm">
                        <input type="hidden" name="upload_csv" value="1">
                        <div class="input-group">
                            <label><i class="fas fa-cloud-upload-alt"></i> CSV File (UTF-8 format)</label>
                            <div class="file-drop-zone" id="dropZone">
                                <i class="fas fa-file-import" style="font-size: 1.8rem; color:#4b8b7a;"></i>
                                <p style="margin: 8px 0 4px; font-weight:500;">Click or drag & drop</p>
                                <span style="font-size:0.7rem; color:#7f8c8d;">.csv only, max 5MB</span>
                                <input type="file" name="officer_csv" id="csvFileInput" accept=".csv" required style="display:none;">
                            </div>
                            <div id="fileNameDisplay" style="font-size:0.75rem; margin-top: 8px; text-align:center; color:#2c7a6e;"></div>
                        </div>
                        <button type="submit" class="btn btn-success" id="submitCsvBtn"><i class="fas fa-rocket"></i> Process Bulk Spreadsheet</button>
                    </form>
                    <div class="small-note">
                        <i class="fas fa-shield-alt"></i> Duplicate usernames are automatically skipped.
                    </div>
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="card-elegant" style="height: 100%; display: flex; flex-direction: column;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-address-card"></i> Active Administrative Directory</h3>
                    <span style="background:#eef2fa; padding:5px 12px; border-radius: 40px; font-size:0.7rem; font-weight:600;"><i class="fas fa-sync-alt"></i> Live registry</span>
                </div>
                <div class="card-body" style="padding-top: 0;">
                    <div class="officers-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user-tag"></i> Full Name</th>
                                    <th><i class="fas fa-key"></i> Username</th>
                                    <th><i class="fas fa-briefcase"></i> Assigned Unit</th>
                                    <th style="text-align:center;"><i class="fas fa-cog"></i> Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($officer_count == 0): ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
                                            <i class="fas fa-user-slash"></i>
                                            <div>No officers provisioned yet</div>
                                            <div style="font-size:0.7rem;">Use the controls on the left to register users.</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    // Reset pointer to beginning for display
                                    mysqli_data_seek($result, 0);
                                    while($row = mysqli_fetch_assoc($result)): 
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                        <td><code style="background:#f2f5f9; padding:4px 8px; border-radius:20px;"><?php echo htmlspecialchars($row['username']); ?></code></td>
                                        <td>
                                            <span class="badge-unit">
                                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($row['unit_name']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <a href="manage_officers.php?delete_officer=<?php echo $row['id']; ?>" onclick="return confirm('⚠️ Remove <?php echo htmlspecialchars(addslashes($row['fullname'])); ?> from the officer roster?');" style="color:#e74c3c; padding:6px 12px; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; text-decoration:none; font-size:0.75rem; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($officer_count > 0): ?>
                        <div style="margin-top: 20px; font-size:0.7rem; text-align:center; color:#6182a0;">
                            <i class="fas fa-check-circle" style="color:#2c7a6e;"></i> Total active clearance officers: <?php echo $officer_count; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('csvFileInput');
        const fileNameSpan = document.getElementById('fileNameDisplay');
        
        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => {
                fileInput.click();
            });
            
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#2c7a6e';
                dropZone.style.backgroundColor = '#f2fcf8';
            });
            
            dropZone.addEventListener('dragleave', () => {
                dropZone.style.borderColor = '#cbdde9';
                dropZone.style.backgroundColor = '#fafdff';
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.borderColor = '#cbdde9';
                dropZone.style.backgroundColor = '#fafdff';
                const files = e.dataTransfer.files;
                if (files.length > 0 && (files[0].type === 'text/csv' || files[0].name.endsWith('.csv'))) {
                    fileInput.files = files;
                    if (fileNameSpan) fileNameSpan.innerHTML = `<i class="fas fa-file-csv"></i> ${files[0].name}`;
                } else {
                    if (fileNameSpan) fileNameSpan.innerHTML = '<span style="color:#e67e22;">⚠️ Invalid file type. Please select .csv</span>';
                }
            });
        }
        
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    if (fileNameSpan) fileNameSpan.innerHTML = `<i class="fas fa-check-circle" style="color:#2ecc71;"></i> ${this.files[0].name}`;
                } else {
                    if (fileNameSpan) fileNameSpan.innerHTML = '';
                }
            });
        }
    })();
</script>
</body>
</html>