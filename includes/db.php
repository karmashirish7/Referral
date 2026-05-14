<?php
// ============================================================
// Database Connection
// Credentials are stored in config.php (created by install.php).
// If config.php doesn't exist, redirect to the installer.
// ============================================================

$configFile = __DIR__ . '/../config.php';

if (!file_exists($configFile)) {
    header("Location: install.php");
    exit;
}

require_once $configFile;

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Connection failed — show helpful error with link to re-run installer
    die('<!DOCTYPE html><html><head>
        <title>Database Error</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <style>
            body{font-family:-apple-system,sans-serif;background:#f0f4f9;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
            .box{background:#fff;border-radius:12px;padding:40px;max-width:480px;width:100%;box-shadow:0 4px 20px rgba(0,0,0,.08);text-align:center}
            h2{color:#dc2626;margin-bottom:8px}p{color:#718096;font-size:14px}
            code{background:#f1f5f9;padding:2px 8px;border-radius:4px;font-size:13px;color:#1F3864}
            a{display:inline-block;margin-top:20px;background:#1F3864;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-size:14px}
        </style></head><body>
        <div class="box">
            <i class="bi bi-database-x" style="font-size:40px;color:#dc2626"></i>
            <h2>Database Connection Failed</h2>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p>Your saved credentials in <code>config.php</code> may be wrong, or MySQL is not running.</p>
            <a href="install.php">Re-run Installer</a>
        </div></body></html>');
}
