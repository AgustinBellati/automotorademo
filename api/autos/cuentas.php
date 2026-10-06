<?php
declare(strict_types=1);

// GET → cuentas del usuario, con conteo de vehículos por estado
// POST { action:'crear', nombre, whatsapp, direccion?, lead_id? }
//    | { action:'editar', id, nombre?, direccion?, whatsapp?, color?, tasa_anual?, plazos?, entrega_min_pct?, estado? }
//    | { action:'borrar', id }
require_once __DIR__ . '/common.php';

$db = autos_db();
$uid = autos_user_id();

function autos_slug_base(string $nombre): string
{
    $s = mb_strtolower($nombre, 'UTF-8');
    $s = strtr($s, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ]);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'cuenta';
    }
    return substr($s, 0, 70);
}

function autos_slug_unico(mysqli $db, string $nombre): string
{
    $base = autos_slug_base($nombre);
    $stmt = autos_prepare($db, 'SELECT 1 FROM autos_cuentas WHERE slug = ? LIMIT 1');
    for ($n = 1; $n <= 200; $n++) {
        if ($n === 1) {
            $slug = $base;
        } else {
            $sufijo = '-' . $n;
            $slug = substr($base, 0, 80 - strlen($sufijo)) . $sufijo;
        }
        autos_bind_exec($stmt, [['s', $slug]]);
        if (!autos_fila($stmt)) {
            return $slug;
        }
    }
    autos_json(['ok' => false, 'error' => 'No se pudo generar el slug'], 500);
}

function autos_iniciales(string $nombre): string
{
    $limpio = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $nombre) ?? '';
    $palabras = preg_split('/\s+/u', trim($limpio), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letras = '';
    foreach (array_slice($palabras, 0, 2) as $palabra) {
        $letras .= mb_strtoupper(mb_substr($palabra, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return autos_text($letras, 4);
}

function autos_normalizar_whatsapp(string $crudo): ?string
{
    $crudo = trim($crudo);
    if ($crudo === '') {
        return null;
    }
    $conMas = str_starts_with($crudo, '+');
    $digitos = preg_replace('/\D+/', '', $crudo) ?? '';
    if (strlen($digitos) < 8 || strlen($digitos) > 15) {
        return null;
    }
    return autos_text(($conMas ? '+' : '') . $digitos, 20);
}

function autos_whatsapp(mixed $value, bool $permitirVacio): string
{
    $normalizado = autos_normalizar_whatsapp(autos_text($value, 40));
    if ($normalizado === null) {
        if ($permitirVacio && trim((string) $value) === '') {
            return '';
        }
        autos_json(['ok' => false, 'error' => trim((string) $value) === '' ? 'El WhatsApp es obligatorio' : 'WhatsApp inválido'], 400);
    }
    return $normalizado;
}

function autos_color_hex(mixed $value): string
{
    $color = strtolower(trim((string) $value));
    if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
        autos_json(['ok' => false, 'error' => 'El color tiene que ser #rrggbb'], 400);
    }
    return autos_text($color, 7);
}

function autos_tasa(mixed $value): string
{
    if (is_string($value)) {
        $value = str_replace(',', '.', trim($value));
    }
    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
        autos_json(['ok' => false, 'error' => 'La tasa anual tiene que estar entre 0 y 60'], 400);
    }
    $n = round((float) $value, 2);
    if ($n < 0 || $n > 60) {
        autos_json(['ok' => false, 'error' => 'La tasa anual tiene que estar entre 0 y 60'], 400);
    }
    return autos_text(number_format($n, 2, '.', ''), 10);
}

function autos_plazos(mixed $value): string
{
    if (is_string($value)) {
        $texto = autos_text($value, 60);
        $partes = $texto === '' ? [] : (preg_split('/\s*,\s*/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    } elseif (is_array($value)) {
        $partes = [];
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                autos_json(['ok' => false, 'error' => 'Plazos inválidos'], 400);
            }
            $partes[] = autos_text($item, 4);
        }
    } else {
        autos_json(['ok' => false, 'error' => 'Plazos inválidos'], 400);
    }
    if ($partes === []) {
        autos_json(['ok' => false, 'error' => 'Indicá al menos un plazo'], 400);
    }
    $nums = [];
    foreach ($partes as $parte) {
        $n = autos_entero($parte, 6, 84, 'Cada plazo tiene que estar entre 6 y 84 meses');
        if (!in_array($n, $nums, true)) {
            $nums[] = $n;
        }
    }
    $guardado = implode(',', $nums);
    if (strlen($guardado) > 60) {
        autos_json(['ok' => false, 'error' => 'Demasiados plazos'], 400);
    }
    return $guardado;
}

function autos_sql_cuentas(): string
{
    return "SELECT c.id, c.lead_id, c.slug, c.nombre, c.iniciales, c.direccion, c.whatsapp,
            c.color, c.tasa_anual, c.plazos, c.entrega_min_pct, c.estado, c.creado, c.actualizado,
            (SELECT COUNT(*) FROM autos_vehiculos v WHERE v.cuenta_id = c.id AND v.estado = 'disponible') AS n_disponible,
            (SELECT COUNT(*) FROM autos_vehiculos v WHERE v.cuenta_id = c.id AND v.estado = 'reservado') AS n_reservado,
            (SELECT COUNT(*) FROM autos_vehiculos v WHERE v.cuenta_id = c.id AND v.estado = 'vendido') AS n_vendido
        FROM autos_cuentas c";
}

function autos_cuenta_data(array $row): array
{
    $plazos = trim((string) $row['plazos']);
    return [
        'id' => (int) $row['id'],
        'lead_id' => $row['lead_id'] !== null ? (int) $row['lead_id'] : null,
        'slug' => (string) $row['slug'],
        'nombre' => (string) $row['nombre'],
        'iniciales' => (string) $row['iniciales'],
        'direccion' => (string) $row['direccion'],
        'whatsapp' => (string) $row['whatsapp'],
        'color' => (string) $row['color'],
        'tasa_anual' => (float) $row['tasa_anual'],
        'plazos' => $plazos === '' ? [] : array_map('intval', explode(',', $plazos)),
        'entrega_min_pct' => (int) $row['entrega_min_pct'],
        'estado' => (string) $row['estado'],
        'creado' => (string) $row['creado'],
        'actualizado' => (string) $row['actualizado'],
        'conteo' => [
            'disponible' => (int) ($row['n_disponible'] ?? 0),
            'reservado' => (int) ($row['n_reservado'] ?? 0),
            'vendido' => (int) ($row['n_vendido'] ?? 0),
        ],
    ];
}

function autos_cuenta_salida(mysqli $db, int $id, int $uid): array
{
    $stmt = autos_prepare($db, autos_sql_cuentas() . ' WHERE c.id = ? AND c.user_id = ?');
    autos_bind_exec($stmt, [['i', $id], ['i', $uid]]);
    $row = autos_fila($stmt);
    if (!$row) {
        autos_json(['ok' => false, 'error' => 'Cuenta no encontrada'], 404);
    }
    return autos_cuenta_data($row);
}

function autos_nombre_cuenta(mixed $value): string
{
    $nombre = autos_text($value, 120);
    if ($nombre === '' || !preg_match('/\p{L}|\p{N}/u', $nombre)) {
        autos_json(['ok' => false, 'error' => 'El nombre es obligatorio'], 400);
    }
    return $nombre;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $stmt = autos_prepare($db, autos_sql_cuentas() . ' WHERE c.user_id = ? ORDER BY c.creado DESC, c.id DESC');
    autos_bind_exec($stmt, [['i', $uid]]);
    $cuentas = [];
    foreach (autos_filas($stmt) as $row) {
        $cuentas[] = autos_cuenta_data($row);
    }
    autos_json(['ok' => true, 'data' => $cuentas]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    autos_json(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$input = autos_input();
$action = (string) ($input['action'] ?? '');

if ($action === 'crear') {
    $leadId = null;
    $lead = null;
    if (array_key_exists('lead_id', $input) && $input['lead_id'] !== null && $input['lead_id'] !== '') {
        $leadId = autos_entero($input['lead_id'], 1, 2147483647, 'Lead inválido');
        $stmt = autos_prepare(
            $db,
            'SELECT nombre, direccion, telefono, telefono_int FROM leadfinder_leads WHERE id = ? AND user_id = ?'
        );
        autos_bind_exec($stmt, [['i', $leadId], ['i', $uid]]);
        $lead = autos_fila($stmt);
        if (!$lead) {
            autos_json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
        }
    }

    $nombre = autos_text($input['nombre'] ?? '', 120);
    if ($nombre === '' && $lead) {
        $nombre = autos_text($lead['nombre'], 120);
    }
    $nombre = autos_nombre_cuenta($nombre);

    $direccion = autos_text($input['direccion'] ?? '', 200);
    if ($direccion === '' && $lead) {
        $direccion = autos_text($lead['direccion'], 200);
    }

    $whatsappCrudo = trim((string) ($input['whatsapp'] ?? ''));
    if ($whatsappCrudo !== '') {
        $whatsapp = autos_normalizar_whatsapp(autos_text($whatsappCrudo, 40));
        if ($whatsapp === null) {
            autos_json(['ok' => false, 'error' => 'WhatsApp inválido'], 400);
        }
    } else {
        $whatsapp = null;
        if ($lead) {
            foreach (['telefono', 'telefono_int'] as $campo) {
                $whatsapp = autos_normalizar_whatsapp((string) $lead[$campo]);
                if ($whatsapp !== null) {
                    break;
                }
            }
        }
        if ($whatsapp === null) {
            autos_json(['ok' => false, 'error' => 'El WhatsApp es obligatorio'], 400);
        }
    }

    $iniciales = autos_iniciales($nombre);
    $stmt = autos_prepare(
        $db,
        'INSERT INTO autos_cuentas (user_id, lead_id, slug, nombre, iniciales, direccion, whatsapp) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $creada = false;
    for ($intento = 0; $intento < 5 && !$creada; $intento++) {
        $slug = autos_slug_unico($db, $nombre);
        $errno = autos_bind_exec($stmt, [
            ['i', $uid],
            ['i', $leadId],
            ['s', $slug],
            ['s', $nombre],
            ['s', $iniciales],
            ['s', $direccion],
            ['s', $whatsapp],
        ]);
        $creada = $errno === 0;
    }
    if (!$creada) {
        autos_json(['ok' => false, 'error' => 'No se pudo crear la cuenta'], 500);
    }
    autos_json(['ok' => true, 'data' => autos_cuenta_salida($db, (int) $stmt->insert_id, $uid)]);
}

if ($action === 'editar') {
    $id = autos_entero($input['id'] ?? 0, 1, 2147483647, 'Cuenta inválida');
    autos_cuenta($id);
    $sets = [];
    $params = [];
    if (array_key_exists('nombre', $input)) {
        $sets[] = 'nombre = ?';
        $params[] = ['s', autos_nombre_cuenta($input['nombre'])];
    }
    if (array_key_exists('direccion', $input)) {
        $sets[] = 'direccion = ?';
        $params[] = ['s', autos_text($input['direccion'], 200)];
    }
    if (array_key_exists('whatsapp', $input)) {
        $sets[] = 'whatsapp = ?';
        $params[] = ['s', autos_whatsapp($input['whatsapp'], true)];
    }
    if (array_key_exists('color', $input)) {
        $sets[] = 'color = ?';
        $params[] = ['s', autos_color_hex($input['color'])];
    }
    if (array_key_exists('tasa_anual', $input)) {
        $sets[] = 'tasa_anual = ?';
        $params[] = ['s', autos_tasa($input['tasa_anual'])];
    }
    if (array_key_exists('plazos', $input)) {
        $sets[] = 'plazos = ?';
        $params[] = ['s', autos_plazos($input['plazos'])];
    }
    if (array_key_exists('entrega_min_pct', $input)) {
        $sets[] = 'entrega_min_pct = ?';
        $params[] = ['i', autos_entero($input['entrega_min_pct'], 0, 90, 'La entrega mínima tiene que estar entre 0 y 90')];
    }
    if (array_key_exists('estado', $input)) {
        $estado = strtolower(autos_text($input['estado'], 20));
        if (!in_array($estado, ['demo', 'activa', 'pausada'], true)) {
            autos_json(['ok' => false, 'error' => 'Estado inválido'], 400);
        }
        $sets[] = 'estado = ?';
        $params[] = ['s', $estado];
    }
    if ($sets === []) {
        autos_json(['ok' => false, 'error' => 'Nada para modificar'], 400);
    }
    $params[] = ['i', $id];
    $params[] = ['i', $uid];
    $stmt = autos_prepare($db, 'UPDATE autos_cuentas SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?');
    autos_bind_exec($stmt, $params);
    autos_json(['ok' => true, 'data' => autos_cuenta_salida($db, $id, $uid)]);
}

if ($action === 'borrar') {
    $id = autos_entero($input['id'] ?? 0, 1, 2147483647, 'Cuenta inválida');
    autos_cuenta($id);
    $db->begin_transaction();
    $fotos = autos_prepare($db, 'DELETE f FROM autos_fotos f JOIN autos_vehiculos v ON v.id = f.vehiculo_id WHERE v.cuenta_id = ?');
    autos_bind_exec($fotos, [['i', $id]]);
    $vehiculos = autos_prepare($db, 'DELETE FROM autos_vehiculos WHERE cuenta_id = ?');
    autos_bind_exec($vehiculos, [['i', $id]]);
    $eventos = autos_prepare($db, 'DELETE FROM autos_eventos WHERE cuenta_id = ?');
    autos_bind_exec($eventos, [['i', $id]]);
    $cuenta = autos_prepare($db, 'DELETE FROM autos_cuentas WHERE id = ? AND user_id = ?');
    autos_bind_exec($cuenta, [['i', $id], ['i', $uid]]);
    if ($cuenta->affected_rows < 1) {
        $db->rollback();
        autos_json(['ok' => false, 'error' => 'Cuenta no encontrada'], 404);
    }
    $db->commit();
    autos_borrar_directorio(autos_media_dir() . '/' . $id);
    autos_json(['ok' => true, 'data' => ['id' => $id]]);
}

autos_json(['ok' => false, 'error' => 'Acción no soportada'], 400);
