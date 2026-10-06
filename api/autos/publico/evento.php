<?php
declare(strict_types=1);

// POST { slug, ref?, tipo } → registra una visita o un clic del catálogo público (sin login).
// No guarda la IP: sólo un hash corto que cambia cada día.
require_once __DIR__ . '/_datos.php';

header('Content-Type: application/json; charset=utf-8');

function autos_evento_fin(int $status = 204): never
{
    http_response_code($status);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    autos_evento_fin(405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    autos_evento_fin(400);
}
$tipo = (string) ($input['tipo'] ?? '');
if (!in_array($tipo, ['visita_catalogo', 'visita_auto', 'click_whatsapp', 'copiar_link'], true)) {
    autos_evento_fin(400);
}

$db = autos_pub_db();
$cuenta = autos_pub_cuenta($db, (string) ($input['slug'] ?? ''));
if (!$cuenta) {
    autos_evento_fin(404);
}
$cid = (int) $cuenta['id'];

// Las visitas del dueño de la cuenta no cuentan
if (session_status() === PHP_SESSION_NONE) {
    session_start(['read_and_close' => true]);
}
if ((int) ($_SESSION['user_id'] ?? 0) === (int) $cuenta['user_id']) {
    autos_evento_fin();
}

$vid = null;
if ($tipo !== 'visita_catalogo') {
    $ref = (string) ($input['ref'] ?? '');
    if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $ref)) {
        autos_evento_fin(400);
    }
    $stmt = $db->prepare("SELECT id FROM autos_vehiculos WHERE cuenta_id = ? AND ref = ? AND estado IN ('disponible','reservado')");
    $stmt->bind_param('is', $cid, $ref);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        autos_evento_fin(404);
    }
    $vid = (int) $row['id'];
}

$visitante = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Y-m-d') . '|' . __DIR__), 0, 16);

// La misma visita (visitante, auto, tipo) cuenta una vez cada 30 minutos
$stmt = $db->prepare(
    'SELECT 1 FROM autos_eventos
     WHERE cuenta_id = ? AND visitante = ? AND tipo = ? AND vehiculo_id <=> ? AND creado > NOW() - INTERVAL 30 MINUTE LIMIT 1'
);
$stmt->bind_param('issi', $cid, $visitante, $tipo, $vid);
$stmt->execute();
$repetido = (bool) $stmt->get_result()->fetch_row();
$stmt->close();

if (!$repetido) {
    $stmt = $db->prepare('INSERT INTO autos_eventos (cuenta_id, vehiculo_id, tipo, visitante) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iiss', $cid, $vid, $tipo, $visitante);
    $stmt->execute();
    $stmt->close();
}
autos_evento_fin();
