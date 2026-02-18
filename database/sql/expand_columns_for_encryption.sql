-- ============================================================================
-- SCRIPT SQL PARA EXPANDIR COLUMNAS - DATOS ENCRIPTADOS
-- ============================================================================
-- Fecha: 13 de Febrero de 2026
-- Propósito: Expandir columnas VARCHAR a LONGTEXT para almacenar datos encriptados
-- BD: jvcso1ub_jalud_prestamos
-- ============================================================================

-- IMPORTANTE: Hacer BACKUP antes de ejecutar este script
-- Tiempo estimado: 1-2 minutos (depende del tamaño de la BD)

-- ============================================================================
-- 1. EXPANDIR TABLA Cliente
-- ============================================================================

-- Cambiar DNI de VARCHAR(20) a LONGTEXT
ALTER TABLE `Cliente` 
MODIFY COLUMN `DNI` LONGTEXT NOT NULL;

-- Cambiar NombresApellidos de VARCHAR(200) a LONGTEXT
ALTER TABLE `Cliente` 
MODIFY COLUMN `NombresApellidos` LONGTEXT NOT NULL;

-- Cambiar ConyugeDNI de VARCHAR(20) a LONGTEXT
ALTER TABLE `Cliente` 
MODIFY COLUMN `ConyugeDNI` LONGTEXT DEFAULT NULL;

-- Cambiar ConyugeNombresApellidos de VARCHAR(200) a LONGTEXT
ALTER TABLE `Cliente` 
MODIFY COLUMN `ConyugeNombresApellidos` LONGTEXT DEFAULT NULL;

-- Cambiar Domicilio de VARCHAR(500) a LONGTEXT
ALTER TABLE `Cliente` 
MODIFY COLUMN `Domicilio` LONGTEXT DEFAULT NULL;

-- ============================================================================
-- 2. EXPANDIR TABLA TelefonoNegocio
-- ============================================================================

-- Cambiar Telefono de VARCHAR(20) a LONGTEXT
ALTER TABLE `TelefonoNegocio` 
MODIFY COLUMN `Telefono` LONGTEXT NOT NULL;

-- ============================================================================
-- 3. EXPANDIR TABLA DocumentoCliente
-- ============================================================================

-- Cambiar NombreOriginal de VARCHAR(255) a LONGTEXT
ALTER TABLE `DocumentoCliente` 
MODIFY COLUMN `NombreOriginal` LONGTEXT NOT NULL;

-- ============================================================================
-- VERIFICACIÓN: Consultar estructura de tablas
-- ============================================================================

-- Verificar cambios en Cliente
DESCRIBE `Cliente`;

-- Verificar cambios en TelefonoNegocio
DESCRIBE `TelefonoNegocio`;

-- Verificar cambios en DocumentoCliente
DESCRIBE `DocumentoCliente`;

-- ============================================================================
-- FIN DEL SCRIPT
-- ============================================================================
-- Próximo paso: Ejecutar el comando de encriptación desde artisan:
-- php artisan security:encrypt-sensitive-data
-- ============================================================================
