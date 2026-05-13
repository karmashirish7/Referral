<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    logAction($pdo, currentUserId(), currentUser()['name'], 'logout', 'user', currentUserId(), 'User logged out');
}

session_destroy();
header("Location: index.php");
exit;
