<?php
declare(strict_types=1);

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../auth/auth_guard.php';
require_once __DIR__ . '/../db.php'; // define $conn
require_once __DIR__ . '/schema.php';

function autos_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function autos_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function autos_text(mixed $value, int $max): string
{
    return mb_substr(trim((string) $value), 0, $max);
}

function autos_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function autos_db(): mysqli
{
    global $conn;
    static $ready = false;
    if (!$ready) {
        autos_crear_tablas($conn);
        $ready = true;
    }
    return $conn;
}

function autos_prepare(mysqli $db, string $sql): mysqli_stmt
{
    try {
        $stmt = $db->prepare($sql);
    } catch (mysqli_sql_exception) {
        autos_json(['ok' => false, 'error' => 'Error de base de datos'], 500);
    }
    if (!$stmt instanceof mysqli_stmt) {
        autos_json(['ok' => false, 'error' => 'Error de base de datos'], 500);
    }
    return $stmt;
}

/** @param list<array{0:string,1:mixed}> $params */
function autos_bind_exec(mysqli_stmt $stmt, array $params, bool $cortar = true): int
{
    if ($params !== []) {
        $tipos = '';
        $vals = [];
        foreach ($params as [$tipo, $valor]) {
            $tipos .= $tipo;
            $vals[] = $valor;
        }
        $refs = [];
        foreach ($vals as $i => $_) {
            $refs[] = &$vals[$i];
        }
        $stmt->bind_param($tipos, ...$refs);
    }
    try {
        if ($stmt->execute()) {
            return 0;
        }
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() === 1062) {
            return 1062;
        }
        if (!$cortar) {
            return (int) $e->getCode() ?: 1;
        }
        autos_json(['ok' => false, 'error' => 'No se pudo guardar'], 500);
    }
    if ($stmt->errno === 1062) {
        return 1062;
    }
    if (!$cortar) {
        return $stmt->errno ?: 1;
    }
    autos_json(['ok' => false, 'error' => 'No se pudo guardar'], 500);
}

function autos_filas(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();
    if (!$result instanceof mysqli_result) {
        autos_json(['ok' => false, 'error' => 'Error de base de datos'], 500);
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
}

function autos_fila(mysqli_stmt $stmt): ?array
{
    return autos_filas($stmt)[0] ?? null;
}

function autos_entero(mixed $value, int $min, int $max, string $error): int
{
    if (is_string($value)) {
        $value = trim($value);
        if (!preg_match('/^-?\d+$/', $value)) {
            autos_json(['ok' => false, 'error' => $error], 400);
        }
    } elseif (is_float($value)) {
        if (floor($value) !== $value) {
            autos_json(['ok' => false, 'error' => $error], 400);
        }
    } elseif (!is_int($value)) {
        autos_json(['ok' => false, 'error' => $error], 400);
    }
    $n = (int) $value;
    if ($n < $min || $n > $max) {
        autos_json(['ok' => false, 'error' => $error], 400);
    }
    return $n;
}

function autos_cuenta(int $id): array
{
    $db = autos_db();
    $uid = autos_user_id();
    $stmt = autos_prepare($db, 'SELECT * FROM autos_cuentas WHERE id = ? AND user_id = ?');
    autos_bind_exec($stmt, [['i', $id], ['i', $uid]]);
    $cuenta = autos_fila($stmt);
    if (!$cuenta) {
        autos_json(['ok' => false, 'error' => 'Cuenta no encontrada'], 404);
    }
    return $cuenta;
}

function autos_vehiculo(int $id): array
{
    $db = autos_db();
    $uid = autos_user_id();
    $stmt = autos_prepare(
        $db,
        'SELECT v.* FROM autos_vehiculos v
         JOIN autos_cuentas c ON c.id = v.cuenta_id
         WHERE v.id = ? AND c.user_id = ?'
    );
    autos_bind_exec($stmt, [['i', $id], ['i', $uid]]);
    $vehiculo = autos_fila($stmt);
    if (!$vehiculo) {
        autos_json(['ok' => false, 'error' => 'Vehículo no encontrado'], 404);
    }
    return $vehiculo;
}

function autos_foto(int $id): array
{
    $db = autos_db();
    $uid = autos_user_id();
    $stmt = autos_prepare(
        $db,
        'SELECT f.*, v.cuenta_id FROM autos_fotos f
         JOIN autos_vehiculos v ON v.id = f.vehiculo_id
         JOIN autos_cuentas c ON c.id = v.cuenta_id
         WHERE f.id = ? AND c.user_id = ?'
    );
    autos_bind_exec($stmt, [['i', $id], ['i', $uid]]);
    $foto = autos_fila($stmt);
    if (!$foto) {
        autos_json(['ok' => false, 'error' => 'Foto no encontrada'], 404);
    }
    return $foto;
}

function autos_media_dir(): string
{
    return dirname(__DIR__, 2) . '/autos-media';
}

function autos_foto_url(int $cuentaId, int $vehiculoId, string $archivo, string $ext, int $tamano): string
{
    return '/autos-media/' . $cuentaId . '/' . $vehiculoId . '/' . $archivo . '_' . $tamano . '.' . $ext;
}

function autos_fotos_de(int $vehiculoId, int $cuentaId): array
{
    $db = autos_db();
    $stmt = autos_prepare($db, 'SELECT * FROM autos_fotos WHERE vehiculo_id = ? ORDER BY orden ASC, id ASC');
    autos_bind_exec($stmt, [['i', $vehiculoId]]);
    $out = [];
    foreach (autos_filas($stmt) as $foto) {
        $out[] = autos_foto_publica($foto, $cuentaId);
    }
    return $out;
}

function autos_foto_publica(array $foto, int $cuentaId): array
{
    $archivo = (string) $foto['archivo'];
    $ext = (string) $foto['ext'];
    $vehiculoId = (int) $foto['vehiculo_id'];
    return [
        'id' => (int) $foto['id'],
        'orden' => (int) $foto['orden'],
        'archivo' => $archivo,
        'ext' => $ext,
        'ancho' => (int) $foto['ancho'],
        'alto' => (int) $foto['alto'],
        'url_800' => autos_foto_url($cuentaId, $vehiculoId, $archivo, $ext, 800),
        'url_1600' => autos_foto_url($cuentaId, $vehiculoId, $archivo, $ext, 1600),
    ];
}

function autos_media_asegurar(): void
{
    $dir = autos_media_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        autos_json(['ok' => false, 'error' => 'No se pudo crear la carpeta de fotos'], 500);
    }
    $htaccess = $dir . '/.htaccess';
    if (is_file($htaccess)) {
        return;
    }
    $contenido = <<<'HT'
Options -Indexes

<IfModule mod_php.c>
php_flag engine off
</IfModule>
<IfModule mod_php7.c>
php_flag engine off
</IfModule>
<IfModule mod_php8.c>
php_flag engine off
</IfModule>

<FilesMatch "\.(php|phtml|phar)$">
  Require all denied
</FilesMatch>
HT;
    if (file_put_contents($htaccess, $contenido . "\n", LOCK_EX) === false) {
        autos_json(['ok' => false, 'error' => 'No se pudo proteger la carpeta de fotos'], 500);
    }
}

function autos_borrar_archivos_foto(int $cuentaId, int $vehiculoId, string $archivo, string $ext): void
{
    if (!preg_match('/^[a-f0-9]{16}$/', $archivo) || !in_array($ext, ['webp', 'jpg'], true)) {
        return;
    }
    $base = autos_media_dir() . '/' . $cuentaId . '/' . $vehiculoId . '/' . $archivo;
    foreach ([800, 1600] as $tamano) {
        $path = $base . '_' . $tamano . '.' . $ext;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    autos_rmdir_si_vacio(autos_media_dir() . '/' . $cuentaId . '/' . $vehiculoId);
    autos_rmdir_si_vacio(autos_media_dir() . '/' . $cuentaId);
}

function autos_borrar_directorio(string $dir): void
{
    $raiz = realpath(autos_media_dir());
    $actual = realpath($dir);
    if ($raiz === false || $actual === false || $actual === $raiz) {
        return;
    }
    $prefijo = rtrim($raiz, '/\\') . DIRECTORY_SEPARATOR;
    if (!str_starts_with($actual, $prefijo)) {
        return;
    }
    $items = scandir($actual);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $actual . DIRECTORY_SEPARATOR . $item;
        if (is_link($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            autos_borrar_directorio($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($actual);
}

function autos_rmdir_si_vacio(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items !== false && count(array_diff($items, ['.', '..'])) === 0) {
        @rmdir($dir);
    }
}
