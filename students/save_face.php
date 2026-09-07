<?php
// students/save_face.php
require_once '../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $matric_no = trim($_POST['matric_no'] ?? '');
    $face_matrix = trim($_POST['face_matrix'] ?? '');

    if (empty($matric_no) || empty($face_matrix)) {
        echo json_encode(['success' => false, 'message' => 'Missing matric number or biometric data.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "UPDATE students SET face_descriptor = ? WHERE matric_no = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database Query Error: ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ss", $face_matrix, $matric_no);
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) >= 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Matric number not found in record. Ensure account is created first.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Execute Error: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}
?>