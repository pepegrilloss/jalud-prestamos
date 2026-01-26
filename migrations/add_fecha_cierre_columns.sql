-- Script para agregar control individual de cierre de día por registro
-- Esto permite cerrar el día globalmente pero abrir registros individuales si es necesario

-- Agregar FechaCierre a tabla CLIENTE
ALTER TABLE `cliente` ADD COLUMN `FechaCierre` DATE NULL COMMENT 'Fecha en que se cerró este registro' AFTER `Activo`;

-- Agregar FechaCierre a tabla PROPOSICIONCREDITO
ALTER TABLE `proposicioncredito` ADD COLUMN `FechaCierre` DATE NULL COMMENT 'Fecha en que se cerró este registro' AFTER `Activo`;

-- Agregar FechaCierre a tabla CREDITO
ALTER TABLE `credito` ADD COLUMN `FechaCierre` DATE NULL COMMENT 'Fecha en que se cerró este registro' AFTER `Activo`;

-- Agregar FechaCierre a tabla PAGO
ALTER TABLE `pago` ADD COLUMN `FechaCierre` DATE NULL COMMENT 'Fecha en que se cerró este registro' AFTER `Activo`;

-- Agregar FechaCierre a tabla CUOTA
ALTER TABLE `cuota` ADD COLUMN `FechaCierre` DATE NULL COMMENT 'Fecha en que se cerró este registro' AFTER `Activo`;

-- Agregar índices para optimizar búsquedas
ALTER TABLE `cliente` ADD INDEX `IDX_FechaCierre` (`FechaCierre`);
ALTER TABLE `proposicioncredito` ADD INDEX `IDX_FechaCierre` (`FechaCierre`);
ALTER TABLE `credito` ADD INDEX `IDX_FechaCierre` (`FechaCierre`);
ALTER TABLE `pago` ADD INDEX `IDX_FechaCierre` (`FechaCierre`);
ALTER TABLE `cuota` ADD INDEX `IDX_FechaCierre` (`FechaCierre`);

-- Verificación (ejecutar después para confirmar):
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME IN ('cliente', 'proposicioncredito', 'credito', 'pago', 'cuota') 
-- AND COLUMN_NAME = 'FechaCierre';
