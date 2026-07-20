<?php
declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';
EnvLoader::load(__DIR__ . '/.env');

/**
 * Database connection constants.
 * Values come from .env; the defaults keep local XAMPP dev working out of the box.
 * The names (HOST, USERNAME, DATABASE, PASSWORD) are kept for backward compatibility
 * with database.php and any legacy code that references them directly.
 */
defined('HOST')     || define('HOST',     env('DB_HOST', 'localhost'));
defined('DB_PORT')  || define('DB_PORT',  (int) env('DB_PORT', 3306));
defined('DATABASE') || define('DATABASE', env('DB_NAME', 'duanmau_atgt'));
defined('USERNAME') || define('USERNAME', env('DB_USER', 'root'));
defined('PASSWORD') || define('PASSWORD', env('DB_PASSWORD', ''));
defined('DB_CHARSET') || define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

defined('APP_ENV')   || define('APP_ENV',   env('APP_ENV', 'local'));
defined('APP_DEBUG') || define('APP_DEBUG', (bool) env('APP_DEBUG', false));

// SMTP placeholders (unused until email flow is wired up).
defined('USERNAME_EMAIL') || define('USERNAME_EMAIL', env('SMTP_USER', ''));
defined('PASSWORD_EMAIL') || define('PASSWORD_EMAIL', env('SMTP_PASSWORD', ''));

// --- AI Camera ---
defined('AI_CAMERA_ENABLED') || define('AI_CAMERA_ENABLED', (bool) env('AI_CAMERA_ENABLED', false));
defined('ROBOFLOW_KEY')      || define('ROBOFLOW_KEY',      env('ROBOFLOW_PUBLISHABLE_KEY', ''));
defined('ROBOFLOW_MODEL')    || define('ROBOFLOW_MODEL',    env('ROBOFLOW_MODEL', 'hard-hat-workers/12'));
