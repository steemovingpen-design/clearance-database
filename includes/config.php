<?php
// includes/config.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "clearance_system";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// Auto-heal: Ensure face_image column exists on students table
$check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'students'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $col_check = @mysqli_query($conn, "SHOW COLUMNS FROM students LIKE 'face_image'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        @mysqli_query($conn, "ALTER TABLE students ADD COLUMN face_image VARCHAR(255) NULL AFTER face_descriptor");
    }
}

// Auto-heal: Ensure is_cleared column exists on clearance_documents table
$check_doc_table = @mysqli_query($conn, "SHOW TABLES LIKE 'clearance_documents'");
if ($check_doc_table && mysqli_num_rows($check_doc_table) > 0) {
    $col_doc_check = @mysqli_query($conn, "SHOW COLUMNS FROM clearance_documents LIKE 'is_cleared'");
    if ($col_doc_check && mysqli_num_rows($col_doc_check) == 0) {
        @mysqli_query($conn, "ALTER TABLE clearance_documents ADD COLUMN is_cleared TINYINT(1) NOT NULL DEFAULT 0 AFTER rejection_comment");
    }
}
?>