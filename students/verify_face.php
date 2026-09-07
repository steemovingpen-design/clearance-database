<?php
// students/verify_face.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['face_verified']) && $_POST['face_verified'] == '1') {
    $matric_no = trim($_POST['matric_no'] ?? '');
    
    $stmt = mysqli_prepare($conn, "SELECT id, fullname, matric_no FROM students WHERE matric_no = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $matric_no);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($student = mysqli_fetch_assoc($result)) {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['fullname'];
            $_SESSION['student_matric'] = $student['matric_no'];
            $_SESSION['matric_no'] = $student['matric_no'];
            
            header("Location: dashboard.php");
            exit();
        }
        mysqli_stmt_close($stmt);
    }
}

header("Location: login.php");
exit();