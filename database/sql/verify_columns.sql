-- ============================================================================
-- SCRIPT DE VERIFICACIÓN - Estado de las columnas
-- ============================================================================

-- ============================================================================
-- ANTES: Verificar tamaño actual de las columnas
-- ============================================================================

-- Script para copiar y ejecutar ANTES de aplicar los cambios
-- Esto te mostrará los tipos de datos actuales

SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'jvcso1ub_jalud_prestamos'
AND TABLE_NAME IN ('Cliente', 'TelefonoNegocio', 'DocumentoCliente')
AND COLUMN_NAME IN (
    'DNI', 'NombresApellidos', 'ConyugeDNI', 'ConyugeNombresApellidos', 
    'Domicilio', 'Telefono', 'NombreOriginal'
)
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================================================
-- CONTAR REGISTROS (para evitar pérdida de datos)
-- ============================================================================

-- Contar clientes
SELECT COUNT(*) as 'Total Clientes' FROM Cliente;

-- Contar teléfonos
SELECT COUNT(*) as 'Total Teléfonos' FROM TelefonoNegocio;

-- Contar documentos
SELECT COUNT(*) as 'Total Documentos' FROM DocumentoCliente;

-- ============================================================================
-- VERIFICAR LONGITUD MÁXIMA DE DATOS ACTUALES
-- ============================================================================

-- Longitud máxima actual de DNI
SELECT MAX(CHAR_LENGTH(DNI)) as 'Max DNI Length' FROM Cliente;

-- Longitud máxima actual de NombresApellidos
SELECT MAX(CHAR_LENGTH(NombresApellidos)) as 'Max NombresApellidos Length' FROM Cliente;

-- Longitud máxima actual de Domicilio
SELECT MAX(CHAR_LENGTH(Domicilio)) as 'Max Domicilio Length' FROM Cliente;

-- Longitud máxima actual de Telefono
SELECT MAX(CHAR_LENGTH(Telefono)) as 'Max Telefono Length' FROM TelefonoNegocio;

-- ============================================================================
-- DESPUÉS: Ejecutar este query DESPUÉS de aplicar los cambios
-- ============================================================================

-- Verifica que los cambios se aplicaron correctamente
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'jvcso1ub_jalud_prestamos'
AND TABLE_NAME IN ('Cliente', 'TelefonoNegocio', 'DocumentoCliente')
AND COLUMN_NAME IN (
    'DNI', 'NombresApellidos', 'ConyugeDNI', 'ConyugeNombresApellidos', 
    'Domicilio', 'Telefono', 'NombreOriginal'
)
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================================================
