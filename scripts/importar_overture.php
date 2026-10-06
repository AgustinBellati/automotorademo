<?php
declare(strict_types=1);

// Carga data/uy_places.jsonl (generado por scripts/overture.mjs) en la tabla leadfinder_places.
// Uso: php scripts/importar_overture.php
if (PHP_SAPI !== 'cli') {
    exit("Solo por consola\n");
}

require __DIR__ . '/../../agusdevpro/api/db.php'; // define $conn
require_once __DIR__ . '/../api/leads/places.php'; // places_estado_web()
require_once __DIR__ . '/../api/leads/schema.php';

$archivo = __DIR__ . '/../data/uy_places.jsonl';
if (!is_file($archivo)) {
    exit("No existe $archivo. Corré primero: node scripts/overture.mjs\n");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
leads_crear_tablas($conn);

const COLS = ['place_id', 'nombre', 'categoria', 'jerarquia', 'direccion', 'localidad', 'telefono', 'web',
    'facebook', 'email', 'estado_web', 'confianza', 'lat', 'lon'];
const LOTE = 500;

function primero(array $lista, int $max): string
{
    return mb_substr((string) ($lista[0] ?? ''), 0, $max);
}

/** La web "propia" si hay alguna; si no, la primera (puede ser una red social) */
function web_principal(array $webs): string
{
    foreach ($webs as $w) {
        if (places_estado_web($w) === 'con_web') {
            return $w;
        }
    }
    return $webs[0] ?? '';
}

function estado_web(array $webs): string
{
    $estado = 'sin_web';
    foreach ($webs as $w) {
        $e = places_estado_web($w);
        if ($e === 'con_web') {
            return 'con_web';
        }
        if ($e === 'solo_redes') {
            $estado = 'solo_redes';
        }
    }
    return $estado;
}

function insertar_lote(mysqli $conn, array $filas): void
{
    if (!$filas) {
        return;
    }
    $fila = '(' . implode(',', array_fill(0, count(COLS), '?')) . ')';
    $updates = implode(',', array_map(static fn($c) => "$c = VALUES($c)", array_slice(COLS, 1)));
    $sql = 'INSERT INTO leadfinder_places (' . implode(',', COLS) . ') VALUES '
        . implode(',', array_fill(0, count($filas), $fila))
        . " ON DUPLICATE KEY UPDATE $updates";
    $stmt = $conn->prepare($sql);
    $valores = array_merge(...$filas);
    $stmt->bind_param(str_repeat('s', count($valores)), ...$valores);
    $stmt->execute();
}

$inicio = microtime(true);
$ids = [];
$lote = [];
$total = 0;
$conteo = ['sin_web' => 0, 'solo_redes' => 0, 'con_web' => 0];

$fh = fopen($archivo, 'r');
while (($linea = fgets($fh)) !== false) {
    $p = json_decode($linea, true);
    if (!is_array($p) || empty($p['place_id']) || empty($p['nombre'])) {
        continue;
    }
    $webs = $p['webs'] ?? [];
    $estado = estado_web($webs);
    $conteo[$estado]++;
    $ids[] = $p['place_id'];
    $lote[] = [
        $p['place_id'],
        mb_substr($p['nombre'], 0, 255),
        mb_substr($p['categoria'] ?? '', 0, 120),
        // con espacios en los bordes para poder buscar "% categoria %"
        mb_substr(' ' . ($p['jerarquia'] ?? '') . ' ', 0, 400),
        mb_substr($p['direccion'] ?? '', 0, 255),
        mb_substr($p['localidad'] ?? '', 0, 120),
        primero($p['telefonos'] ?? [], 60),
        mb_substr(web_principal($webs), 0, 500),
        primero($p['redes'] ?? [], 500),
        primero($p['emails'] ?? [], 255),
        $estado,
        (string) ($p['confianza'] ?? 0),
        isset($p['lat']) ? (string) $p['lat'] : null,
        isset($p['lon']) ? (string) $p['lon'] : null,
    ];
    if (count($lote) >= LOTE) {
        insertar_lote($conn, $lote);
        $total += count($lote);
        $lote = [];
        echo "\r$total importados…";
    }
}
fclose($fh);
insertar_lote($conn, $lote);
$total += count($lote);

// Borrar los que ya no están en Overture (cerraron o se eliminaron)
$conn->query('CREATE TEMPORARY TABLE tmp_ids (place_id VARCHAR(64) PRIMARY KEY) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
foreach (array_chunk($ids, 1000) as $chunk) {
    $conn->query("INSERT INTO tmp_ids VALUES ('" . implode("'),('", array_map([$conn, 'real_escape_string'], $chunk)) . "')");
}
$conn->query('DELETE p FROM leadfinder_places p LEFT JOIN tmp_ids t USING (place_id) WHERE t.place_id IS NULL');
$borrados = $conn->affected_rows;

printf(
    "\r%d negocios importados en %.0fs (%d sin web, %d solo redes, %d con web). %d viejos borrados.\n",
    $total, microtime(true) - $inicio, $conteo['sin_web'], $conteo['solo_redes'], $conteo['con_web'], $borrados
);
