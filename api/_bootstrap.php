<?php
// Fungsi bersama untuk semua endpoint JSON.
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_in(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function require_method(string $m): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $m) {
        json_out(['error' => "Gunakan method $m"], 405);
    }
}

function run_api(callable $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        json_out(['error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        error_log('[walisongo] ' . $e->getMessage());
        json_out(['error' => 'Terjadi kesalahan server. Pastikan database sudah diimpor dan config/database.php benar.'], 500);
    }
}
