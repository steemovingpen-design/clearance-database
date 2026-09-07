-- =======================================================
-- ElevateClear: Digital Biometric Clearance System Database
-- Database: clearance_system
-- =======================================================

CREATE DATABASE IF NOT EXISTS `clearance_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clearance_system`;

-- --------------------------------------------------------
-- Table structure for `academic_affairs_data`
-- (Official institutional records used during student registration)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_affairs_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `matric_no` VARCHAR(50) NOT NULL UNIQUE,
  `fullname` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `faculty` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `level` VARCHAR(20) NOT NULL DEFAULT '400',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed sample academic records for testing student registration
INSERT INTO `academic_affairs_data` (`matric_no`, `fullname`, `email`, `phone`, `faculty`, `department`, `level`) VALUES
('CS2026/001', 'Adewale Johnson Olumide', 'adewale.j@university.edu.ng', '08031234567', 'Physical Sciences', 'Computer Science', '400'),
('CS2026/002', 'Chioma Blessing Eze', 'chioma.eze@university.edu.ng', '08052345678', 'Physical Sciences', 'Computer Science', '400'),
('ACC-2023-001', 'Babajide Emmanuel Bello', 'b.bello@university.edu.ng', '08073456789', 'Management Sciences', 'Accounting', '400'),
('BF-2023-008', 'Fatima Abubakar Dahiru', 'fatima.d@university.edu.ng', '08094567890', 'Management Sciences', 'Banking and Finance', '400'),
('ENG2026/015', 'Chukwuemeka David Okafor', 'c.okafor@university.edu.ng', '08125678901', 'Engineering', 'Electrical Engineering', '400')
ON DUPLICATE KEY UPDATE fullname=VALUES(fullname);

-- --------------------------------------------------------
-- Table structure for `students`
-- (Registered students with biometric descriptors and captured photos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `matric_no` VARCHAR(50) NOT NULL UNIQUE,
  `fullname` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `faculty` VARCHAR(100) DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `level` VARCHAR(20) DEFAULT NULL,
  `face_descriptor` LONGTEXT DEFAULT NULL,
  `face_image` VARCHAR(255) DEFAULT NULL,
  `clearance_status` INT NOT NULL DEFAULT 0,
  `unique_clearance_id` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `officers`
-- (Clearance desk verification officers)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `officers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(150) NOT NULL,
  `unit_name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default clearance officers (Default password: password123)
-- Password hash generated with PASSWORD_BCRYPT for 'password123'
INSERT INTO `officers` (`username`, `password`, `fullname`, `unit_name`) VALUES
('bursary_officer', '$2y$10$tZ2zV9Kq8a3vS6xUvD246e7fB0bN5gB0XWfR9Qx6oE8bM0u.0zM4K', 'Mr. Samuel Alabi (Bursary)', 'Bursary'),
('library_officer', '$2y$10$tZ2zV9Kq8a3vS6xUvD246e7fB0bN5gB0XWfR9Qx6oE8bM0u.0zM4K', 'Mrs. Funke Balogun (Library)', 'Library'),
('dean_officer', '$2y$10$tZ2zV9Kq8a3vS6xUvD246e7fB0bN5gB0XWfR9Qx6oE8bM0u.0zM4K', 'Dr. Kenneth Okon (Dean Office)', 'Dean'),
('medical_officer', '$2y$10$tZ2zV9Kq8a3vS6xUvD246e7fB0bN5gB0XWfR9Qx6oE8bM0u.0zM4K', 'Dr. Aisha Mahmud (Medical)', 'Medical'),
('sug_officer', '$2y$10$tZ2zV9Kq8a3vS6xUvD246e7fB0bN5gB0XWfR9Qx6oE8bM0u.0zM4K', 'Comrade Tunde Ade (SUG)', 'SUG'),
('dept_officer', '$2y$10$tZ2zV9Kq8a3vS6xUvD246e7fB0bN5gB0XWfR9Qx6oE8bM0u.0zM4K', 'Prof. Grace Nnaji (HOD Desk)', 'Department')
ON DUPLICATE KEY UPDATE fullname=VALUES(fullname);

-- --------------------------------------------------------
-- Table structure for `clearance_documents`
-- (Student uploaded receipts and endorsement verification logs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clearance_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `matric_no` VARCHAR(50) NOT NULL,
  `unit_name` VARCHAR(50) NOT NULL,
  `document_path` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `rejection_comment` TEXT DEFAULT NULL,
  `is_cleared` TINYINT(1) NOT NULL DEFAULT 0,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_student_unit` (`matric_no`, `unit_name`),
  KEY `idx_matric` (`matric_no`),
  KEY `idx_unit` (`unit_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
