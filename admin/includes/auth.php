<?php
/* =====================================================
   ADMIN AUTH CHECK
   Include this at the top of every admin page
   ===================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: ' . (strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? '' : 'admin/') . 'login.php');
    exit();
}

// Session timeout — 8 hours
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 28800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit();
}
$_SESSION['login_time'] = time(); // refresh

// CSRF verify on POST — skip for now, re-enable after testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../includes/db.php';
    // verifyCsrf(); // temporarily disabled
} else {
    require_once __DIR__ . '/../../includes/db.php';
}

// Refresh admin info
$adminId   = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminRole = $_SESSION['admin_role'];
