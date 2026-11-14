<?php
/**
 * Local Development Router for PHP Built-in Web Server (`php -S 127.0.0.1:8080 router.php`).
 * Mirrors Hostinger Apache .htaccess routing for WordPress Admin, REST API, and static uploads.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$docRoot = __DIR__;

// Normalize Apache/HTTP Authorization header for REST Application Passwords
if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$fullPath = realpath($docRoot . $uri);

// Mirror Hostinger .htaccess blocks for sensitive/hidden files in local dev
if (preg_match('/(^|\/)\.(?!well-known)|(\.sql|\.log|\.bak|\.old|\.local|readme\.html|license\.txt|router\.php)$/i', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Serve existing static files directly (uploads, CSS, JS, images, PDFs)
if ($uri !== '/' && $fullPath && str_starts_with($fullPath, realpath($docRoot)) && is_file($fullPath)) {
    // Allow PHP files inside wp-admin / wp-includes / wp-login / wp-cron to execute cleanly
    if (str_ends_with(strtolower($fullPath), '.php')) {
        $_SERVER['SCRIPT_FILENAME'] = $fullPath;
        chdir(dirname($fullPath));
        require $fullPath;
        return true;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'gif'   => 'image/gif',
        'pdf'   => 'application/pdf',
        'mp4'   => 'video/mp4',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');

    $size = filesize($fullPath);
    $start = 0;
    $end = $size > 0 ? $size - 1 : 0;
    $isRangeRequest = false;

    header('Accept-Ranges: bytes');

    if ($size > 0 && !empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
        $reqStart = $matches[1] !== '' ? (int) $matches[1] : null;
        $reqEnd   = $matches[2] !== '' ? (int) $matches[2] : null;

        if ($reqStart === null && $reqEnd !== null) {
            $start = max(0, $size - $reqEnd);
            $end = $size - 1;
        } elseif ($reqStart !== null) {
            $start = $reqStart;
            $end = ($reqEnd !== null && $reqEnd < $size) ? $reqEnd : $size - 1;
        }

        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */{$size}");
            return true;
        }

        $isRangeRequest = true;
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    }

    $length = $size > 0 ? ($end - $start + 1) : 0;
    header("Content-Length: {$length}");

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        return true;
    }

    $fp = fopen($fullPath, 'rb');
    if ($fp === false) {
        http_response_code(500);
        return true;
    }
    if ($isRangeRequest && $start > 0) {
        fseek($fp, $start);
    }
    $remaining = $length;
    while ($remaining > 0 && !feof($fp) && connection_status() === CONNECTION_NORMAL) {
        $chunkSize = $remaining > 65536 ? 65536 : $remaining;
        $buffer = fread($fp, $chunkSize);
        if ($buffer === false || $buffer === '') {
            break;
        }
        echo $buffer;
        flush();
        $remaining -= strlen($buffer);
    }
    fclose($fp);
    return true;
}

// Handle /wp-admin or directory trailing index.php
if ($fullPath && is_dir($fullPath) && is_file($fullPath . '/index.php')) {
    chdir($fullPath);
    require $fullPath . '/index.php';
    return true;
}

// Route everything else (including /wp-json/*) through WordPress front controller
chdir($docRoot);
require $docRoot . '/index.php';
