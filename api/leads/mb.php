<?php
declare(strict_types=1);

// Respaldo por si PHP no tiene la extensión mbstring (pasa en algunas instalaciones de Windows)

if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $length = null): string
    {
        $r = iconv_substr($s, $start, $length ?? iconv_strlen($s, 'UTF-8'), 'UTF-8');
        return $r === false ? '' : $r;
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s): string
    {
        return strtolower(strtr($s, ['Á' => 'á', 'É' => 'é', 'Í' => 'í', 'Ó' => 'ó', 'Ú' => 'ú', 'Ü' => 'ü', 'Ñ' => 'ñ']));
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s): string
    {
        return strtoupper(strtr($s, ['á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó', 'ú' => 'Ú', 'ü' => 'Ü', 'ñ' => 'Ñ']));
    }
}
