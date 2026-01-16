-- =====================================================
-- Script: Agregar columna FueRefinanciada
-- Tabla: ProposicionCredito
-- =====================================================

ALTER TABLE `ProposicionCredito`
ADD COLUMN `FueRefinanciada` TINYINT(1) NOT NULL DEFAULT 0 AFTER `EsRefinanciamiento`;

-- Crear índice
ALTER TABLE `ProposicionCredito`
ADD INDEX `IDX_FueRefinanciada` (`FueRefinanciada`);

-- Verificar
SHOW COLUMNS FROM `ProposicionCredito` WHERE Field = 'FueRefinanciada';
