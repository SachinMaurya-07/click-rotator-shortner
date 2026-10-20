<?php
// Basic configuration for the click-rotation shortener.
// Keep this file outside the public web root when possible.

const DATA_FILE = __DIR__ . '/data/links.json';
const ADMIN_PASSWORD = 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD'; // Change this before deployment
const SESSION_NAME = 'rotator_admin';
const MAX_URL_LENGTH = 2048;

session_name(SESSION_NAME);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function ensure_data_file(): void {
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!file_exists(DATA_FILE)) {
        $initial = [
            'settings' => [
                'version' => 1
            ],
            'links' => []
        ];
        file_put_contents(DATA_FILE, json_encode($initial, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

function load_data(): array {
    ensure_data_file();
    $fp = fopen(DATA_FILE, 'c+');
    if (!$fp) {
        throw new RuntimeException('Unable to open data file.');
    }
    if (!flock($fp, LOCK_SH)) {
        fclose($fp);
        throw new RuntimeException('Unable to lock data file.');
    }
    $contents = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($contents ?: '', true);
    if (!is_array($data)) {
        $data = ['settings' => ['version' => 1], 'links' => []];
    }
    $data['links'] = is_array($data['links'] ?? null) ? $data['links'] : [];
    return $data;
}

function update_data(callable $mutator): array {
    ensure_data_file();
    $fp = fopen(DATA_FILE, 'c+');
    if (!$fp) {
        throw new RuntimeException('Unable to open data file.');
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new RuntimeException('Unable to lock data file.');
    }

    rewind($fp);
    $contents = stream_get_contents($fp);
    $data = json_decode($contents ?: '', true);
    if (!is_array($data)) {
        $data = ['settings' => ['version' => 1], 'links' => []];
    }
    $data['links'] = is_array($data['links'] ?? null) ? $data['links'] : [];

    $result = $mutator($data);

    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return [$data, $result];
}

function json_response(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function require_admin(): void {
    if (empty($_SESSION['admin_ok'])) {
        json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

function valid_slug(string $slug): bool {
    return preg_match('/^[A-Za-z0-9_-]{1,80}$/', $slug) === 1;
}

function valid_url(string $url): bool {
    if (strlen($url) > MAX_URL_LENGTH) return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function random_id(int $length = 7): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}
