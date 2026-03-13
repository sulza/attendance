-- ============================================================
-- VISITING LECTURER ATTENDANCE MANAGEMENT SYSTEM
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS visiting_lecturer_attendance
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE visiting_lecturer_attendance;

-- -------------------------------------------------------
-- DEPARTMENTS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    faculty VARCHAR(150),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- USERS (Admin, HOD, Lecturer)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','hod','lecturer') NOT NULL DEFAULT 'lecturer',
    department_id INT,
    staff_id VARCHAR(50) UNIQUE,
    gender ENUM('male','female','other'),
    qualification VARCHAR(200),
    specialization VARCHAR(200),
    profile_photo VARCHAR(255) DEFAULT 'default.png',
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    last_login DATETIME,
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- -------------------------------------------------------
-- ACADEMIC SESSIONS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(50) NOT NULL,  -- e.g. 2024/2025
    semester ENUM('first','second','summer') NOT NULL,
    start_date DATE,
    end_date DATE,
    is_current TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- COURSES
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_title VARCHAR(200) NOT NULL,
    course_code VARCHAR(30) NOT NULL UNIQUE,
    credit_units INT DEFAULT 3,
    level ENUM('100','200','300','400','500','600','PG') DEFAULT '100',
    department_id INT,
    description TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- -------------------------------------------------------
-- COURSE ASSIGNMENTS (Lecturer → Course)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS course_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    course_id INT NOT NULL,
    session_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_assignment (lecturer_id, course_id, session_id)
);

-- -------------------------------------------------------
-- ATTENDANCE RECORDS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    course_id INT NOT NULL,
    session_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME,
    duration_hours DECIMAL(4,2),
    topic_covered TEXT,
    venue VARCHAR(150),
    students_present INT DEFAULT 0,
    lecture_type ENUM('theory','practical','seminar','tutorial','field_work') DEFAULT 'theory',
    teaching_method ENUM('lecture','discussion','demonstration','project','workshop') DEFAULT 'lecture',
    materials_used TEXT,
    remarks TEXT,
    status ENUM('pending','verified','rejected') DEFAULT 'pending',
    verified_by INT,
    verified_at DATETIME,
    rejection_reason TEXT,
    sign_in_ip VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- -------------------------------------------------------
-- NOTIFICATIONS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- -------------------------------------------------------
-- ACTIVITY LOGS
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(200) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- -------------------------------------------------------
-- PAYMENT / CLAIMS (Optional feature)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecturer_id INT NOT NULL,
    session_id INT NOT NULL,
    total_hours DECIMAL(6,2),
    rate_per_hour DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('draft','submitted','approved','paid','rejected') DEFAULT 'draft',
    submitted_at DATETIME,
    approved_by INT,
    approved_at DATETIME,
    payment_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- -------------------------------------------------------
-- SEED DATA
-- -------------------------------------------------------

-- Departments
INSERT INTO departments (name, code, faculty) VALUES
('Computer Science', 'CSC', 'Faculty of Sciences'),
('Mathematics', 'MTH', 'Faculty of Sciences'),
('Physics', 'PHY', 'Faculty of Sciences'),
('English Language', 'ENG', 'Faculty of Arts'),
('Business Administration', 'BUS', 'Faculty of Management Sciences');

-- Academic Session
INSERT INTO academic_sessions (session_name, semester, start_date, end_date, is_current) VALUES
('2024/2025', 'second', '2025-01-15', '2025-06-30', 1),
('2024/2025', 'first', '2024-09-01', '2024-12-20', 0);

-- Default Admin (password: Admin@123)
INSERT INTO users (full_name, email, phone, password, role, staff_id, status) VALUES
('System Administrator', 'admin@university.edu', '+234-800-000-0001',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uDe9oM8IS', 'admin', 'ADM001', 'active');

-- Default HOD (password: Hod@12345)
INSERT INTO users (full_name, email, phone, password, role, department_id, staff_id, status) VALUES
('Dr. James Okonkwo', 'hod.csc@university.edu', '+234-800-000-0002',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uDe9oM8IS', 'hod', 1, 'HOD001', 'active');

-- Sample Lecturer (password: password)
INSERT INTO users (full_name, email, phone, password, role, department_id, staff_id, qualification, specialization, status) VALUES
('Prof. Adaeze Nwosu', 'lecturer1@university.edu', '+234-800-000-0003',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uDe9oM8IS', 'lecturer', 1, 'LEC001', 'PhD Computer Science', 'Artificial Intelligence', 'active'),
('Mr. Chukwuemeka Eze', 'lecturer2@university.edu', '+234-800-000-0004',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uDe9oM8IS', 'lecturer', 1, 'LEC002', 'MSc Software Engineering', 'Web Development', 'active');

-- Courses
INSERT INTO courses (course_title, course_code, credit_units, level, department_id) VALUES
('Introduction to Programming', 'CSC101', 3, '100', 1),
('Data Structures and Algorithms', 'CSC201', 3, '200', 1),
('Database Management Systems', 'CSC301', 3, '300', 1),
('Artificial Intelligence', 'CSC401', 3, '400', 1),
('Web Technologies', 'CSC302', 2, '300', 1),
('Computer Networks', 'CSC303', 3, '300', 1),
('Software Engineering', 'CSC402', 3, '400', 1),
('Machine Learning', 'CSC501', 3, '500', 1);

-- Course Assignments
INSERT INTO course_assignments (lecturer_id, course_id, session_id, assigned_by) VALUES
(3, 1, 1, 1), (3, 4, 1, 1),
(4, 2, 1, 1), (4, 5, 1, 1);

-- -------------------------------------------------------
-- SETTINGS TABLE
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('institution_name', 'Federal University of Technology', 'Institution name'),
('institution_address', 'Abuja, Nigeria', 'Institution address'),
('system_email', 'admin@university.edu', 'System email'),
('require_hod_approval', '1', 'Require HOD verification'),
('max_lecture_hours', '4', 'Max hours per lecture'),
('session_timeout', '3600', 'Session timeout (seconds)'),
('allow_backdating', '7', 'Days allowed to backdate attendance');
