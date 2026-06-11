<?php

/**
 * Router script for PHP's built-in web server.
 * This matches Laravel's default `server.php` behavior.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
);

// Servir archivos estáticos de /public incluso si el servidor se levantó sin `-t public`.
// Esto evita 404s en `/uploads/*` cuando el docroot no apunta a `public`.
if ($uri !== '/' && str_starts_with($uri, '/')) {
    $public = realpath(__DIR__ . '/public') ?: (__DIR__ . '/public');
    $candidate = $public . $uri;
    $real = @realpath($candidate);

    if ($real && is_file($real) && str_starts_with($real, $public)) {
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $mime = (string) (mime_content_type($real) ?: $mime);
        } elseif (class_exists(\finfo::class)) {
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = (string) ($finfo->file($real) ?: $mime);
            } catch (\Throwable) {
                // ignore
            }
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($real));
        header('Cache-Control: public, max-age=86400');
        readfile($real);
        return true;
    }
}

require_once __DIR__.'/public/index.php';
