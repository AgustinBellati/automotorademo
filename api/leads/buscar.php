<?php
declare(strict_types=1);

// POST { rubro, ciudad, incluirRedes } → negocios sin web propia
require_once __DIR__ . '/common.php';

// GET → lista de rubros conocidos (para el autocompletado)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    leads_json(['ok' => true, 'data' => array_keys(RUBROS)]);
}

$input = leads_input();
$rubro = leads_text($input['rubro'] ?? '', 120);
$ciudad = leads_text($input['ciudad'] ?? '', 160);
$incluirRedes = (bool) ($input['incluirRedes'] ?? true);

if ($rubro === '' || $ciudad === '') {
    leads_json(['ok' => false, 'error' => 'Completá rubro y ciudad'], 400);
}

// Base abierta de Overture Maps importada en MySQL
$db = leads_db();
try {
    $res = overture_buscar($db, $rubro, $ciudad, $incluirRedes);
} catch (RuntimeException $e) {
    leads_json(['ok' => false, 'error' => $e->getMessage()], 502);
}

// Marcar los que ya están guardados
$ids = array_column($res['leads'], 'place_id');
$guardados = [];
if ($ids) {
    try {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT place_id FROM leadfinder_leads WHERE user_id = ? AND place_id IN ($marks)");
        $uid = leads_user_id();
        $stmt->bind_param('i' . str_repeat('s', count($ids)), $uid, ...$ids);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $guardados[$row['place_id']] = true;
        }
    } catch (mysqli_sql_exception) {
        // si falla la BD igual devolvemos los resultados
    }
}

foreach ($res['leads'] as &$lead) {
    $lead['guardado'] = isset($guardados[$lead['place_id']]);
}
unset($lead);

leads_json(['ok' => true, 'data' => $res]);
