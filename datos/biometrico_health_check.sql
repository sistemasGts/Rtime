-- Tabla: Estado actual de cada biométrico
-- Actualización: Se sobrescribe con el mayor reciente
CREATE TABLE IF NOT EXISTS `biometrico_conexion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `biometrico_id` INT NOT NULL UNIQUE,
    `estado` ENUM('conectado', 'desconectado', 'lento', 'error') NOT NULL DEFAULT 'desconectado',
    `latencia_ms` INT DEFAULT NULL COMMENT 'Latencia en ms del último chequeo',
    `fecha_check` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `observacion` VARCHAR(500) DEFAULT NULL,
    `intentos_fallidos` INT DEFAULT 0,
    `ultima_conexion_exitosa` DATETIME DEFAULT NULL,
    KEY `idx_estado` (`estado`),
    KEY `idx_fecha` (`fecha_check`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: Historial completo de cambios
-- Inmutable: se agrega nuevo registro cada vez que cambia el estado
CREATE TABLE IF NOT EXISTS `biometrico_conexion_historial` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `biometrico_id` INT DEFAULT 0 COMMENT '0 = evento del puente/bridge',
    `estado_anterior` ENUM('conectado', 'desconectado', 'lento', 'error') DEFAULT NULL,
    `estado_nuevo` ENUM('conectado', 'desconectado', 'lento', 'error') NOT NULL,
    `latencia_ms` INT DEFAULT NULL,
    `fecha_cambio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `observacion` VARCHAR(500) DEFAULT NULL,
    KEY `idx_biometrico_id` (`biometrico_id`),
    KEY `idx_fecha` (`fecha_cambio`),
    KEY `idx_estado_nuevo` (`estado_nuevo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: Monitoreo del BioBridge (puente)
-- Registra cada verificación del puente
CREATE TABLE IF NOT EXISTS `biometrico_puente_conexion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha_check` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `estado` ENUM('activo', 'inactivo') NOT NULL,
    `latencia_ms` INT DEFAULT NULL,
    `observacion` VARCHAR(500) DEFAULT NULL,
    KEY `idx_fecha` (`fecha_check`),
    KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
