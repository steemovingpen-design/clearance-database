<?php
// officers/dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Guard: Kick out unauthorized visitors
if (!isset($_SESSION['officer_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../includes/config.php';

$officer_name = $_SESSION['officer_name'];
$officer_unit = $_SESSION['officer_unit'];

$message = "";
$messageType = "";

$current_view = trim($_GET['view'] ?? 'active');
$valid_views = ['active', 'pending', 'cleared', 'all'];
if (!in_array($current_view, $valid_views)) {
    $current_view = 'active';
}

// ==========================================
// ACTION 1: HANDLE SINGLE STATUS DECISION
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $doc_id = intval($_POST['document_id']);
    $action = trim($_POST['action'] ?? '');
    $rejection_comment = trim($_POST['rejection_comment'] ?? '');
    
    if ($action === 'Approved' || $action === 'Rejected') {
        if ($action === 'Approved') {
            $update_stmt = mysqli_prepare($conn, "UPDATE clearance_documents SET status = 'Approved', rejection_comment = NULL, is_cleared = 0 WHERE id = ? AND unit_name = ?");
            mysqli_stmt_bind_param($update_stmt, "is", $doc_id, $officer_unit);
        } else {
            $comment_val = !empty($rejection_comment) ? $rejection_comment : "Document does not satisfy unit requirements. Please upload a clear official receipt.";
            $update_stmt = mysqli_prepare($conn, "UPDATE clearance_documents SET status = 'Rejected', rejection_comment = ? WHERE id = ? AND unit_name = ?");
            mysqli_stmt_bind_param($update_stmt, "sis", $comment_val, $doc_id, $officer_unit);
        }
        
        if (mysqli_stmt_execute($update_stmt)) {
            $message = "File status updated to <strong>$action</strong> successfully.";
            $messageType = "success";
        } else {
            $message = "Database Action Fault: " . mysqli_error($conn);
            $messageType = "danger";
        }
        mysqli_stmt_close($update_stmt);
    }
}

// ==========================================
// ACTION 2: CLEAR A SINGLE APPROVED DOCUMENT
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_single'])) {
    $doc_id = intval($_POST['document_id']);
    $clear_stmt = mysqli_prepare($conn, "UPDATE clearance_documents SET is_cleared = 1 WHERE id = ? AND unit_name = ? AND status = 'Approved'");
    if ($clear_stmt) {
        mysqli_stmt_bind_param($clear_stmt, "is", $doc_id, $officer_unit);
        if (mysqli_stmt_execute($clear_stmt)) {
            $message = "Approved document cleared from active queue and archived.";
            $messageType = "success";
        }
        mysqli_stmt_close($clear_stmt);
    }
}

// ==========================================
// ACTION 3: CLEAR ALL APPROVED DOCUMENTS (BULK)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_all_approved'])) {
    $bulk_stmt = mysqli_prepare($conn, "UPDATE clearance_documents SET is_cleared = 1 WHERE unit_name = ? AND status = 'Approved' AND (is_cleared = 0 OR is_cleared IS NULL)");
    if ($bulk_stmt) {
        mysqli_stmt_bind_param($bulk_stmt, "s", $officer_unit);
        if (mysqli_stmt_execute($bulk_stmt)) {
            $cleared_rows = mysqli_stmt_affected_rows($bulk_stmt);
            $message = "Successfully cleared <strong>$cleared_rows</strong> approved file(s) from the queue to declutter your dashboard.";
            $messageType = "success";
        }
        mysqli_stmt_close($bulk_stmt);
    }
}

// ==========================================
// ACTION 4: RESTORE CLEARED DOCUMENT BACK TO QUEUE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['restore_single'])) {
    $doc_id = intval($_POST['document_id']);
    $res_stmt = mysqli_prepare($conn, "UPDATE clearance_documents SET is_cleared = 0 WHERE id = ? AND unit_name = ?");
    if ($res_stmt) {
        mysqli_stmt_bind_param($res_stmt, "is", $doc_id, $officer_unit);
        if (mysqli_stmt_execute($res_stmt)) {
            $message = "Document restored back into the active clearance queue.";
            $messageType = "success";
        }
        mysqli_stmt_close($res_stmt);
    }
}

// ==========================================
// FETCH METRICS FOR DASHBOARD CARDS
// ==========================================
$stats_stmt = mysqli_prepare($conn, "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'Approved' AND (is_cleared = 0 OR is_cleared IS NULL) THEN 1 ELSE 0 END) as active_approved,
                    SUM(CASE WHEN status = 'Approved' AND is_cleared = 1 THEN 1 ELSE 0 END) as cleared_approved,
                    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                FROM clearance_documents 
                WHERE unit_name = ?");
mysqli_stmt_bind_param($stats_stmt, "s", $officer_unit);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

$total_docs       = intval($stats['total'] ?? 0);
$pending_docs     = intval($stats['pending'] ?? 0);
$active_approved  = intval($stats['active_approved'] ?? 0);
$cleared_approved = intval($stats['cleared_approved'] ?? 0);
$rejected_docs    = intval($stats['rejected'] ?? 0);
$active_queue_count = $pending_docs + $active_approved + $rejected_docs;

// ==========================================
// BUILD QUERY BASED ON SELECTED VIEW FILTER
// ==========================================
$where_filter = "";
if ($current_view === 'pending') {
    $where_filter = "AND d.status = 'Pending'";
} elseif ($current_view === 'cleared') {
    $where_filter = "AND d.status = 'Approved' AND d.is_cleared = 1";
} elseif ($current_view === 'all') {
    $where_filter = ""; // Show all
} else {
    // 'active' (default): only documents not cleared
    $where_filter = "AND (d.is_cleared = 0 OR d.is_cleared IS NULL)";
}

$query_sql = "SELECT d.*, s.fullname as student_name, s.face_image 
              FROM clearance_documents d 
              JOIN students s ON d.matric_no = s.matric_no 
              WHERE d.unit_name = ? $where_filter
              ORDER BY d.uploaded_at DESC";

$doc_stmt = mysqli_prepare($conn, $query_sql);
mysqli_stmt_bind_param($doc_stmt, "s", $officer_unit);
mysqli_stmt_execute($doc_stmt);
$result = mysqli_stmt_get_result($doc_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Terminal - <?php echo htmlspecialchars($officer_unit); ?> Desk</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --panel-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #2c7da0;
            --primary-hover: #1f5e7e;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --border-color: #e2e8f0;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-main);
            margin: 0; 
            padding: 30px;
            line-height: 1.5;
        }

        .dashboard-container {
            max-width: 1320px;
            margin: 0 auto;
        }

        /* --- HEADER STYLING --- */
        .header-bar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: var(--panel-bg); 
            padding: 22px 32px; 
            border-radius: 18px; 
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03); 
            margin-bottom: 26px;
            border: 1px solid var(--border-color);
        }
        .header-bar h1 { font-size: 22px; font-weight: 700; margin: 0; color: var(--text-main); }
        .header-meta { display: flex; align-items: center; gap: 10px; margin-top: 6px; color: var(--text-muted); font-size: 14px; }
        
        .badge-unit { 
            background: #e0f2fe; 
            color: #0369a1; 
            padding: 4px 12px; 
            border-radius: 9999px; 
            font-size: 12px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }

        .logout-btn { 
            color: var(--danger); 
            font-weight: 600; 
            text-decoration: none; 
            border: 1.5px solid #fee2e2; 
            background: #fff5f5;
            padding: 9px 18px; 
            border-radius: 10px; 
            font-size: 14px;
            transition: all 0.2s ease; 
        }
        .logout-btn:hover { 
            background: var(--danger); 
            color: white; 
            border-color: var(--danger);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        /* --- ANALYTICS / STATS GRID --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 26px;
        }
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 500px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        .stat-card {
            background: var(--panel-bg);
            padding: 18px 22px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.02);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0; width: 4px;
        }
        .stat-card.active-q::before { background: var(--primary); }
        .stat-card.pending::before { background: var(--warning); }
        .stat-card.active-app::before { background: var(--success); }
        .stat-card.cleared::before { background: #8b5cf6; }

        .stat-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 26px; font-weight: 700; margin-top: 4px; color: var(--text-main); }
        .stat-sub { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* --- MAIN CARD & TOOLBAR --- */
        .main-card { 
            background: var(--panel-bg); 
            padding: 30px 32px; 
            border-radius: 18px; 
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03); 
            border: 1px solid var(--border-color);
        }
        .card-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 22px;
        }
        .main-card h2 { font-size: 18px; font-weight: 700; color: var(--text-main); margin: 0 0 4px 0; }
        .card-desc { color: var(--text-muted); font-size: 13.5px; margin: 0; }

        /* --- VIEW FILTER TABS & CLEAR BUTTON --- */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-muted);
            background: #f1f5f9;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .tab-btn:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }
        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(44, 125, 160, 0.25);
        }
        .tab-badge {
            background: rgba(0,0,0,0.12);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        .tab-btn.active .tab-badge {
            background: rgba(255,255,255,0.25);
            color: white;
        }

        .btn-clear-bulk {
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 3px 10px rgba(139, 92, 246, 0.25);
        }
        .btn-clear-bulk:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.35);
        }

        /* --- TABLE STYLING --- */
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { 
            background: #f8fafc; 
            color: var(--text-muted); 
            font-size: 12px; 
            text-transform: uppercase; 
            font-weight: 700; 
            letter-spacing: 0.5px;
            padding: 14px 18px;
            border-bottom: 2px solid var(--border-color);
        }
        td { 
            padding: 14px 18px; 
            border-bottom: 1px solid var(--border-color); 
            color: #334155;
            font-size: 14px;
            vertical-align: middle; 
        }
        tr:hover td { background-color: #fafafa; }
        tr:last-child td { border-bottom: none; }
        
        code { background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-weight: 600; color: #475569; font-size: 13px; }
        
        .student-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .student-thumb {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #2c7da0;
            background: #e2e8f0;
        }
        .student-name { font-weight: 600; color: var(--text-main); }
        
        .doc-link { 
            color: var(--primary); 
            font-weight: 600;
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .doc-link:hover { text-decoration: underline; color: var(--primary-hover); }
        
        .action-form { display: inline-flex; gap: 6px; margin: 0; align-items: center; }
        .btn-action { 
            border: none; 
            padding: 7px 12px; 
            font-weight: 600; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 12px; 
            color: white; 
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: transform 0.1s ease, box-shadow 0.2s ease; 
        }
        .btn-action:active { transform: scale(0.96); }
        .btn-approve { background: var(--success); }
        .btn-approve:hover { box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25); }
        .btn-reject { background: var(--danger); }
        .btn-reject:hover { box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25); }
        
        /* Clear / Archive button on rows */
        .btn-clear-row {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-clear-row:hover {
            background: #8b5cf6;
            color: white;
            border-color: #8b5cf6;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);
        }

        .btn-restore-row {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-restore-row:hover {
            background: #16a34a;
            color: white;
            border-color: #16a34a;
        }

        .status-pill { 
            padding: 5px 10px; 
            border-radius: 8px; 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            display: inline-block; 
            letter-spacing: 0.5px;
        }
        .status-Pending { background: #fef3c7; color: #b45309; }
        .status-Approved { background: #d1fae5; color: #065f46; }
        .status-Rejected { background: #fee2e2; color: #991b1b; }

        .badge-cleared-tag {
            display: inline-block;
            margin-top: 4px;
            font-size: 10px;
            font-weight: 700;
            color: #8b5cf6;
            background: #f5f3ff;
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid #ddd6fe;
        }
        
        .alert { padding: 12px 18px; margin-bottom: 20px; border-radius: 12px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    </style>
</head>
<body>

<div class="dashboard-container">

    <div class="header-bar">
        <div>
            <h1>Welcome back, <?php echo htmlspecialchars($officer_name); ?></h1>
            <div class="header-meta">
                <span>Verification Unit:</span>
                <span class="badge-unit"><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($officer_unit); ?> Desk</span>
                <span>• Live Clearance Stream</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card active-q">
            <div class="stat-label">Active Queue</div>
            <div class="stat-value"><?php echo $active_queue_count; ?></div>
            <div class="stat-sub">Pending + un-cleared items</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">Awaiting Review</div>
            <div class="stat-value"><?php echo $pending_docs; ?></div>
            <div class="stat-sub">Requires endorsement</div>
        </div>
        <div class="stat-card active-app">
            <div class="stat-label">Approved (In Queue)</div>
            <div class="stat-value"><?php echo $active_approved; ?></div>
            <div class="stat-sub">Ready to be cleared</div>
        </div>
        <div class="stat-card cleared">
            <div class="stat-label">Cleared & Archived</div>
            <div class="stat-value"><?php echo $cleared_approved; ?></div>
            <div class="stat-sub">Uncluttered approvals</div>
        </div>
    </div>

    <div class="main-card">
        <div class="card-header-row">
            <div>
                <h2>Document Clearance Queue & Archive</h2>
                <p class="card-desc">Review submitted receipts. You can clear approved documents anytime to keep your dashboard tidy and avoid overcrowded queues.</p>
            </div>
        </div>

        <?php if(!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="controls-row">
            <div class="filter-tabs">
                <a href="dashboard.php?view=active" class="tab-btn <?php echo $current_view === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-bolt"></i> Active Queue 
                    <span class="tab-badge"><?php echo $active_queue_count; ?></span>
                </a>
                <a href="dashboard.php?view=pending" class="tab-btn <?php echo $current_view === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-hourglass-half"></i> Pending Review 
                    <span class="tab-badge"><?php echo $pending_docs; ?></span>
                </a>
                <a href="dashboard.php?view=cleared" class="tab-btn <?php echo $current_view === 'cleared' ? 'active' : ''; ?>">
                    <i class="fas fa-box-archive"></i> Cleared Archive 
                    <span class="tab-badge"><?php echo $cleared_approved; ?></span>
                </a>
                <a href="dashboard.php?view=all" class="tab-btn <?php echo $current_view === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> All Files 
                    <span class="tab-badge"><?php echo $total_docs; ?></span>
                </a>
            </div>

            <?php if ($active_approved > 0): ?>
                <form method="POST" action="dashboard.php?view=<?php echo htmlspecialchars($current_view); ?>" onsubmit="return confirm('🧹 Clear all <?php echo $active_approved; ?> approved document(s) from your active view? This unclutters your queue while preserving students approved status.');">
                    <button type="submit" name="clear_all_approved" value="1" class="btn-clear-bulk">
                        <i class="fas fa-broom"></i> Clear All Approved (<?php echo $active_approved; ?>)
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Matric No</th>
                        <th>Student Details</th>
                        <th>Attached Receipt</th>
                        <th>Clearance Status</th>
                        <th style="text-align: center;">Decision / Action</th>
                        <th>Uploaded On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 50px 20px; color: var(--text-muted); font-style: italic;">
                                <div style="font-size: 32px; margin-bottom: 8px;">✨</div>
                                <?php if ($current_view === 'active'): ?>
                                    Your active queue is completely clear and uncluttered!
                                <?php elseif ($current_view === 'pending'): ?>
                                    No documents are currently awaiting review in the <?php echo htmlspecialchars($officer_unit); ?> queue.
                                <?php elseif ($current_view === 'cleared'): ?>
                                    No cleared/archived documents found.
                                <?php else: ?>
                                    No documents submitted yet.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                                // Normalize document path
                                $doc_file = $row['document_path'];
                                if (strpos($doc_file, 'http') === 0 || strpos($doc_file, '/') === 0) {
                                    $doc_url = $doc_file;
                                } elseif (strpos($doc_file, 'uploads/') === 0) {
                                    $doc_url = '../' . $doc_file;
                                } else {
                                    $doc_url = '../uploads/documents/' . $doc_file;
                                }

                                // Student photo
                                $thumb_fallback = 'https://ui-avatars.com/api/?background=2c7da0&color=fff&rounded=true&bold=true&size=42&name=' . urlencode($row['student_name'] ?? 'S');
                                $thumb_url = $thumb_fallback;
                                if (!empty($row['face_image'])) {
                                    if (strpos($row['face_image'], 'http') === 0 || strpos($row['face_image'], '/') === 0) {
                                        $thumb_url = $row['face_image'];
                                    } else {
                                        $disk_p = '../' . ltrim($row['face_image'], '/');
                                        if (file_exists($disk_p)) {
                                            $thumb_url = $disk_p;
                                        }
                                    }
                                }

                                $is_doc_cleared = intval($row['is_cleared'] ?? 0);
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['matric_no']); ?></code></td>
                                <td>
                                    <div class="student-cell">
                                        <img src="<?php echo htmlspecialchars($thumb_url); ?>" class="student-thumb" alt="Photo" onerror="this.src='<?php echo htmlspecialchars($thumb_fallback); ?>';">
                                        <span class="student-name"><?php echo htmlspecialchars($row['student_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($doc_url); ?>" target="_blank" class="doc-link">
                                        <i class="fas fa-file-alt"></i> View Receipt
                                    </a>
                                </td>
                                <td>
                                    <span class="status-pill status-<?php echo $row['status']; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                    <?php if ($row['status'] === 'Approved' && $is_doc_cleared == 1): ?>
                                        <div><span class="badge-cleared-tag"><i class="fas fa-archive"></i> Cleared from queue</span></div>
                                    <?php endif; ?>
                                    <?php if ($row['status'] === 'Rejected' && !empty($row['rejection_comment'])): ?>
                                        <div style="font-size:11px; color:#ef4444; margin-top:4px; max-width:180px;">
                                            <i class="fas fa-comment"></i> <?php echo htmlspecialchars($row['rejection_comment']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($row['status'] === 'Pending'): ?>
                                        <!-- Review Decision Buttons for Pending Files -->
                                        <form method="POST" action="dashboard.php?view=<?php echo htmlspecialchars($current_view); ?>" class="action-form" onsubmit="return handleDecision(this);">
                                            <input type="hidden" name="document_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="rejection_comment" value="">
                                            
                                            <button type="submit" name="action" value="Approved" class="btn-action btn-approve" title="Approve this receipt">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="Rejected" class="btn-action btn-reject" title="Reject this receipt">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>

                                    <?php elseif ($row['status'] === 'Approved'): ?>
                                        <?php if ($is_doc_cleared == 0): ?>
                                            <!-- Clear single approved document from queue -->
                                            <form method="POST" action="dashboard.php?view=<?php echo htmlspecialchars($current_view); ?>" class="action-form">
                                                <input type="hidden" name="document_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="clear_single" value="1" class="btn-clear-row" title="Clear this document from active queue">
                                                    <i class="fas fa-broom"></i> Clear from Queue
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Restore to active queue if in Cleared view -->
                                            <form method="POST" action="dashboard.php?view=<?php echo htmlspecialchars($current_view); ?>" class="action-form">
                                                <input type="hidden" name="document_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="restore_single" value="1" class="btn-restore-row" title="Restore to active queue">
                                                    <i class="fas fa-undo"></i> Restore to Queue
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <!-- Rejected: allow officer to re-evaluate -->
                                        <form method="POST" action="dashboard.php?view=<?php echo htmlspecialchars($current_view); ?>" class="action-form">
                                            <input type="hidden" name="document_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <button type="submit" name="action" value="Approved" class="btn-action btn-approve" title="Re-approve document">
                                                <i class="fas fa-redo"></i> Re-Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color: var(--text-muted); font-size: 13px;">
                                        <?php echo date("M d, Y • h:i A", strtotime($row['uploaded_at'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    function handleDecision(form) {
        const actionBtn = document.activeElement;
        if (actionBtn && actionBtn.value === 'Rejected') {
            const reason = prompt("Enter feedback or reason for rejecting this document (optional):", "Receipt is illegible or missing required stamp.");
            if (reason === null) {
                // User cancelled the prompt
                return false;
            }
            form.querySelector('input[name="rejection_comment"]').value = reason;
        }
        return true;
    }
</script>

</body>
</html>