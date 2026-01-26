-- Agregar columna EsPagoAutomatico a la tabla pago
-- Esta columna marca si el pago fue generado automáticamente por un refinanciamiento
ALTER TABLE `pago` ADD COLUMN `EsPagoAutomatico` TINYINT(1) NOT NULL DEFAULT 0 AFTER `EsPagoForzado`;

-- Crear índice para consultas rápidas
ALTER TABLE `pago` ADD INDEX `idx_es_pago_automatico` (`EsPagoAutomatico`);
