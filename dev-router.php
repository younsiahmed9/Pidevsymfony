<?php

declare(strict_types=1);

$publicDir = __DIR__ . '/public';
$publicDirReal = realpath($publicDir);
$uriPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$requested = $publicDirReal !== false ? realpath($publicDirReal . $uriPath) : false;

if (
    $publicDirReal !== false
    && $requested !== false
    && str_starts_with($requested, $publicDirReal)
    && is_file($requested)
) {
    // Let PHP's built-in server handle static assets with proper MIME types.
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';

require $_SERVER['SCRIPT_FILENAME'];
