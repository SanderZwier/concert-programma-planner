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

            // Create concerts table if it doesn't exist
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS concerts (
                    slug VARCHAR(255) PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    concert_date DATE,
                    performers TEXT,
                    programme_items TEXT,
                    created_at BIGINT,
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
