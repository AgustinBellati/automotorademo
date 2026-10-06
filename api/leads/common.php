<?php
declare(strict_types=1);

require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../auth/auth_guard.php';
require_once __DIR__ . '/../db.php'; // define $conn
require_once __DIR__ . '/places.php';
require_once __DIR__ . '/overture.php';
require_once __DIR__ . '/schema.php';

const LEADS_ESTADOS = [
    'nuevo', 'demo_lista', 'contactado', 'vio_demo', 'respondio', 'reunion', 'cliente', 'perdido', 'descartado',
];

const LEADS_MOTIVOS = ['cerrado', 'tiene_web', 'pocas_resenas', 'nota_baja', 'sin_telefono', 'otro'];

function leads_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function leads_input(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function leads_text(mixed $value, int $max): string
{
    return mb_substr(trim((string) $value), 0, $max);
}

function leads_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function leads_db(): mysqli
{
    global $conn;
    static $ready = false;
    if (!$ready) {
        leads_crear_tablas($conn);
        $ready = true;
    }
    return $conn;
}
