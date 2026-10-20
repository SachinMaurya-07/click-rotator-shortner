<?php
require __DIR__ . '/config.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = trim($path, '/');

if ($path === '') {
    http_response_code(404);
    echo 'Short link not found.';
    exit;
}

$parts = explode('/', $path);
$slug = $parts[0];
if (!valid_slug($slug)) {
    http_response_code(404);
    echo 'Short link not found.';
    exit;
}

try {
    [$data, $target] = update_data(function (&$data) use ($slug) {
        foreach ($data['links'] as &$link) {
            if (($link['slug'] ?? '') !== $slug) continue;
            if (empty($link['enabled'])) throw new RuntimeException('Link disabled.');
            $targets = $link['targets'] ?? [];
            if (!is_array($targets) || count($targets) === 0) throw new RuntimeException('No target configured.');

            $idx = max(0, min((int)($link['current_index'] ?? 0), count($targets) - 1));
            $limit = max(1, (int)($link['click_limit'] ?? 300));
            $link['clicks_in_current_target'] = (int)($link['clicks_in_current_target'] ?? 0) + 1;
            $link['total_clicks'] = (int)($link['total_clicks'] ?? 0) + 1;

            $selected = $targets[$idx]['url'];
            // Rotate only after the current click reaches the configured limit.
            if ($link['clicks_in_current_target'] >= $limit && count($targets) > 1) {
                $link['current_index'] = ($idx + 1) % count($targets);
                $link['clicks_in_current_target'] = 0;
            } else {
                $link['current_index'] = $idx;
            }
            $link['last_click_at'] = gmdate('c');
            return $selected;
        }
        throw new RuntimeException('Short link not found.');
    });

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    http_response_code($msg === 'Short link not found.' ? 404 : 503);
    echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    exit;
}
