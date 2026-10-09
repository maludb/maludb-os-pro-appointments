<?php
/**
 * Database Configuration
 *
 * PDO singleton connection to PostgreSQL. The connection details come from app_config() (config/app.php):
 * the environment, config/.env (what the Business OS installer writes: DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASSWORD) or config/local.php. Nothing is written here.
 */

require_once __DIR__ . '/app.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $host = (string)app_config('DB_HOST', '127.0.0.1');
        $port = (string)app_config('DB_PORT', '5432');
        $name = (string)app_config('DB_NAME', 'zozocal');
        $user = (string)app_config('DB_USER', 'zozocal');
        $pass = (string)app_config('DB_PASSWORD', app_config('DB_PASS', ''));
        try {
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;sslmode=disable", $host, $port, $name);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ];
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
