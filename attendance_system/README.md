# VLA - Visiting Lecturer Attendance Management System

A professional, full-featured web-based system for managing visiting lecturer attendance built with PHP 8+ and MySQL.

---

## 🚀 Features

### Lecturer Portal
- Secure login with role-based access
- Mark attendance: select course, date, time in/out, topic, venue, students count
- Auto-calculated duration
- View personal attendance history with status indicators
- View assigned courses and statistics
- Profile management & password change

### HOD Portal
- Department dashboard with key metrics
- View and verify/reject attendance submissions
- Detailed attendance records with search & filtering
- Generate and download CSV attendance reports
- Monitor all department lecturers' performance

### Admin Portal
- Full system dashboard with charts & analytics
- User management (Admins, HODs, Lecturers)
- Department management
- Course management
- Academic session management
- Course assignments (assign courses to lecturers)
- Complete attendance oversight and verification
- Comprehensive reports with filtering
- Activity audit logs
- System settings configuration

---

## 📋 Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Web server: Apache/Nginx (XAMPP/WAMP/LAMP supported)
- Enabled PHP extensions: mysqli, session

---

## ⚙️ Installation

### 1. Copy Files
Place the `attendance_system` folder in your web root:
```
/xampp/htdocs/attendance_system/
```

### 2. Create Database
Open MySQL (phpMyAdmin or CLI) and run:
```sql
SOURCE /path/to/attendance_system/config/schema.sql;
```

### 3. Configure Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('DB_NAME', 'visiting_lecturer_attendance');
define('BASE_URL', 'http://localhost/attendance_system/');
```

### 4. Access the System
Open: `http://localhost/attendance_system/`

---

## 🔑 Default Login Credentials

| Role     | Email                      | Password   |
|----------|----------------------------|------------|
| Admin    | admin@university.edu       | password   |
| HOD      | hod.csc@university.edu     | password   |
| Lecturer | lecturer1@university.edu   | password   |
| Lecturer | lecturer2@university.edu   | password   |

> ⚠️ **Change all passwords immediately after installation!**

---

## 📁 Project Structure
```
attendance_system/
├── index.php              # Login page
├── logout.php             # Logout handler
├── config/
│   ├── database.php       # DB config + all helper functions
│   └── schema.sql         # Database schema + seed data
├── includes/
│   ├── header.php         # Sidebar + topbar template
│   └── footer.php         # Footer template
├── admin/                 # Admin role pages
│   ├── dashboard.php
│   ├── lecturers.php
│   ├── departments.php
│   ├── courses.php
│   ├── sessions.php
│   ├── assignments.php
│   ├── attendance.php
│   ├── reports.php
│   ├── activity_logs.php
│   └── settings.php
├── hod/                   # HOD role pages
│   ├── dashboard.php
│   ├── attendance.php
│   ├── lecturers.php
│   └── reports.php
├── lecturer/              # Lecturer role pages
│   ├── dashboard.php
│   ├── log_attendance.php
│   ├── my_attendance.php
│   └── my_courses.php
└── assets/
    ├── css/style.css      # Professional stylesheet
    └── js/app.js          # Frontend JavaScript
```

---

## 🛡️ Security Features
- Password hashing (bcrypt via `password_hash`)
- SQL injection prevention via prepared statements
- Session-based authentication with role enforcement
- Activity logging for all key actions
- Input sanitization throughout

---

## 📊 Reports
The system supports CSV export for:
- Attendance records (filterable by lecturer, course, date range, status)
- Summary reports per lecturer

---

## License
For educational and institutional use. MIT License.
