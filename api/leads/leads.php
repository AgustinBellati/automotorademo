<?php
declare(strict_types=1);

// GET → mis leads guardados
// POST { action: 'save', rubro, ciudad, leads: [...] }
//    | { action: 'update', id, estado, notas }
//    | { action: 'delete', id }
//    | { action: 'calificar', id, calificado: bool, motivo?: string }
require_once __DIR__ . '/common.php';

$db = leads_db();
$uid = leads_user_id();

function leads_listar(mysqli $db, int $uid): array
{
    $stmt = $db->prepare('SELECT * FROM leadfinder_leads WHERE user_id = ? ORDER BY creado DESC, id DESC');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['resenas'] = (int) $r['resenas'];
        $r['rating'] = $r['rating'] !== null ? (float) $r['rating'] : null;
        $r['notas'] = (string) ($r['notas'] ?? '');
        $r['calificado'] = $r['calificado'] === null ? null : ((int) $r['calificado'] === 1);
        $r['motivo_descarte'] = $r['motivo_descarte'] !== null && $r['motivo_descarte'] !== '' ? $r['motivo_descarte'] : null;
        $r['template_id'] = $r['template_id'] !== null && $r['template_id'] !== '' ? $r['template_id'] : null;
        $r['revisado_google_at'] = $r['revisado_google_at'] ?: null;
    }
    return $rows;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    leads_json(['ok' => true, 'data' => leads_listar($db, $uid)]);
}

$input = leads_input();
$action = (string) ($input['action'] ?? '');

if ($action === 'save') {
    $rubro = leads_text($input['rubro'] ?? '', 120);
    $ciudad = leads_text($input['ciudad'] ?? '', 160);
    $items = is_array($input['leads'] ?? null) ? array_slice($input['leads'], 0, 200) : [];

    $stmt = $db->prepare(
        'INSERT IGNORE INTO leadfinder_leads
            (user_id, place_id, nombre, rubro, ciudad, categoria, direccion, telefono, telefono_int,
             estado_web, web_actual, rating, resenas, maps_url, facebook, email)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $nuevos = 0;
    foreach ($items as $l) {
        $placeId = leads_text($l['place_id'] ?? '', 255);
        $nombre = leads_text($l['nombre'] ?? '', 255);
        if ($placeId === '' || $nombre === '') {
            continue;
        }
        $categoria = leads_text($l['categoria'] ?? '', 120);
        $direccion = leads_text($l['direccion'] ?? '', 255);
        $telefono = leads_text($l['telefono'] ?? '', 60);
        $telefonoInt = leads_text($l['telefono_int'] ?? '', 60);
        $estadoWeb = ($l['estado_web'] ?? '') === 'solo_redes' ? 'solo_redes' : 'sin_web';
        $web = leads_text($l['web_actual'] ?? '', 500);
        $rating = isset($l['rating']) && $l['rating'] !== null ? (float) $l['rating'] : null;
        $resenas = (int) ($l['resenas'] ?? 0);
        $maps = leads_text($l['maps_url'] ?? '', 500);
        $facebook = leads_text($l['facebook'] ?? '', 500);
        $email = leads_text($l['email'] ?? '', 255);

        $stmt->bind_param(
            'issssssssssdisss',
            $uid, $placeId, $nombre, $rubro, $ciudad, $categoria, $direccion, $telefono, $telefonoInt,
            $estadoWeb, $web, $rating, $resenas, $maps, $facebook, $email
        );
        $stmt->execute();
        $nuevos += $stmt->affected_rows;
    }
    leads_json(['ok' => true, 'data' => ['guardados' => $nuevos]]);
}

if ($action === 'update') {
    $id = (int) ($input['id'] ?? 0);
    $estado = (string) ($input['estado'] ?? 'nuevo');
    if (!in_array($estado, LEADS_ESTADOS, true)) {
        leads_json(['ok' => false, 'error' => 'Estado inválido'], 400);
    }
    $notas = leads_text($input['notas'] ?? '', 5000);
    $stmt = $db->prepare('UPDATE leadfinder_leads SET estado = ?, notas = ? WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ssii', $estado, $notas, $id, $uid);
    $stmt->execute();
    leads_json(['ok' => true]);
}

if ($action === 'calificar') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0 || !is_bool($input['calificado'] ?? null)) {
        leads_json(['ok' => false, 'error' => 'Calificación inválida'], 400);
    }
    $calificado = $input['calificado'];
    $motivo = null;
    if (!$calificado) {
        $motivo = (string) ($input['motivo'] ?? '');
        if (!in_array($motivo, LEADS_MOTIVOS, true)) {
            leads_json(['ok' => false, 'error' => 'Motivo inválido'], 400);
        }
    }

    $stmt = $db->prepare(
        'SELECT l.rubro, l.estado, p.categoria AS categoria_overture
         FROM leadfinder_leads l
         LEFT JOIN leadfinder_places p ON p.place_id = l.place_id COLLATE utf8mb4_unicode_ci
         WHERE l.id = ? AND l.user_id = ?'
    );
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $lead = $stmt->get_result()->fetch_assoc();
    if (!$lead) {
        leads_json(['ok' => false, 'error' => 'Lead no encontrado'], 404);
    }

    $template = null;
    $estado = (string) $lead['estado'];
    if ($calificado) {
        $cat = strtolower(trim((string) ($lead['categoria_overture'] ?? '')));
        $rubro = mb_strtolower(trim((string) $lead['rubro']));
        if ($cat === 'barber' || $rubro === 'barbería' || $rubro === 'barberia') {
            $template = 'barberia';
        }
    } else {
        $estado = 'descartado';
    }

    $db->begin_transaction();
    if ($calificado) {
        $templateParam = $template ?? '';
        $upd = $db->prepare(
            "UPDATE leadfinder_leads
             SET calificado = 1, motivo_descarte = NULL, revisado_google_at = NOW(), template_id = NULLIF(?, '')
             WHERE id = ? AND user_id = ?"
        );
        $upd->bind_param('sii', $templateParam, $id, $uid);
    } else {
        $upd = $db->prepare(
            "UPDATE leadfinder_leads
             SET calificado = 0, motivo_descarte = ?, revisado_google_at = NOW(), template_id = NULL, estado = 'descartado'
             WHERE id = ? AND user_id = ?"
        );
        $upd->bind_param('sii', $motivo, $id, $uid);
    }
    $tipo = $calificado ? 'calificado' : 'descartado';
    $por = 'usuario';
    $ev = $db->prepare('INSERT INTO eventos (user_id, lead_id, tipo, creado_por) VALUES (?, ?, ?, ?)');
    $ev->bind_param('iiss', $uid, $id, $tipo, $por);
    if (!$upd->execute() || !$ev->execute()) {
        $db->rollback();
        leads_json(['ok' => false, 'error' => 'No se pudo guardar la calificación'], 500);
    }
    $db->commit();

    $out = $db->prepare('SELECT revisado_google_at, estado FROM leadfinder_leads WHERE id = ? AND user_id = ?');
    $out->bind_param('ii', $id, $uid);
    $out->execute();
    $guardado = $out->get_result()->fetch_assoc();

    leads_json(['ok' => true, 'data' => [
        'calificado' => $calificado,
        'motivo_descarte' => $motivo,
        'revisado_google_at' => $guardado['revisado_google_at'] ?? null,
        'template_id' => $template,
        'estado' => (string) ($guardado['estado'] ?? $estado),
    ]]);
}

if ($action === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM leadfinder_leads WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    leads_json(['ok' => true]);
}

leads_json(['ok' => false, 'error' => 'Acción no soportada'], 400);
