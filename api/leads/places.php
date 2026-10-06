<?php
declare(strict_types=1);

require_once __DIR__ . '/mb.php';

// Webs que no cuentan como "página propia"
const PLACES_REDES = [
    'facebook.com', 'fb.com', 'instagram.com', 'linktr.ee', 'wa.me', 'whatsapp.com', 'tiktok.com',
    'twitter.com', 'x.com', 'linkedin.com', 'youtube.com', 'sites.google.com', 'business.site', 'g.page',
];

/** secret.php una sola vez: string (formato viejo) o array. */
function leads_secret(): mixed
{
    static $listo = false;
    static $valor = null;
    if (!$listo) {
        $listo = true;
        $archivo = __DIR__ . '/secret.php';
        if (is_file($archivo)) {
            $valor = require $archivo;
        }
    }
    return $valor;
}

function places_api_key(): string
{
    $key = getenv('GOOGLE_PLACES_API_KEY') ?: '';
    if ($key === '') {
        $secret = leads_secret();
        if (is_string($secret)) {
            $key = $secret;
        } elseif (is_array($secret)) {
            $key = (string) ($secret['places_key'] ?? '');
        }
    }
    return trim($key);
}

function config(string $clave, mixed $default = null): mixed
{
    $secret = leads_secret();
    if (!is_array($secret) || !array_key_exists($clave, $secret)) {
        return $default;
    }
    return $secret[$clave];
}

function places_estado_web(?string $uri): string
{
    if (!$uri) {
        return 'sin_web';
    }
    $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
    if ($host === '') {
        return 'sin_web';
    }
    $host = preg_replace('/^www\./', '', $host);
    foreach (PLACES_REDES as $d) {
        if ($host === $d || str_ends_with($host, '.' . $d)) {
            return 'solo_redes';
        }
    }
    return 'con_web';
}
