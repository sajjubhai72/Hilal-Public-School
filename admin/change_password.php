<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPwd  = $_POST['current_password'] ?? '';
    $newPwd      = $_POST['new_password'] ?? '';
    $confirmPwd  = $_POST['confirm_password'] ?? '';
    $adminId     = $_SESSION['admin_id'];

    $user = $conn->query("SELECT password FROM users WHERE id=$adminId")->fetch_assoc();

    if (!password_verify($currentPwd, $user['password'])) {
        $_SESSION['pwd_error'] = 'Current password is incorrect.';
    } elseif ($newPwd !== $confirmPwd) {
        $_SESSION['pwd_error'] = 'New passwords do not match.';
    } elseif (strlen($newPwd) < 6) {
        $_SESSION['pwd_error'] = 'Password must be at least 6 characters.';
    } else {
        $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hashed' WHERE id=$adminId");
        $_SESSION['pwd_success'] = 'Password changed successfully!';
    }
}

header('Location: settings.php');
exit();
