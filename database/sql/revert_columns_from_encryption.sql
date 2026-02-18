-- ============================================================================
-- SCRIPT SQL PARA REVERTIR CAMBIOS (EN CASO DE NECESARIO)
-- ============================================================================
-- CUIDADO: Este script revierte los cambios de LONGTEXT a VARCHAR
-- SOLO usar si algo salió mal y necesitas restaurar la BD
-- ============================================================================

-- ============================================================================
-- 1. REVERTIR TABLA Cliente
-- ============================================================================

ALTER TABLE `Cliente` 
MODIFY COLUMN `DNI` VARCHAR(20) NOT NULL;

ALTER TABLE `Cliente` 
MODIFY COLUMN `NombresApellidos` VARCHAR(200) NOT NULL;

ALTER TABLE `Cliente` 
MODIFY COLUMN `ConyugeDNI` VARCHAR(20) DEFAULT NULL;

ALTER TABLE `Cliente` 
MODIFY COLUMN `ConyugeNombresApellidos` VARCHAR(200) DEFAULT NULL;

ALTER TABLE `Cliente` 
MODIFY COLUMN `Domicilio` VARCHAR(500) DEFAULT NULL;

-- ============================================================================
-- 2. REVERTIR TABLA TelefonoNegocio
-- ============================================================================

ALTER TABLE `TelefonoNegocio` 
MODIFY COLUMN `Telefono` VARCHAR(20) NOT NULL;

-- ============================================================================
-- 3. REVERTIR TABLA DocumentoCliente
-- ============================================================================

ALTER TABLE `DocumentoCliente` 
MODIFY COLUMN `NombreOriginal` VARCHAR(255) NOT NULL;

-- ============================================================================
-- FIN - Las columnas han sido revertidas a sus tipos originales
-- ============================================================================
