-- ========================================================================
-- Script: Agregar Columna "EsPagoInicial" a tabla Pago
-- Fecha: 24 de Febrero, 2026
-- Propósito: Marcar si un pago se realiza el mismo día que se genera el crédito
-- ========================================================================

-- 1. Agregar columna EsPagoInicial
ALTER TABLE `pago` 
ADD COLUMN `EsPagoInicial` TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'Indica si el pago se realizó el mismo día de generación del crédito'
AFTER `EsPagoAMayor`;

-- 2. Crear índice para búsquedas rápidas
ALTER TABLE `pago` 
ADD INDEX `idx_es_pago_inicial` (`EsPagoInicial`);

-- 3. Crear índice combinado para queries frecuentes
ALTER TABLE `pago` 
ADD INDEX `idx_credito_es_inicial` (`CreditoID`, `EsPagoInicial`);

-- 4. Verificar la estructura actualizada
DESCRIBE `pago`;

-- 5. Verificar si hay registros con EsPagoInicial = 1
SELECT COUNT(*) as pagos_iniciales 
FROM `pago` 
WHERE EsPagoInicial = 1;

-- ========================================================================
-- INFORMACIÓN DE REFERENCIA
-- ========================================================================
-- Columna: EsPagoInicial
-- Tipo: TINYINT(1) - Boolean
-- Valores:
--   0 = Pago normal (no es inicial)
--   1 = Pago inicial (realizado el mismo día que FechaGeneracion del crédito)
-- ========================================================================
