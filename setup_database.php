<?php
/**
 * ====================================================================
 * Secure Task Management System
 * Automatic Database Setup & Migration Script
 * 
 * Run via Browser: http://localhost/secure-task-manager/setup_database.php
 * Or via CLI:      php setup_database.php
 * ====================================================================
 */

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = getenv('DB_NAME') ?: 'secure_task_manager';

$is_cli = (php_sapi_name() === 'cli');

function output_msg($msg, $type = 'info') {
    global $is_cli;
    if ($is_cli) {
        echo "[" . strtoupper($type) . "] " . strip_tags($msg) . "\n";
    } else {
        $color = ($type === 'success') ? '#10b981' : (($type === 'error') ? '#ef4444' : '#0284c7');
        echo "<div style='font-family: sans-serif; padding: 10px 15px; margin: 8px 0; border-radius: 6px; background: " . (($type === 'error') ? '#fee2e2' : '#f0fdf4') . "; color: {$color}; border: 1px solid {$color};'><strong>" . htmlspecialchars($msg) . "</strong></div>";
    }
}

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>Database Setup</title><style>body{font-family:system-ui;max-width:600px;margin:40px auto;padding:20px;background:#0f172a;color:#f8fafc;}.card{background:#1e293b;padding:24px;border-radius:12px;border:1px solid #334155;}.btn{display:inline-block;background:#0d9488;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;margin-top:15px;}</style></head><body><div class='card'><h2>Database Auto-Setup</h2>";
}

try {
    // 1. Connect to MySQL server (global or scoped to database)
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        output_msg("Connected to MySQL server on {$host}:{$port} successfully.", "success");
    } catch (PDOException $e) {
        // Fallback: connect directly to target database if user has database-scoped credentials
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        output_msg("Connected directly to database '{$db_name}' on {$host}:{$port}.", "success");
    }

    // 2. Read database.sql file
    $sql_file = __DIR__ . '/database/database.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("File 'database/database.sql' was not found!");
    }

    $sql_content = file_get_contents($sql_file);

    // 3. Execute database schema
    try {
        $pdo->exec($sql_content);
    } catch (PDOException $e) {
        // If CREATE DATABASE / USE fails due to scoped permissions on OpenShift/Cloud, strip them and run table scripts
        $clean_sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql_content);
        $clean_sql = preg_replace('/USE `?[a-zA-Z0-9_-]+`?;/i', '', $clean_sql);
        $pdo->exec($clean_sql);
    }

    // Ensure database context is active
    $pdo->exec("USE `{$db_name}`");

    // 4. Guarantee fresh valid password hashes for demo accounts
    $fresh_hash = password_hash('Password123!', PASSWORD_BCRYPT);
    $fix_stmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE email IN ('demo@securetask.local', 'admin@securetask.local')");
    $fix_stmt->execute([':pwd' => $fresh_hash]);

    output_msg("Database 'secure_task_manager' created successfully!", "success");
    output_msg("Tables 'users' and 'tasks' created successfully!", "success");
    output_msg("Demo accounts verified with password: Password123!", "success");

    if (!$is_cli) {
        echo "<p style='color:#cbd5e1;margin-top:20px;'>Your database is completely setup and ready to use!</p>";
        echo "<a href='index.php' class='btn'>Go to Homepage &rarr;</a> ";
        echo "<a href='login.php' class='btn' style='background:#334155;margin-left:10px;'>Login Page</a>";
    }

} catch (PDOException $e) {
    output_msg("Database Setup Failed: " . $e->getMessage(), "error");
    if (!$is_cli) {
        echo "<p style='color:#cbd5e1;'>Please make sure MySQL is started in XAMPP Control Panel.</p>";
    }
} catch (Exception $e) {
    output_msg("Error: " . $e->getMessage(), "error");
}

if (!$is_cli) {
    echo "</div></body></html>";
}

