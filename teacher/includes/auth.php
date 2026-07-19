<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['teacher_id']) || $_SESSION['teacher_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Session timeout — 8 hours
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 28800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['login_time'] = time();

// CSRF verify on POST — skip for now, re-enable after testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../includes/db.php';
    // verifyCsrf(); // temporarily disabled
} else {
    require_once __DIR__ . '/../../includes/db.php';
}

$teacherId   = $_SESSION['teacher_id'];
$teacherName = $_SESSION['teacher_name'];

// Get teacher's assigned classes
$assignedClasses = $conn->query("
    SELECT DISTINCT tc.class_id, c.class_name, tc.subject_id, s.subject_name, tc.is_class_teacher
    FROM teacher_classes tc
    JOIN classes c ON tc.class_id = c.id
    JOIN subjects s ON tc.subject_id = s.id
    WHERE tc.teacher_id = $teacherId
    ORDER BY c.id, s.subject_name
")->fetch_all(MYSQLI_ASSOC);

// Is this teacher a class teacher for any class?
$isClassTeacherAnywhere = $conn->query("
    SELECT COUNT(*) as c FROM teacher_classes
    WHERE teacher_id=$teacherId AND is_class_teacher=1
")->fetch_assoc()['c'] > 0;
