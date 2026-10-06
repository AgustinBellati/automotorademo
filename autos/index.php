<?php
declare(strict_types=1);

// Catálogo público de una automotora: /autos/<slug>/ y /autos/<slug>/<ref>
// (sin mod_rewrite: /autos/?c=<slug>&r=<ref>). PHP arma el HTML con meta OG y los datos; catalogo.js renderiza.
require_once __DIR__ . '/../api/autos/publico/_datos.php';

$slug = (string) ($_GET['c'] ?? '');
$ref = (string) ($_GET['r'] ?? '');
$datos = autos_pub_slug_valido($slug) ? autos_catalogo(autos_pub_db(), $slug) : null;

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function precio_txt(array $v): string
{
    return ($v['moneda'] === 'UYU' ? '$ ' : 'U$S ') . number_format($v['precio'], 0, ',', '.');
}

if (!$datos) {
    http_response_code(404);
    ?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Catálogo no disponible</title><link rel="stylesheet" href="/autos/catalogo.css"></head>
<body><main class="wrap"><h1>Este catálogo no está disponible</h1><p class="desc">Revisá el link o consultá directamente con la automotora.</p></main></body></html>
<?php
    exit;
}

$cuenta = $datos['cuenta'];
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$origen = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'agusdevpro.com');
$bonitas = !str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), 'c=');
$base = $bonitas ? '/autos/' . $cuenta['slug'] . '/' : '/autos/?c=' . $cuenta['slug'];

$auto = null;
foreach ($datos['vehiculos'] as $v) {
    if ($v['ref'] === $ref) {
        $auto = $v;
        break;
    }
}

$titulo = $cuenta['nombre'] . ' · Autos usados';
$descripcion = count($datos['vehiculos']) . ' autos en stock' . ($cuenta['direccion'] ? ' · ' . $cuenta['direccion'] : '');
$imagen = $datos['vehiculos'][0]['fotos'][0]['url_1600'] ?? '';
$url = $origen . $base;
if ($auto) {
    $titulo = $auto['marca'] . ' ' . $auto['modelo'] . ' ' . $auto['anio'] . ' · ' . precio_txt($auto);
    $descripcion = number_format($auto['km'], 0, ',', '.') . ' km' . ($auto['caja'] ? ' · ' . $auto['caja'] : '') . ' · ' . $cuenta['nombre'];
    $imagen = $auto['fotos'][0]['url_1600'] ?? $imagen;
    $url = $origen . ($bonitas ? $base . $auto['ref'] : $base . '&r=' . $auto['ref']);
}
$config = ['base' => $base, 'bonitas' => $bonitas, 'origen' => $origen, 'evento' => '/api/autos/publico/evento.php'];
$json = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($titulo) ?></title>
<meta name="description" content="<?= h($descripcion) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($titulo) ?>">
<meta property="og:description" content="<?= h($descripcion) ?>">
<meta property="og:url" content="<?= h($url) ?>">
<?php if ($imagen): ?><meta property="og:image" content="<?= h($origen . $imagen) ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/autos/catalogo.css">
<style>:root { --brand: <?= h($cuenta['color']) ?> }</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-in">
    <a class="brand" href="<?= h($base) ?>" style="color:inherit;text-decoration:none">
      <div class="logo"><?= h($cuenta['iniciales'] ?: mb_substr($cuenta['nombre'], 0, 2)) ?></div>
      <div>
        <div class="brand-name"><?= h($cuenta['nombre']) ?></div>
        <?php if ($cuenta['direccion']): ?><div class="brand-sub"><?= h($cuenta['direccion']) ?></div><?php endif; ?>
      </div>
    </a>
  </div>
</header>
<main class="wrap" id="app"></main>
<div class="toast" id="toast" hidden></div>
<script type="application/json" id="datos"><?= json_encode($datos, $json) ?></script>
<script type="application/json" id="config"><?= json_encode($config, $json) ?></script>
<script src="/autos/catalogo.js"></script>
</body>
</html>
