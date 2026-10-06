<?php
declare(strict_types=1);

// GET ?cuenta_id= → vehículos de la cuenta, con fotos y días en stock
// POST { action:'crear', cuenta_id, marca, modelo, anio, km, precio, ... }
//    | { action:'editar', id, ...campos }
//    | { action:'estado', id, estado }
//    | { action:'borrar', id }
require_once __DIR__ . '/common.php';

$db = autos_db();
$uid = autos_user_id();

const AUTOS_SQL_VEHICULO = <<<'SQL'
SELECT v.id, v.cuenta_id, v.ref, v.marca, v.modelo, v.`version`, v.anio, v.km, v.precio,
       v.moneda, v.combustible, v.caja, v.tipo, v.color, v.descripcion, v.estado, v.destacado,
       v.ingreso_at, v.vendido_at, v.creado, v.actualizado,
       GREATEST(0, DATEDIFF(COALESCE(DATE(v.vendido_at), CURRENT_DATE), v.ingreso_at)) AS dias_en_stock
FROM autos_vehiculos v
JOIN autos_cuentas c ON c.id = v.cuenta_id
SQL;

function autos_fecha_ingreso(mixed $value): string
{
    $texto = autos_text($value, 10);
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
    $errores = DateTimeImmutable::getLastErrors();
    $avisos = is_array($errores) ? ($errores['warning_count'] + $errores['error_count']) : 0;
    if (!$fecha || $avisos > 0 || $fecha->format('Y-m-d') !== $texto) {
        autos_json(['ok' => false, 'error' => 'Fecha de ingreso inválida'], 400);
    }
    $min = new DateTimeImmutable('1980-01-01');
    $hoy = new DateTimeImmutable('today');
    if ($fecha < $min || $fecha > $hoy) {
        autos_json(['ok' => false, 'error' => 'Fecha de ingreso inválida'], 400);
    }
    return $texto;
}

function autos_flag(mixed $value): int
{
    if ($value === true || $value === 1 || $value === '1') {
        return 1;
    }
    if ($value === false || $value === 0 || $value === '0') {
        return 0;
    }
    autos_json(['ok' => false, 'error' => 'Destacado inválido'], 400);
}

/** @return array<string, array{0:string,1:mixed}> */
function autos_campos_vehiculo(array $input, bool $crear): array
{
    $out = [];
    $presente = static function (string $campo) use ($input, $crear): bool {
        return $crear || array_key_exists($campo, $input);
    };

    if ($presente('marca')) {
        $marca = autos_text($input['marca'] ?? '', 80);
        if ($marca === '') {
            autos_json(['ok' => false, 'error' => 'La marca es obligatoria'], 400);
        }
        $out['marca'] = ['s', $marca];
    }
    if ($presente('modelo')) {
        $modelo = autos_text($input['modelo'] ?? '', 80);
        if ($modelo === '') {
            autos_json(['ok' => false, 'error' => 'El modelo es obligatorio'], 400);
        }
        $out['modelo'] = ['s', $modelo];
    }
    if ($presente('version')) {
        $out['version'] = ['s', autos_text($input['version'] ?? '', 80)];
    }
    if ($crear && !array_key_exists('anio', $input)) {
        autos_json(['ok' => false, 'error' => 'El año es obligatorio'], 400);
    }
    if ($presente('anio')) {
        $max = (int) date('Y') + 1;
        $out['anio'] = ['i', autos_entero($input['anio'], 1980, $max, "El año tiene que estar entre 1980 y $max")];
    }
    if ($crear && !array_key_exists('km', $input)) {
        autos_json(['ok' => false, 'error' => 'Los kilómetros son obligatorios'], 400);
    }
    if ($presente('km')) {
        $out['km'] = ['i', autos_entero($input['km'], 0, 1000000, 'Los kilómetros tienen que estar entre 0 y 1.000.000')];
    }
    if ($crear && !array_key_exists('precio', $input)) {
        autos_json(['ok' => false, 'error' => 'El precio es obligatorio'], 400);
    }
    if ($presente('precio')) {
        $out['precio'] = ['i', autos_entero($input['precio'], 1, 999999999, 'El precio tiene que estar entre 1 y 999.999.999')];
    }
    if ($presente('moneda')) {
        $moneda = strtoupper(autos_text($input['moneda'] ?? 'USD', 3));
        if (!in_array($moneda, ['USD', 'UYU'], true)) {
            autos_json(['ok' => false, 'error' => 'Moneda inválida'], 400);
        }
        $out['moneda'] = ['s', $moneda];
    }
    if ($presente('combustible')) {
        $out['combustible'] = ['s', autos_text($input['combustible'] ?? '', 20)];
    }
    if ($presente('caja')) {
        $out['caja'] = ['s', autos_text($input['caja'] ?? '', 20)];
    }
    if ($presente('tipo')) {
        $tipo = strtolower(autos_text($input['tipo'] ?? 'otro', 20));
        if (!in_array($tipo, ['sedan', 'hatch', 'suv', 'pickup', 'otro'], true)) {
            autos_json(['ok' => false, 'error' => 'Tipo inválido'], 400);
        }
        $out['tipo'] = ['s', $tipo];
    }
    if ($presente('color')) {
        $out['color'] = ['s', autos_text($input['color'] ?? '', 30)];
    }
    if ($presente('descripcion')) {
        $descripcion = autos_text($input['descripcion'] ?? '', 5000);
        $out['descripcion'] = ['s', $descripcion === '' ? null : $descripcion];
    }
    if ($presente('destacado')) {
        $out['destacado'] = ['i', autos_flag($input['destacado'] ?? 0)];
    }
    if (array_key_exists('ingreso_at', $input)) {
        if ($input['ingreso_at'] === null || $input['ingreso_at'] === '') {
            if (!$crear) {
                autos_json(['ok' => false, 'error' => 'Fecha de ingreso inválida'], 400);
            }
            $out['ingreso_at'] = ['s', null];
        } else {
            $out['ingreso_at'] = ['s', autos_fecha_ingreso($input['ingreso_at'])];
        }
    } elseif ($crear) {
        $out['ingreso_at'] = ['s', null];
    }
    return $out;
}

function autos_formatear_vehiculo(array $row, array $fotos): array
{
    $descripcion = $row['descripcion'];
    return [
        'id' => (int) $row['id'],
        'cuenta_id' => (int) $row['cuenta_id'],
        'ref' => (string) $row['ref'],
        'marca' => (string) $row['marca'],
        'modelo' => (string) $row['modelo'],
        'version' => (string) $row['version'],
        'anio' => (int) $row['anio'],
        'km' => (int) $row['km'],
        'precio' => (int) $row['precio'],
        'moneda' => (string) $row['moneda'],
        'combustible' => (string) $row['combustible'],
        'caja' => (string) $row['caja'],
        'tipo' => (string) $row['tipo'],
        'color' => (string) $row['color'],
        'descripcion' => $descripcion !== null && $descripcion !== '' ? (string) $descripcion : null,
        'estado' => (string) $row['estado'],
        'destacado' => (int) $row['destacado'] === 1 ? 1 : 0,
        'ingreso_at' => (string) $row['ingreso_at'],
        'vendido_at' => $row['vendido_at'] !== null ? (string) $row['vendido_at'] : null,
        'creado' => (string) $row['creado'],
        'actualizado' => (string) $row['actualizado'],
        'dias_en_stock' => (int) $row['dias_en_stock'],
        'fotos' => $fotos,
    ];
}

function autos_vehiculo_data(mysqli $db, int $id, int $uid): array
{
    $stmt = autos_prepare($db, AUTOS_SQL_VEHICULO . ' WHERE v.id = ? AND c.user_id = ?');
    autos_bind_exec($stmt, [['i', $id], ['i', $uid]]);
    $row = autos_fila($stmt);
    if (!$row) {
        autos_json(['ok' => false, 'error' => 'Vehículo no encontrado'], 404);
    }
    return autos_formatear_vehiculo($row, autos_fotos_de((int) $row['id'], (int) $row['cuenta_id']));
}

function autos_siguiente_ref(mysqli $db, int $cuentaId): string
{
    $stmt = autos_prepare(
        $db,
        "SELECT COALESCE(MAX(CAST(SUBSTRING(ref, 2) AS UNSIGNED)), 0) AS n
         FROM autos_vehiculos WHERE cuenta_id = ? AND ref REGEXP '^A[0-9]+$'"
    );
    autos_bind_exec($stmt, [['i', $cuentaId]]);
    $n = (int) (autos_fila($stmt)['n'] ?? 0);
    $siguiente = max($n + 1, 101);
    if ($siguiente > 999999999) {
        autos_json(['ok' => false, 'error' => 'No quedan referencias disponibles'], 500);
    }
    return 'A' . $siguiente;
}

function autos_estado_vehiculo(mixed $value): string
{
    $estado = strtolower(autos_text($value, 20));
    if (!in_array($estado, ['disponible', 'reservado', 'vendido'], true)) {
        autos_json(['ok' => false, 'error' => 'Estado inválido'], 400);
    }
    return $estado;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    if (!isset($_GET['cuenta_id'])) {
        autos_json(['ok' => false, 'error' => 'Falta la cuenta'], 400);
    }
    $cuentaId = autos_entero($_GET['cuenta_id'], 1, 2147483647, 'Cuenta inválida');
    autos_cuenta($cuentaId);
    $stmt = autos_prepare(
        $db,
        AUTOS_SQL_VEHICULO . ' WHERE v.cuenta_id = ? AND c.user_id = ? ORDER BY v.ingreso_at DESC, v.id DESC'
    );
    autos_bind_exec($stmt, [['i', $cuentaId], ['i', $uid]]);
    $rows = autos_filas($stmt);
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }
    $fotos = [];
    if ($ids !== []) {
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $params = array_map(static fn (int $id): array => ['i', $id], $ids);
        $fotosStmt = autos_prepare($db, "SELECT * FROM autos_fotos WHERE vehiculo_id IN ($marcas) ORDER BY orden ASC, id ASC");
        autos_bind_exec($fotosStmt, $params);
        foreach (autos_filas($fotosStmt) as $foto) {
            $vid = (int) $foto['vehiculo_id'];
            $fotos[$vid][] = autos_foto_publica($foto, $cuentaId);
        }
    }
    $data = [];
    foreach ($rows as $row) {
        $data[] = autos_formatear_vehiculo($row, $fotos[(int) $row['id']] ?? []);
    }
    autos_json(['ok' => true, 'data' => $data]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    autos_json(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$input = autos_input();
$action = (string) ($input['action'] ?? '');

if ($action === 'crear') {
    $cuentaId = autos_entero($input['cuenta_id'] ?? 0, 1, 2147483647, 'Cuenta inválida');
    autos_cuenta($cuentaId);
    $campos = autos_campos_vehiculo($input, true);
    $estado = autos_estado_vehiculo($input['estado'] ?? 'disponible');

    $cols = ['cuenta_id', 'ref'];
    $holders = ['?', '?'];
    $resto = [];
    foreach ($campos as $col => [$tipo, $valor]) {
        $cols[] = $col === 'version' ? '`version`' : $col;
        if ($col === 'ingreso_at' && $valor === null) {
            $holders[] = 'CURRENT_DATE';
            continue;
        }
        $holders[] = '?';
        $resto[] = [$tipo, $valor];
    }
    $cols[] = 'estado';
    $cols[] = 'vendido_at';
    $holders[] = '?';
    $holders[] = "IF(? = 'vendido', NOW(), NULL)";
    $resto[] = ['s', $estado];
    $resto[] = ['s', $estado];

    $sql = 'INSERT INTO autos_vehiculos (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $holders) . ')';
    $stmt = autos_prepare($db, $sql);
    $creado = false;
    for ($intento = 0; $intento < 5 && !$creado; $intento++) {
        $ref = autos_siguiente_ref($db, $cuentaId);
        $creado = autos_bind_exec($stmt, array_merge([['i', $cuentaId], ['s', $ref]], $resto)) === 0;
    }
    if (!$creado) {
        autos_json(['ok' => false, 'error' => 'No se pudo crear el vehículo'], 500);
    }
    autos_json(['ok' => true, 'data' => autos_vehiculo_data($db, (int) $stmt->insert_id, $uid)]);
}

if ($action === 'editar') {
    $id = autos_entero($input['id'] ?? 0, 1, 2147483647, 'Vehículo inválido');
    autos_vehiculo($id);
    if (array_key_exists('estado', $input)) {
        autos_json(['ok' => false, 'error' => 'Para cambiar el estado usá la acción estado'], 400);
    }
    $campos = autos_campos_vehiculo($input, false);
    if ($campos === []) {
        autos_json(['ok' => false, 'error' => 'Nada para modificar'], 400);
    }
    $sets = [];
    $params = [];
    foreach ($campos as $col => [$tipo, $valor]) {
        $sets[] = $col === 'version' ? 'v.`version` = ?' : 'v.' . $col . ' = ?';
        $params[] = [$tipo, $valor];
    }
    $params[] = ['i', $id];
    $params[] = ['i', $uid];
    $sql = 'UPDATE autos_vehiculos v JOIN autos_cuentas c ON c.id = v.cuenta_id SET '
        . implode(', ', $sets)
        . ' WHERE v.id = ? AND c.user_id = ?';
    $stmt = autos_prepare($db, $sql);
    autos_bind_exec($stmt, $params);
    autos_json(['ok' => true, 'data' => autos_vehiculo_data($db, $id, $uid)]);
}

if ($action === 'estado') {
    $id = autos_entero($input['id'] ?? 0, 1, 2147483647, 'Vehículo inválido');
    $estado = autos_estado_vehiculo($input['estado'] ?? '');
    $actual = autos_vehiculo($id);
    if ((string) $actual['estado'] !== $estado) {
        if ($estado === 'vendido') {
            $sql = 'UPDATE autos_vehiculos v JOIN autos_cuentas c ON c.id = v.cuenta_id
                    SET v.estado = ?, v.vendido_at = NOW()
                    WHERE v.id = ? AND c.user_id = ?';
        } else {
            $sql = 'UPDATE autos_vehiculos v JOIN autos_cuentas c ON c.id = v.cuenta_id
                    SET v.estado = ?, v.vendido_at = NULL
                    WHERE v.id = ? AND c.user_id = ?';
        }
        $stmt = autos_prepare($db, $sql);
        autos_bind_exec($stmt, [['s', $estado], ['i', $id], ['i', $uid]]);
    }
    autos_json(['ok' => true, 'data' => autos_vehiculo_data($db, $id, $uid)]);
}

if ($action === 'borrar') {
    $id = autos_entero($input['id'] ?? 0, 1, 2147483647, 'Vehículo inválido');
    $vehiculo = autos_vehiculo($id);
    $cuentaId = (int) $vehiculo['cuenta_id'];
    $db->begin_transaction();
    $fotos = autos_prepare($db, 'DELETE FROM autos_fotos WHERE vehiculo_id = ?');
    autos_bind_exec($fotos, [['i', $id]]);
    $eventos = autos_prepare($db, 'DELETE FROM autos_eventos WHERE vehiculo_id = ?');
    autos_bind_exec($eventos, [['i', $id]]);
    $borrar = autos_prepare(
        $db,
        'DELETE v FROM autos_vehiculos v JOIN autos_cuentas c ON c.id = v.cuenta_id WHERE v.id = ? AND c.user_id = ?'
    );
    autos_bind_exec($borrar, [['i', $id], ['i', $uid]]);
    if ($borrar->affected_rows < 1) {
        $db->rollback();
        autos_json(['ok' => false, 'error' => 'Vehículo no encontrado'], 404);
    }
    $db->commit();
    autos_borrar_directorio(autos_media_dir() . '/' . $cuentaId . '/' . $id);
    autos_rmdir_si_vacio(autos_media_dir() . '/' . $cuentaId);
    autos_json(['ok' => true, 'data' => ['id' => $id]]);
}

autos_json(['ok' => false, 'error' => 'Acción no soportada'], 400);
