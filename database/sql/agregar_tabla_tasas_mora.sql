-- Crear tabla TasaMora
CREATE TABLE `TasaMora` (
  `TasaMoraID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Nombre` VARCHAR(100) NOT NULL UNIQUE,
  `Porcentaje` DECIMAL(5,2) NOT NULL COMMENT 'Porcentaje de mora (ej: 0.5, 1.0, 2.5)',
  `Descripcion` VARCHAR(500),
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  KEY `IDX_Activo` (`Activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tasas de mora por defecto
INSERT INTO `TasaMora` (`Nombre`, `Porcentaje`, `Descripcion`) VALUES
('Mora 0.5%', 0.50, 'Tasa de mora 0.5% diario'),
('Mora 0.8%', 0.80, 'Tasa de mora 0.8% diario'),
('Mora 1.0%', 1.00, 'Tasa de mora 1.0% diario'),
('Mora 1.5%', 1.50, 'Tasa de mora 1.5% diario'),
('Mora 2.0%', 2.00, 'Tasa de mora 2.0% diario'),
('Mora 2.5%', 2.50, 'Tasa de mora 2.5% diario');
