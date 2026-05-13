<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], (array)$roles)) {
        header("Location: dashboard.php");
        exit;
    }
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function currentRole() {
    return $_SESSION['user_role'] ?? null;
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function isRole($role) {
    return ($_SESSION['user_role'] ?? '') === $role;
}
