<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

function requireTutorLogin()
{
    if (!isset($_SESSION['tutor_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdminLogin()
{
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

function getCurrentTutor()
{
    if (!isset($_SESSION['tutor_id']))
        return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tutors WHERE id = ?");
    $stmt->execute([$_SESSION['tutor_id']]);
    return $stmt->fetch();
}

function getCurrentAdmin()
{
    if (!isset($_SESSION['admin_id']))
        return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

function loginTutor($username, $password)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tutors WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $tutor = $stmt->fetch();
    if ($tutor && password_verify($password, $tutor['password'])) {
        $_SESSION['tutor_id'] = $tutor['id'];
        $_SESSION['tutor_name'] = $tutor['first_name'] . ' ' . $tutor['last_name'];
        $_SESSION['tutor_nickname'] = $tutor['nickname'];
        return true;
    }
    return false;
}

function loginAdmin($username, $password)
{
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        return true;
    }
    return false;
}

function logout()
{
    session_destroy();
}

function isTutorLoggedIn()
{
    return isset($_SESSION['tutor_id']);
}

function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']);
}
