# Hilal Public Secondary School Website
## Setup Guide

---

## Requirements
- XAMPP (PHP 8.0+, MySQL 5.7+, Apache)
- Web Browser

---

## Installation Steps

### 1. Copy Files
Copy the `hilal-school-website` folder to:
```
C:/xampp/htdocs/hilal-school-website/
```

### 2. Import Database
1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Create new database: `hilal_school`
3. Click **Import** → Select `database/hilal_school.sql`
4. Click **Go**

### 3. Configure Database
Open `includes/db.php` and update if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');       // Your MySQL password
define('DB_NAME', 'hilal_school');
```

### 4. Add School Logo
Copy your school logo to:
```
assets/images/logo.png
```

### 5. Run Website
Open browser → `http://localhost/hilal-school-website/`

---

## Login Credentials

### Admin Panel
- URL: `http://localhost/hilal-school-website/admin/login.php`
- Username: `admin`
- Password: `Admin@1234`
> **Change this password immediately after first login!**

### Teacher Portal
- URL: `http://localhost/hilal-school-website/teacher/login.php`
- Teachers are added by Admin
- Admin sets username & password when adding teacher

---

## Project Structure
```
hilal-school-website/
├── index.php               # Home page
├── about.php               # About Us
├── results.php             # Result checker
├── notices.php             # Notice board
├── teachers.php            # Teachers listing
├── admissions.php          # Online admission form
├── scholarship.php         # Scholarship application
├── events.php              # Events calendar
├── gallery.php             # Photo gallery
├── contact.php             # Contact form
│
├── admin/                  # Admin panel
│   ├── login.php
│   ├── dashboard.php
│   ├── teachers.php        # Add/edit teachers
│   ├── students.php        # Manage students
│   ├── classes.php         # Classes & subjects
│   ├── results.php         # Create exams, publish results
│   ├── view_results.php    # View all results + CSV export
│   ├── notices.php
│   ├── events.php
│   ├── gallery.php
│   ├── admissions.php      # View + CSV export admissions
│   ├── scholarship.php
│   ├── messages.php
│   └── settings.php        # School settings + logo
│
├── teacher/                # Teacher portal
│   ├── login.php
│   ├── dashboard.php
│   ├── marks_entry.php     # Enter exam marks
│   ├── attendance.php      # Mark daily attendance
│   └── view_marks.php      # View entered marks
│
├── api/                    # AJAX PHP endpoints
│   ├── get_result.php      # Public result checker
│   ├── send_contact.php    # Contact form
│   ├── submit_admission.php
│   └── submit_scholarship.php
│
├── assets/
│   ├── css/style.css       # Public site styles
│   ├── css/admin.css       # Admin panel styles
│   └── js/main.js          # jQuery scripts
│
├── includes/               # Shared PHP files
│   ├── db.php              # Database + helpers
│   ├── header.php
│   └── footer.php
│
├── uploads/                # Uploaded files
│   ├── notices/
│   ├── gallery/
│   ├── events/
│   ├── teachers/
│   ├── admissions/
│   └── scholarship/
│
└── database/
    └── hilal_school.sql    # Full database schema
```

---

## Workflow

### Adding a Teacher
1. Admin → Teachers → Add Teacher
2. Set username + password
3. Assign classes and subjects in Classes page

### Publishing Results
1. Admin creates exam (Results page)
2. Teacher logs in → Enter Marks → selects exam → enters marks per subject
3. Admin reviews marks (View Results)
4. Admin clicks **Publish** → Students can view on website

### Student Result Check
Students visit: `results.php`
- Select: Year → Exam Type → Class → Roll No → DOB
- Click Check Result → Result card shown with print option

---

## Colors Used (from school logo)
- Primary: `#1a5c2a` (Deep Green)
- Secondary: `#c0392b` (Red)
- Accent: `#f0a500` (Gold)
- Dark: `#1a2a3a` (Navy)
