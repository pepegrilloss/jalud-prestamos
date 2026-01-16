-- =====================================================
-- Script de Migración: Agregar soporte para Refinanciamiento
-- Tabla: ProposicionCredito
-- =====================================================

ALTER TABLE `ProposicionCredito`
ADD COLUMN `EsRefinanciamiento` TINYINT(1) NOT NULL DEFAULT 0 AFTER `Activo`,
ADD COLUMN `ProposicionCreditoAnteriorID` INT(11) NULL DEFAULT NULL AFTER `EsRefinanciamiento`,
ADD COLUMN `MontoTotalPagar` DECIMAL(12,2) NULL DEFAULT 0.00 AFTER `ProposicionCreditoAnteriorID`;

-- Agregar constraint de llave foránea
ALTER TABLE `ProposicionCredito`
ADD CONSTRAINT `FK_ProposicionCredito_Anterior` 
FOREIGN KEY (`ProposicionCreditoAnteriorID`) 
REFERENCES `ProposicionCredito`(`ProposicionCreditoID`) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- Crear índice para optimizar búsquedas de refinanciamiento
ALTER TABLE `ProposicionCredito`
ADD INDEX `IDX_EsRefinanciamiento` (`EsRefinanciamiento`),
ADD INDEX `IDX_ProposicionCreditoAnteriorID` (`ProposicionCreditoAnteriorID`);

-- Verificar que las columnas fueron agregadas correctamente
SHOW COLUMNS FROM `ProposicionCredito` WHERE Field IN ('EsRefinanciamiento', 'ProposicionCreditoAnteriorID', 'MontoTotalPagar');
