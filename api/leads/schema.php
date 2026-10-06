<?php
declare(strict_types=1);

// Tablas de LeadFinder (se crean solas la primera vez)
function leads_crear_tablas(mysqli $conn): void
{
    // Negocios importados de Overture Maps (scripts/importar_overture.php en leadfinder)
    $conn->query("CREATE TABLE IF NOT EXISTS leadfinder_places (
        place_id VARCHAR(64) PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        categoria VARCHAR(120) NOT NULL DEFAULT '',
        jerarquia VARCHAR(400) NOT NULL DEFAULT '',
        direccion VARCHAR(255) NOT NULL DEFAULT '',
        localidad VARCHAR(120) NOT NULL DEFAULT '',
        telefono VARCHAR(60) NOT NULL DEFAULT '',
        web VARCHAR(500) NOT NULL DEFAULT '',
        facebook VARCHAR(500) NOT NULL DEFAULT '',
        email VARCHAR(255) NOT NULL DEFAULT '',
        estado_web VARCHAR(20) NOT NULL DEFAULT 'sin_web',
        confianza DECIMAL(3,2) NOT NULL DEFAULT 0,
        lat DECIMAL(9,6) NULL,
        lon DECIMAL(9,6) NULL,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_localidad (localidad),
        KEY idx_estado (estado_web, confianza)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS leadfinder_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        place_id VARCHAR(255) NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        rubro VARCHAR(120) NOT NULL DEFAULT '',
        ciudad VARCHAR(160) NOT NULL DEFAULT '',
        categoria VARCHAR(120) NOT NULL DEFAULT '',
        direccion VARCHAR(255) NOT NULL DEFAULT '',
        telefono VARCHAR(60) NOT NULL DEFAULT '',
        telefono_int VARCHAR(60) NOT NULL DEFAULT '',
        estado_web VARCHAR(20) NOT NULL DEFAULT 'sin_web',
        web_actual VARCHAR(500) NOT NULL DEFAULT '',
        rating DECIMAL(2,1) NULL,
        resenas INT NOT NULL DEFAULT 0,
        maps_url VARCHAR(500) NOT NULL DEFAULT '',
        facebook VARCHAR(500) NOT NULL DEFAULT '',
        email VARCHAR(255) NOT NULL DEFAULT '',
        estado VARCHAR(20) NOT NULL DEFAULT 'nuevo',
        notas TEXT NULL,
        google_place_id VARCHAR(255) NULL,
        match_estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
        calificado TINYINT NULL,
        motivo_descarte VARCHAR(40) NULL,
        revisado_google_at DATETIME NULL,
        template_id VARCHAR(40) NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_place (user_id, place_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Columnas agregadas después de la primera versión
    foreach (['facebook' => 'VARCHAR(500)', 'email' => 'VARCHAR(255)'] as $col => $tipo) {
        $existe = $conn->query("SHOW COLUMNS FROM leadfinder_leads LIKE '$col'")->num_rows > 0;
        if (!$existe) {
            $conn->query("ALTER TABLE leadfinder_leads ADD COLUMN $col $tipo NOT NULL DEFAULT '' AFTER maps_url");
        }
    }

    // match_estado: pendiente | ok | dudoso | sin_match
    $nuevas = [
        'google_place_id' => 'VARCHAR(255) NULL',
        'match_estado' => "VARCHAR(20) NOT NULL DEFAULT 'pendiente'",
        'calificado' => 'TINYINT NULL',
        'motivo_descarte' => 'VARCHAR(40) NULL',
        'revisado_google_at' => 'DATETIME NULL',
        'template_id' => 'VARCHAR(40) NULL',
    ];
    foreach ($nuevas as $col => $tipo) {
        $like = str_replace('_', '\\_', $col);
        $existe = $conn->query("SHOW COLUMNS FROM leadfinder_leads LIKE '$like'")->num_rows > 0;
        if (!$existe) {
            $conn->query("ALTER TABLE leadfinder_leads ADD COLUMN `$col` $tipo");
        }
    }

    $conn->query("UPDATE leadfinder_leads SET estado = 'respondio' WHERE estado = 'interesado'");

    // estado: borrador | publicada | vencida | retirada
    $conn->query("CREATE TABLE IF NOT EXISTS demos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lead_id INT NOT NULL,
        slug VARCHAR(80) NOT NULL,
        template_id VARCHAR(40) NOT NULL,
        template_version INT NOT NULL DEFAULT 1,
        contenido JSON NULL,
        estado VARCHAR(20) NOT NULL DEFAULT 'borrador',
        publicada_at DATETIME NULL,
        vence_at DATETIME NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_lead (lead_id),
        UNIQUE KEY uq_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS demo_slugs_viejos (
        slug VARCHAR(80) PRIMARY KEY,
        demo_id INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // creado_por: sistema | usuario
    $conn->query("CREATE TABLE IF NOT EXISTS eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lead_id INT NOT NULL,
        demo_id INT NULL,
        tipo VARCHAR(30) NOT NULL,
        minutos SMALLINT NULL,
        meta JSON NULL,
        creado_por VARCHAR(10) NOT NULL DEFAULT 'sistema',
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_lead_tipo (lead_id, tipo),
        KEY idx_tipo_creado (tipo, creado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS google_uso (
        fecha DATE NOT NULL,
        sku VARCHAR(40) NOT NULL,
        llamadas INT NOT NULL DEFAULT 0,
        PRIMARY KEY (fecha, sku)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
