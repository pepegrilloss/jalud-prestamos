-- ========================================================================
-- Script: Agregar Métodos de Pago a la tabla pagos
-- Fecha: 2026-02-24
-- Descripción: Migración para soportar métodos de pago: Efectivo, Yape/Plin, Transferencia Bancaria
-- ========================================================================

-- Primero hacer backup de los datos existentes (opcional)
-- CREATE TABLE pago_backup AS SELECT * FROM pago;

-- 1. Actualizar la columna TipoPago para aceptar los nuevos valores
ALTER TABLE `pago` 
MODIFY COLUMN `TipoPago` VARCHAR(50) NOT NULL DEFAULT 'EFECTIVO'
COMMENT 'Método de pago: EFECTIVO, YAPE_PLIN, TRANSFERENCIA_BANCARIA';

-- 2. Agregar CHECK constraint para validar valores permitidos
-- Nota: Algunos sistemas pueden no soportar CHECK directamente, es solo para documentación
-- ALTER TABLE `pago` ADD CONSTRAINT chk_tipo_pago CHECK (TipoPago IN ('EFECTIVO', 'YAPE_PLIN', 'TRANSFERENCIA_BANCARIA'));

-- 3. Crear índice en TipoPago para mejorar búsquedas por método de pago
ALTER TABLE `pago` ADD INDEX `idx_tipo_pago` (`TipoPago`);

-- 4. Actualizar valores existentes (si la columna tiene otros valores, limpiarlos)
UPDATE `pago` SET `TipoPago` = 'EFECTIVO' WHERE `TipoPago` IS NULL OR `TipoPago` = '';

-- 5. RECOMENDACIÓN: Mantener EsMora y EsPagoAMayor para compatibilidad con datos históricos
-- Pero ya no se usarán en la nueva interfaz de Filament
-- Si en el futuro deseas eliminarlas completamente, descomenta las siguientes líneas:
-- ALTER TABLE `pago` DROP COLUMN `EsMora`;
-- ALTER TABLE `pago` DROP COLUMN `EsPagoAMayor`;

-- 6. Verificar la estructura actualizada
-- SHOW COLUMNS FROM `pago`;

-- 7. Verificar que los datos sean válidos
SELECT COUNT(*) as total, TipoPago, COUNT(*) as cantidad 
FROM `pago` 
GROUP BY TipoPago
ORDER BY cantidad DESC;

-- ========================================================================
-- INFORMACIÓN DE REFERENCIA
-- ========================================================================
-- Métodos de pago disponibles:
-- - EFECTIVO: Pago en efectivo
-- - YAPE_PLIN: Pago a través de Yape o Plin
-- - TRANSFERENCIA_BANCARIA: Pago por transferencia bancaria
-- ========================================================================
