
<?php
session_start();

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

// Check existing session (must be before require_admin)
if ($action === 'check') {
    json_response([
        'ok' => true,
        'logged_in' => !empty($_SESSION['admin_ok'])
    ]);
}

// Admin login
if ($action === 'login') {
    if ($method !== 'POST') {
        json_response([
            'ok' => false,
            'error' => 'Method not allowed'
        ], 405);
    }

    $password = (string)($_POST['password'] ?? '');

    if (hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;

        json_response(['ok' => true]);
    }

    json_response([
        'ok' => false,
        'error' => 'Invalid password'
    ], 403);
}

// Admin logout
if ($action === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();

    json_response(['ok' => true]);
}

// Protect every remaining action
require_admin();

try {
    if ($action === 'list') {
        $data = load_data();

        json_response([
            'ok' => true,
            'links' => array_values($data['links'])
        ]);
    }

    if ($action === 'create') {
        if ($method !== 'POST') {
            json_response([
                'ok' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $slug = trim((string)($_POST['slug'] ?? ''));
        $limit = max(1, min(
            1000000000,
            (int)($_POST['click_limit'] ?? 300)
        ));
        $name = trim((string)($_POST['name'] ?? $slug));

        if (!valid_slug($slug)) {
            json_response([
                'ok' => false,
                'error' => 'Invalid slug. Use letters, numbers, _ or -.'
            ], 422);
        }

        [$data] = update_data(function (&$data) use ($slug, $limit, $name) {
            foreach ($data['links'] as $existing) {
                if (($existing['slug'] ?? '') === $slug) {
                    throw new RuntimeException('That slug already exists.');
                }
            }

            $data['links'][] = [
                'id' => random_id(10),
                'slug' => $slug,
                'name' => $name !== '' ? $name : $slug,
                'click_limit' => $limit,
                'clicks_in_current_target' => 0,
                'total_clicks' => 0,
                'current_index' => 0,
                'enabled' => true,
                'created_at' => gmdate('c'),
                'targets' => []
            ];
        });

        json_response([
            'ok' => true,
            'links' => $data['links']
        ]);
    }

    if ($action === 'save') {
        if ($method !== 'POST') {
            json_response([
                'ok' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $slug = trim((string)($_POST['slug'] ?? ''));

        if (!valid_slug($slug)) {
            json_response([
                'ok' => false,
                'error' => 'Invalid slug'
            ], 422);
        }

        $name = trim((string)($_POST['name'] ?? $slug));
        $limit = max(1, min(
            1000000000,
            (int)($_POST['click_limit'] ?? 300)
        ));
        $enabled = (int)($_POST['enabled'] ?? 1) === 1;
        $targetsRaw = trim((string)($_POST['targets'] ?? ''));
        $lines = preg_split('/\R/', $targetsRaw) ?: [];
        $targets = [];

        foreach ($lines as $line) {
            $url = trim($line);

            if ($url === '') {
                continue;
            }

            if (!valid_url($url)) {
                json_response([
                    'ok' => false,
                    'error' => "Invalid target URL: {$url}"
                ], 422);
            }

            $targets[] = ['url' => $url];
        }

        if (count($targets) < 1) {
            json_response([
                'ok' => false,
                'error' => 'Add at least one target URL.'
            ], 422);
        }

        [$data] = update_data(
            function (&$data) use ($slug, $name, $limit, $enabled, $targets) {
                foreach ($data['links'] as &$link) {
                    if (($link['slug'] ?? '') === $slug) {
                        $link['name'] = $name !== '' ? $name : $slug;
                        $link['click_limit'] = $limit;
                        $link['enabled'] = $enabled;
                        $link['targets'] = $targets;

                        if (($link['current_index'] ?? 0) >= count($targets)) {
                            $link['current_index'] = 0;
                            $link['clicks_in_current_target'] = 0;
                        }

                        return;
                    }
                }

                throw new RuntimeException('Link not found.');
            }
        );

        json_response([
            'ok' => true,
            'links' => $data['links']
        ]);
    }

    if ($action === 'delete') {
        if ($method !== 'POST') {
            json_response([
                'ok' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $slug = trim((string)($_POST['slug'] ?? ''));

        [$data] = update_data(function (&$data) use ($slug) {
            $data['links'] = array_values(array_filter(
                $data['links'],
                fn($l) => ($l['slug'] ?? '') !== $slug
            ));
        });

        json_response([
            'ok' => true,
            'links' => $data['links']
        ]);
    }

    if ($action === 'reset') {
        if ($method !== 'POST') {
            json_response([
                'ok' => false,
                'error' => 'Method not allowed'
            ], 405);
        }

        $slug = trim((string)($_POST['slug'] ?? ''));

        [$data] = update_data(function (&$data) use ($slug) {
            foreach ($data['links'] as &$link) {
                if (($link['slug'] ?? '') === $slug) {
                    $link['clicks_in_current_target'] = 0;
                    $link['total_clicks'] = 0;
                    $link['current_index'] = 0;
                    return;
                }
            }

            throw new RuntimeException('Link not found.');
        });

        json_response([
            'ok' => true,
            'links' => $data['links']
        ]);
    }

    json_response([
        'ok' => false,
        'error' => 'Unknown action'
    ], 404);

} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage()
    ], 500);
}
