<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

final class Database
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $database;
    private string $charset;
    public ?PDO    $connection = null;

    public function __construct()
    {
        $this->host     = HOST;
        $this->port     = DB_PORT;
        $this->username = USERNAME;
        $this->password = PASSWORD;
        $this->database = DATABASE;
        $this->charset  = DB_CHARSET;
    }

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return $this->connection;
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw $e;
            }
            http_response_code(500);
            exit('Database connection error.');
        }
    }
}
