<?php
// Respuesta 404 con negociación de contenido: Markdown para agentes de IA
// (Accept: text/markdown) y HTML para personas. Siempre HTTP 404.
http_response_code(404);
header('Vary: Accept');
header('X-Robots-Tag: noindex');
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if (stripos($accept, 'text/markdown') !== false && is_file(__DIR__ . '/md/404.md')) {
    header('Content-Type: text/markdown; charset=UTF-8');
    header('Cache-Control: no-store, private');
    readfile(__DIR__ . '/md/404.md');
} else {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/404.html');
}
