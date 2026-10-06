<?php
declare(strict_types=1);

// Datos públicos del catálogo (sin login). Lo usan autos/index.php y evento.php.
// Nunca devuelve user_id, lead_id ni vehículos vendidos.
require_once __DIR__ . '/../../db.php'; // define $conn
require_once __DIR__ . '/../schema.php';

function autos_pub_db(): mysqli
{
    global $conn;
    static $ready = false;
    if (!$ready) {
        autos_crear_tablas($conn);
        $ready = true;
    }
    return $conn;
}

function autos_pub_slug_valido(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9-]{1,80}$/', $slug);
}

/** Cuenta visible (demo o activa) por slug, con user_id para uso interno. */
function autos_pub_cuenta(mysqli $db, string $slug): ?array
{
    if (!autos_pub_slug_valido($slug)) {
        return null;
    }
    $stmt = $db->prepare(
        "SELECT id, user_id, slug, nombre, iniciales, direccion, whatsapp, color, tasa_anual, plazos, entrega_min_pct
         FROM autos_cuentas WHERE slug = ? AND estado IN ('demo','activa') LIMIT 1"
    );
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function autos_pub_plazos(string $csv): array
{
    $out = [];
    foreach (explode(',', $csv) as $p) {
        $n = (int) trim($p);
        if ($n >= 6 && $n <= 84) {
            $out[] = $n;
        }
    }
    return $out ?: [12, 24, 36, 48];
}

/** Número para wa.me: sólo dígitos y con 598 si es un celular uruguayo escrito local (09x xxx xxx). */
function autos_pub_whatsapp(string $tel): string
{
    $d = preg_replace('/\D+/', '', $tel) ?? '';
    if (preg_match('/^0?9\d{7}$/', $d)) {
        return '598' . ltrim($d, '0');
    }
    return $d;
}

/** Catálogo completo para la página pública, o null si la cuenta no existe o está pausada. */
function autos_catalogo(mysqli $db, string $slug): ?array
{
    $cuenta = autos_pub_cuenta($db, $slug);
    if (!$cuenta) {
        return null;
    }
    $cid = (int) $cuenta['id'];

    $stmt = $db->prepare(
        "SELECT id, ref, marca, modelo, `version`, anio, km, precio, moneda, combustible, caja, tipo, color,
                descripcion, estado, destacado
         FROM autos_vehiculos
         WHERE cuenta_id = ? AND estado IN ('disponible','reservado')
         ORDER BY destacado DESC, ingreso_at DESC, id DESC"
    );
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $vehiculos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $fotos = [];
    if ($vehiculos) {
        $ids = array_map(static fn ($v) => (int) $v['id'], $vehiculos);
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT vehiculo_id, archivo, ext FROM autos_fotos WHERE vehiculo_id IN ($marcas) ORDER BY orden ASC, id ASC"
        );
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $f) {
            $base = '/autos-media/' . $cid . '/' . (int) $f['vehiculo_id'] . '/' . $f['archivo'] . '_';
            $fotos[(int) $f['vehiculo_id']][] = ['url_800' => $base . '800.' . $f['ext'], 'url_1600' => $base . '1600.' . $f['ext']];
        }
        $stmt->close();
    }

    return [
        'cuenta' => [
            'slug' => (string) $cuenta['slug'],
            'nombre' => (string) $cuenta['nombre'],
            'iniciales' => (string) $cuenta['iniciales'],
            'direccion' => (string) $cuenta['direccion'],
            'whatsapp' => autos_pub_whatsapp((string) $cuenta['whatsapp']),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $cuenta['color']) ? (string) $cuenta['color'] : '#1d4ed8',
            'tasa_anual' => (float) $cuenta['tasa_anual'],
            'plazos' => autos_pub_plazos((string) $cuenta['plazos']),
            'entrega_min_pct' => (int) $cuenta['entrega_min_pct'],
        ],
        'vehiculos' => array_map(static fn ($v) => [
            'ref' => (string) $v['ref'],
            'marca' => (string) $v['marca'],
            'modelo' => (string) $v['modelo'],
            'version' => (string) $v['version'],
            'anio' => (int) $v['anio'],
            'km' => (int) $v['km'],
            'precio' => (int) $v['precio'],
            'moneda' => (string) $v['moneda'],
            'combustible' => (string) $v['combustible'],
            'caja' => (string) $v['caja'],
            'tipo' => (string) $v['tipo'],
            'color' => (string) $v['color'],
            'descripcion' => (string) ($v['descripcion'] ?? ''),
            'estado' => (string) $v['estado'],
            'destacado' => (int) $v['destacado'] === 1,
            'fotos' => $fotos[(int) $v['id']] ?? [],
        ], $vehiculos),
    ];
}
