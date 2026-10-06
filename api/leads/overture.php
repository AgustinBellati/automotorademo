<?php
declare(strict_types=1);

// Búsqueda sobre la tabla leadfinder_places (negocios de Uruguay importados de Overture Maps).
require_once __DIR__ . '/rubros.php';

const OVERTURE_CONFIANZA_MIN = 0.3;
const OVERTURE_MAX_RESULTADOS = 300;

function overture_buscar(mysqli $db, string $rubro, string $ciudad, bool $incluirRedes): array
{
    $where = ['confianza >= ?', '(localidad LIKE ? OR direccion LIKE ?)'];
    $ciudadLike = '%' . $ciudad . '%';
    $params = [OVERTURE_CONFIANZA_MIN, $ciudadLike, $ciudadLike];
    $types = 'dss';

    // Rubro conocido → por categoría (y nombre); desconocido → por nombre o categoría en inglés
    $rubroLike = '%' . $rubro . '%';
    $cats = rubro_categorias($rubro);
    $orRubro = ['nombre LIKE ?', 'categoria LIKE ?'];
    $params[] = $rubroLike;
    $params[] = $rubroLike;
    $types .= 'ss';
    foreach ($cats as $cat) {
        $orRubro[] = 'jerarquia LIKE ?';
        $params[] = '% ' . $cat . ' %';
        $types .= 's';
    }
    $where[] = '(' . implode(' OR ', $orRubro) . ')';
    $whereSql = implode(' AND ', $where);

    $stmt = $db->prepare("SELECT COUNT(*) FROM leadfinder_places WHERE $whereSql");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $revisados = (int) $stmt->get_result()->fetch_row()[0];

    $estados = $incluirRedes ? "'sin_web','solo_redes'" : "'sin_web'";
    $stmt = $db->prepare(
        "SELECT * FROM leadfinder_places WHERE $whereSql AND estado_web IN ($estados)
         ORDER BY (telefono <> '') DESC, confianza DESC LIMIT " . OVERTURE_MAX_RESULTADOS
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $leads = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $leads[] = [
            'place_id' => $r['place_id'],
            'nombre' => $r['nombre'],
            'categoria' => overture_categoria_legible($r['categoria']),
            'direccion' => trim($r['direccion'] . ($r['localidad'] ? ', ' . $r['localidad'] : ''), ', '),
            'telefono' => $r['telefono'],
            'telefono_int' => $r['telefono'],
            'estado_web' => $r['estado_web'],
            'web_actual' => $r['web'],
            'facebook' => $r['facebook'],
            'email' => $r['email'],
            'rating' => null,
            'resenas' => 0,
            // Link para verificar en Google Maps que de verdad no tenga web
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query='
                . rawurlencode($r['nombre'] . ' ' . $r['direccion'] . ' ' . $r['localidad']),
        ];
    }

    return ['revisados' => $revisados, 'leads' => $leads, 'fuente' => 'overture', 'limite' => OVERTURE_MAX_RESULTADOS];
}

/** "automotive_repair" → "Automotive repair" (si es un rubro conocido, usa el nombre en español) */
function overture_categoria_legible(string $cat): string
{
    $elegido = null;
    foreach (RUBROS as $nombre => $lista) {
        $pos = array_search($cat, $lista, true);
        if ($pos === 0) {
            $elegido = $nombre;
            break;
        }
        if ($pos !== false && $elegido === null) {
            $elegido = $nombre;
        }
    }
    if ($elegido === null) {
        return ucfirst(str_replace('_', ' ', $cat));
    }
    return mb_strtoupper(mb_substr($elegido, 0, 1)) . mb_substr($elegido, 1);
}
