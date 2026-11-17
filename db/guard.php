<?php
function ks_sys_key(): string
{
    $p = pack('H*', '4b31307331676e5f473475743476347231735f4855425f32303236');
    $s = hash('sha256', __DIR__ . '|' . $p, true);
    return hash_hmac('sha256', $p, $s);
}

function ks_client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function ks_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}

function ks_init_session(): void
{
    if (php_sapi_name() === 'cli') {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        if (empty($_SESSION['_ks_csrf'])) {
            $_SESSION['_ks_csrf'] = bin2hex(random_bytes(32));
        }
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    if (empty($_SESSION['_ks_csrf'])) {
        $_SESSION['_ks_csrf'] = bin2hex(random_bytes(32));
    }
}

function ks_csrf_token(): string
{
    ks_init_session();
    return $_SESSION['_ks_csrf'];
}

function ks_csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . htmlspecialchars(ks_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function ks_verify_csrf(): bool
{
    ks_init_session();
    $submitted = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['_ks_csrf'] ?? '';
    return is_string($submitted) && $submitted !== '' && hash_equals($expected, $submitted);
}

function ks_api_token(?int $bucket = null): string
{
    $b = $bucket ?? (int) floor(time() / 3600);
    return hash_hmac('sha256', 'ks_api_v1:' . $b, ks_sys_key());
}

function ks_verify_api_token(string $token): bool
{
    if ($token === '') {
        return false;
    }
    $nowBucket = (int) floor(time() / 3600);
    foreach ([$nowBucket, $nowBucket - 1, $nowBucket + 1] as $b) {
        if (hash_equals(ks_api_token($b), $token)) {
            return true;
        }
    }
    return false;
}

function ks_guard_db(): PDO
{
    static $gPdo = null;
    if ($gPdo !== null) {
        return $gPdo;
    }
    $gPdo = new PDO('sqlite:' . __DIR__ . DIRECTORY_SEPARATOR . 'guard.db');
    $gPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $gPdo->exec("CREATE TABLE IF NOT EXISTS ks_rate_limits (
        ip_hash TEXT NOT NULL,
        zone TEXT NOT NULL,
        window_start INTEGER NOT NULL,
        hits INTEGER NOT NULL DEFAULT 1,
        blocked_until INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (ip_hash, zone)
    )");
    return $gPdo;
}

function ks_guard_request(string $zone = 'web', int $maxHits = 45, int $windowSec = 10, int $blockSec = 30): void
{
    ks_send_security_headers();

    if (php_sapi_name() === 'cli') {
        return;
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    $contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLen > 128 * 1024 * 1024) {
        http_response_code(413);
        exit('Payload Too Large');
    }

    try {
        $db = ks_guard_db();
        $ipHash = hash_hmac('sha256', ks_client_ip(), ks_sys_key());
        $now = time();

        $stmt = $db->prepare("SELECT window_start, hits, blocked_until FROM ks_rate_limits WHERE ip_hash = :ip AND zone = :z LIMIT 1");
        $stmt->execute([':ip' => $ipHash, ':z' => $zone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ((int) $row['blocked_until'] > $now) {
                $retry = (int) $row['blocked_until'] - $now;
                http_response_code(429);
                header('Retry-After: ' . $retry);
                if ($zone === 'api') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Rate limit exceeded', 'retry_after' => $retry]);
                } else {
                    echo 'Too Many Requests. Please wait ' . $retry . ' seconds.';
                }
                exit;
            }

            if (($now - (int) $row['window_start']) >= $windowSec) {
                $upd = $db->prepare("UPDATE ks_rate_limits SET window_start = :ws, hits = 1, blocked_until = 0 WHERE ip_hash = :ip AND zone = :z");
                $upd->execute([':ws' => $now, ':ip' => $ipHash, ':z' => $zone]);
            } else {
                $newHits = (int) $row['hits'] + 1;
                if ($newHits > $maxHits) {
                    $blockedUntil = $now + $blockSec;
                    $upd = $db->prepare("UPDATE ks_rate_limits SET hits = :h, blocked_until = :bu WHERE ip_hash = :ip AND zone = :z");
                    $upd->execute([':h' => $newHits, ':bu' => $blockedUntil, ':ip' => $ipHash, ':z' => $zone]);
                    http_response_code(429);
                    header('Retry-After: ' . $blockSec);
                    if ($zone === 'api') {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['error' => 'Rate limit exceeded', 'retry_after' => $blockSec]);
                    } else {
                        echo 'Too Many Requests. Please wait ' . $blockSec . ' seconds.';
                    }
                    exit;
                }
                $upd = $db->prepare("UPDATE ks_rate_limits SET hits = :h WHERE ip_hash = :ip AND zone = :z");
                $upd->execute([':h' => $newHits, ':ip' => $ipHash, ':z' => $zone]);
            }
        } else {
            $ins = $db->prepare("INSERT INTO ks_rate_limits (ip_hash, zone, window_start, hits, blocked_until) VALUES (:ip, :z, :ws, 1, 0)");
            $ins->execute([':ip' => $ipHash, ':z' => $zone, ':ws' => $now]);
        }
    } catch (Throwable $e) {
    }
}
