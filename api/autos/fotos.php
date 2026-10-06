<?php
declare(strict_types=1);

// POST multipart action=subir, vehiculo_id, fotos[]
// POST JSON { action:'ordenar', vehiculo_id, ids:[...] }
//         | { action:'borrar', id }
require_once __DIR__ . '/common.php';

const AUTOS_FOTO_MAX_BYTES = 10485760;
const AUTOS_FOTO_MAX = 20;

$db = autos_db();
$uid = autos_user_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    autos_json(['ok' => false, 'error' => 'Método no permitido'], 405);
}

$tipoContenido = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (str_contains($tipoContenido, 'application/json')) {
    $input = autos_input();
} else {
    $input = $_POST;
}
$action = (string) ($input['action'] ?? '');

if ($action === 'subir') {
    if (!extension_loaded('gd')) {
        autos_json(['ok' => false, 'error' => 'El servidor no puede procesar imágenes'], 500);
    }
    @ini_set('memory_limit', '256M');
    $vehiculoId = autos_entero($input['vehiculo_id'] ?? 0, 1, 2147483647, 'Vehículo inválido');
    $vehiculo = autos_vehiculo($vehiculoId);
    $cuentaId = (int) $vehiculo['cuenta_id'];
    $archivos = autos_archivos_subidos('fotos');
    $archivos = array_values(array_filter(
        $archivos,
        static fn (array $archivo): bool => (int) $archivo['error'] !== UPLOAD_ERR_NO_FILE
    ));
    if ($archivos === []) {
        autos_json(['ok' => false, 'error' => 'Elegí al menos una foto'], 400);
    }

    autos_media_asegurar();
    $dir = autos_media_dir() . '/' . $cuentaId . '/' . $vehiculoId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        autos_json(['ok' => false, 'error' => 'No se pudo crear la carpeta de fotos'], 500);
    }

    $conteo = autos_prepare($db, 'SELECT COUNT(*) AS n FROM autos_fotos WHERE vehiculo_id = ?');
    autos_bind_exec($conteo, [['i', $vehiculoId]]);
    $cupo = AUTOS_FOTO_MAX - (int) (autos_fila($conteo)['n'] ?? 0);
    $ext = function_exists('imagewebp') ? 'webp' : 'jpg';
    $insert = autos_prepare(
        $db,
        'INSERT INTO autos_fotos (vehiculo_id, orden, archivo, ext, ancho, alto) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ordenStmt = autos_prepare($db, 'SELECT COALESCE(MAX(orden), -1) AS n FROM autos_fotos WHERE vehiculo_id = ?');

    $creadas = [];
    $errores = [];
    foreach ($archivos as $archivo) {
        if ($cupo <= 0) {
            $errores[] = 'Máximo 20 fotos por vehículo';
            break;
        }
        $procesada = autos_procesar_foto($archivo, $dir, $ext);
        if (is_string($procesada)) {
            $errores[] = $procesada;
            continue;
        }

        autos_bind_exec($ordenStmt, [['i', $vehiculoId]]);
        $orden = (int) (autos_fila($ordenStmt)['n'] ?? -1) + 1;
        $errno = autos_bind_exec($insert, [
            ['i', $vehiculoId],
            ['i', $orden],
            ['s', $procesada['archivo']],
            ['s', $ext],
            ['i', $procesada['ancho']],
            ['i', $procesada['alto']],
        ], false);
        if ($errno !== 0) {
            foreach ($procesada['rutas'] as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
            $errores[] = 'No se pudo guardar la foto';
            continue;
        }
        $cupo--;
        $creadas[] = autos_foto_publica([
            'id' => (int) $insert->insert_id,
            'vehiculo_id' => $vehiculoId,
            'orden' => $orden,
            'archivo' => $procesada['archivo'],
            'ext' => $ext,
            'ancho' => $procesada['ancho'],
            'alto' => $procesada['alto'],
        ], $cuentaId);
    }

    if ($creadas === []) {
        autos_json(['ok' => false, 'error' => $errores[0] ?? 'No se pudo subir la foto'], 400);
    }
    $data = ['fotos' => $creadas];
    if ($errores !== []) {
        $data['errores'] = array_values(array_unique($errores));
    }
    autos_json(['ok' => true, 'data' => $data]);
}

if ($action === 'ordenar') {
    $vehiculoId = autos_entero($input['vehiculo_id'] ?? 0, 1, 2147483647, 'Vehículo inválido');
    $vehiculo = autos_vehiculo($vehiculoId);
    $ids = autos_ids_fotos($input['ids'] ?? null);
    $stmt = autos_prepare($db, 'SELECT id FROM autos_fotos WHERE vehiculo_id = ? ORDER BY orden ASC, id ASC');
    autos_bind_exec($stmt, [['i', $vehiculoId]]);
    $existentes = array_map(static fn (array $row): int => (int) $row['id'], autos_filas($stmt));
    $conjunto = array_flip($existentes);
    foreach ($ids as $fotoId) {
        if (!isset($conjunto[$fotoId])) {
            autos_json(['ok' => false, 'error' => 'Foto no encontrada'], 400);
        }
    }
    $puestos = array_flip($ids);
    $final = $ids;
    foreach ($existentes as $fotoId) {
        if (!isset($puestos[$fotoId])) {
            $final[] = $fotoId;
        }
    }
    $db->begin_transaction();
    $upd = autos_prepare($db, 'UPDATE autos_fotos SET orden = ? WHERE id = ? AND vehiculo_id = ?');
    foreach ($final as $orden => $fotoId) {
        autos_bind_exec($upd, [['i', $orden], ['i', $fotoId], ['i', $vehiculoId]]);
    }
    $db->commit();
    autos_json(['ok' => true, 'data' => autos_fotos_de($vehiculoId, (int) $vehiculo['cuenta_id'])]);
}

if ($action === 'borrar') {
    $id = autos_entero($input['id'] ?? 0, 1, 2147483647, 'Foto inválida');
    $foto = autos_foto($id);
    $stmt = autos_prepare(
        $db,
        'DELETE f FROM autos_fotos f
         JOIN autos_vehiculos v ON v.id = f.vehiculo_id
         JOIN autos_cuentas c ON c.id = v.cuenta_id
         WHERE f.id = ? AND c.user_id = ?'
    );
    autos_bind_exec($stmt, [['i', $id], ['i', $uid]]);
    autos_borrar_archivos_foto(
        (int) $foto['cuenta_id'],
        (int) $foto['vehiculo_id'],
        (string) $foto['archivo'],
        (string) $foto['ext']
    );
    autos_json(['ok' => true, 'data' => ['id' => $id]]);
}

autos_json(['ok' => false, 'error' => 'Acción no soportada'], 400);

/** @return list<array{name:string,tmp_name:string,error:int,size:int}> */
function autos_archivos_subidos(string $campo): array
{
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
        return [];
    }
    $f = $_FILES[$campo];
    if (!isset($f['name'], $f['tmp_name'], $f['error'], $f['size'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return [[
            'name' => (string) $f['name'],
            'tmp_name' => (string) $f['tmp_name'],
            'error' => (int) $f['error'],
            'size' => (int) $f['size'],
        ]];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $out[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''),
            'error' => (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($f['size'][$i] ?? 0),
        ];
    }
    return $out;
}

/** @return list<int> */
function autos_ids_fotos(mixed $value): array
{
    if (!is_array($value) || $value === [] || array_is_list($value) === false) {
        autos_json(['ok' => false, 'error' => 'Indicá el orden de las fotos'], 400);
    }
    $ids = [];
    foreach ($value as $item) {
        $id = autos_entero($item, 1, 2147483647, 'Foto inválida');
        if (in_array($id, $ids, true)) {
            autos_json(['ok' => false, 'error' => 'Hay fotos repetidas'], 400);
        }
        $ids[] = $id;
    }
    return $ids;
}

/**
 * @param array{name:string,tmp_name:string,error:int,size:int} $archivo
 * @return array{archivo:string,ancho:int,alto:int,rutas:list<string>}|string
 */
function autos_procesar_foto(array $archivo, string $dir, string $ext): array|string
{
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE || $archivo['size'] > AUTOS_FOTO_MAX_BYTES) {
        return 'El archivo supera los 10 MB';
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        return 'No se pudo leer la imagen';
    }
    $info = @getimagesize($archivo['tmp_name']);
    if ($info === false) {
        return 'El archivo no es una imagen JPEG, PNG o WebP';
    }
    $tipo = (int) $info[2];
    if (!in_array($tipo, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return 'El archivo no es una imagen JPEG, PNG o WebP';
    }
    $anchoOrigen = (int) $info[0];
    $altoOrigen = (int) $info[1];
    if ($anchoOrigen < 1 || $altoOrigen < 1 || $anchoOrigen > 8000 || $altoOrigen > 8000) {
        return 'La imagen es demasiado grande';
    }
    if ($tipo === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) {
        return 'No se pudo leer la imagen';
    }

    $img = match ($tipo) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($archivo['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($archivo['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($archivo['tmp_name']),
        default => false,
    };
    if (!$img instanceof GdImage) {
        return 'No se pudo leer la imagen';
    }
    $img = autos_orientar($img, $archivo['tmp_name'], $tipo);
    if (function_exists('imagepalettetotruecolor') && !imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }

    $nombre = bin2hex(random_bytes(8));
    $ruta1600 = $dir . '/' . $nombre . '_1600.' . $ext;
    $ruta800 = $dir . '/' . $nombre . '_800.' . $ext;
    $grande = autos_rendition($img, 1600, $ruta1600, $ext);
    if ($grande === null) {
        unset($img);
        @unlink($ruta1600);
        return 'No se pudo guardar la foto';
    }
    $chica = autos_rendition($img, 800, $ruta800, $ext);
    unset($img);
    if ($chica === null) {
        @unlink($ruta1600);
        @unlink($ruta800);
        return 'No se pudo guardar la foto';
    }
    return [
        'archivo' => $nombre,
        'ancho' => $grande['ancho'],
        'alto' => $grande['alto'],
        'rutas' => [$ruta1600, $ruta800],
    ];
}

function autos_orientar(GdImage $img, string $tmp, int $tipo): GdImage
{
    if ($tipo !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($tmp);
    $orientacion = (int) (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1);
    if ($orientacion === 2 || $orientacion === 4) {
        imageflip($img, $orientacion === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        return $img;
    }
    $grados = match ($orientacion) {
        3 => 180,
        5, 6 => -90,
        7, 8 => 90,
        default => null,
    };
    if ($grados === null) {
        return $img;
    }
    $rotada = imagerotate($img, $grados, 0);
    if (!$rotada instanceof GdImage) {
        return $img;
    }
    unset($img);
    if ($orientacion === 5 || $orientacion === 7) {
        imageflip($rotada, IMG_FLIP_HORIZONTAL);
    }
    return $rotada;
}

/** @return array{ancho:int,alto:int}|null */
function autos_rendition(GdImage $origen, int $anchoMax, string $destino, string $ext): ?array
{
    $origenW = imagesx($origen);
    $origenH = imagesy($origen);
    if ($origenW < 1 || $origenH < 1) {
        return null;
    }
    $ancho = min($origenW, $anchoMax);
    $alto = max(1, (int) round($origenH * ($ancho / $origenW)));
    $img = imagecreatetruecolor($ancho, $alto);
    if (!$img instanceof GdImage) {
        return null;
    }
    if ($ext === 'jpg') {
        $blanco = imagecolorallocate($img, 255, 255, 255);
        if ($blanco !== false) {
            imagefilledrectangle($img, 0, 0, $ancho, $alto, $blanco);
        }
    } else {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparente = imagecolorallocatealpha($img, 0, 0, 0, 127);
        if ($transparente !== false) {
            imagefilledrectangle($img, 0, 0, $ancho, $alto, $transparente);
        }
        imagealphablending($img, true);
    }
    imagecopyresampled($img, $origen, 0, 0, 0, 0, $ancho, $alto, $origenW, $origenH);
    if ($ext !== 'jpg') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    $ok = $ext === 'webp' ? imagewebp($img, $destino, 80) : imagejpeg($img, $destino, 82);
    unset($img);
    if ($ok !== true || !is_file($destino)) {
        if (is_file($destino)) {
            @unlink($destino);
        }
        return null;
    }
    return ['ancho' => $ancho, 'alto' => $alto];
}
