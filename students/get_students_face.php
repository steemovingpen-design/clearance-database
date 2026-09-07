<?php
// students/get_students_face.php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (isset($_GET['matric_no'])) {
    $matric_no = trim($_GET['matric_no']);
    
    $stmt = mysqli_prepare($conn, "SELECT face_descriptor FROM students WHERE matric_no = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $matric_no);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if (!empty($row['face_descriptor'])) {
                echo json_encode(['success' => true, 'face_descriptor' => $row['face_descriptor']]);
                mysqli_stmt_close($stmt);
                exit();
            }
        }
        mysqli_stmt_close($stmt);
    }
}

echo json_encode(['success' => false, 'message' => 'No vector tracking match found.']);
?>