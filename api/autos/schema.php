<?php
declare(strict_types=1);

// Tablas del módulo Autos (se crean solas la primera vez)
function autos_crear_tablas(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS autos_cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lead_id INT NULL,
        slug VARCHAR(80) NOT NULL,
        nombre VARCHAR(120) NOT NULL,
        iniciales VARCHAR(4) NOT NULL DEFAULT '',
        direccion VARCHAR(200) NOT NULL DEFAULT '',
        whatsapp VARCHAR(20) NOT NULL DEFAULT '',
        color VARCHAR(7) NOT NULL DEFAULT '#1d4ed8',
        tasa_anual DECIMAL(5,2) NOT NULL DEFAULT 14,
        plazos VARCHAR(60) NOT NULL DEFAULT '12,24,36,48',
        entrega_min_pct TINYINT NOT NULL DEFAULT 30,
        estado ENUM('demo','activa','pausada') NOT NULL DEFAULT 'demo',
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slug (slug),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS autos_vehiculos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT NOT NULL,
        ref VARCHAR(10) NOT NULL,
        marca VARCHAR(80) NOT NULL DEFAULT '',
        modelo VARCHAR(80) NOT NULL DEFAULT '',
        version VARCHAR(80) NOT NULL DEFAULT '',
        anio SMALLINT NOT NULL DEFAULT 0,
        km INT NOT NULL DEFAULT 0,
        precio INT NOT NULL DEFAULT 0,
        moneda ENUM('USD','UYU') NOT NULL DEFAULT 'USD',
        combustible VARCHAR(20) NOT NULL DEFAULT '',
        caja VARCHAR(20) NOT NULL DEFAULT '',
        tipo ENUM('sedan','hatch','suv','pickup','otro') NOT NULL DEFAULT 'otro',
        color VARCHAR(30) NOT NULL DEFAULT '',
        descripcion TEXT NULL,
        estado ENUM('disponible','reservado','vendido') NOT NULL DEFAULT 'disponible',
        destacado TINYINT NOT NULL DEFAULT 0,
        ingreso_at DATE NOT NULL DEFAULT (CURRENT_DATE),
        vendido_at DATETIME NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cuenta_ref (cuenta_id, ref),
        KEY idx_cuenta_estado (cuenta_id, estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS autos_fotos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vehiculo_id INT NOT NULL,
        orden SMALLINT NOT NULL DEFAULT 0,
        archivo VARCHAR(40) NOT NULL,
        ext VARCHAR(4) NOT NULL,
        ancho SMALLINT NOT NULL DEFAULT 0,
        alto SMALLINT NOT NULL DEFAULT 0,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_vehiculo_orden (vehiculo_id, orden)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS autos_eventos (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT NOT NULL,
        vehiculo_id INT NULL,
        tipo ENUM('visita_catalogo','visita_auto','click_whatsapp','copiar_link') NOT NULL,
        visitante CHAR(16) NOT NULL,
        creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_cuenta_creado (cuenta_id, creado),
        KEY idx_vehiculo_tipo (vehiculo_id, tipo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
