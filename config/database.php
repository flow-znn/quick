<?php
declare(strict_types=1);

function database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = trim((string) getenv('DB_HOST'));
    $name = trim((string) getenv('DB_NAME'));
    $user = trim((string) getenv('DB_USER'));
    $password = (string) getenv('DB_PASSWORD');
    $port = trim((string) (getenv('DB_PORT') ?: '3306'));

    if ($host === '' || $name === '' || $user === '') {
        throw new RuntimeException('Configure DB_HOST, DB_NAME e DB_USER no ambiente do container para conectar ao MySQL.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $connection = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
