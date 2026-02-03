<?php
/**
 * Database configuration
 *
 * Set these values to match your MySQL database credentials.
 * For security, consider moving this file outside the web root
 * or using environment variables.
 */

define('DB_HOST', getenv('MYSQL_HOST') ?: 'localhost');
define('DB_USER', getenv('MYSQL_USER') ?: 'your_username');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: 'your_password');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'your_database');

/**
 * Get PDO database connection
 */
function getDatabase() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            // Create sessions table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    session_id VARCHAR(255) PRIMARY KEY,
                    performers TEXT,
                    programme_items TEXT,
                    concert_info TEXT,
                    updated_at BIGINT
                )
            ");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }

    return $pdo;
}
