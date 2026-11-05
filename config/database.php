<?php
/**
 * Koneksi database (PDO / MySQL / MariaDB).
 * Ubah nilai default di bawah sesuai XAMPP/hosting Anda, atau set environment
 * variable WS_DB_HOST, WS_DB_NAME, WS_DB_USER, WS_DB_PASS.
 */
function db_config(): array
{
    return [
        'host'    => getenv('WS_DB_HOST') ?: 'localhost',
        'name'    => getenv('WS_DB_NAME') ?: 'db_walisongo_pc',
        'user'    => getenv('WS_DB_USER') ?: 'root',
        'pass'    => getenv('WS_DB_PASS') !== false ? getenv('WS_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = db_config();
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
