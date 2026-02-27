-- ========================================================================
-- SCRIPT SQL: Desencriptar Teléfonos y Revertir Columna
-- Fecha: 24 de Febrero, 2026
-- Propósito: Remover encriptación de teléfonos en TelefonoNegocio
-- ========================================================================

-- IMPORTANTE: HACER BACKUP ANTES DE EJECUTAR
-- Tiempo estimado: 1-2 minutos

-- ========================================================================
-- 1. ANALIZAR DATOS ACTUALES
-- ========================================================================

-- Ver estado actual de los teléfonos
SELECT 
    TelefonoNegocioID,
    Telefono,
    LENGTH(Telefono) as longitud,
    CASE 
        WHEN Telefono REGEXP '^[0-9]+$' THEN 'NÚMERO_LIMPIO'
        WHEN Telefono LIKE '{%' THEN 'JSON_ENCRIPTADO'
        WHEN Telefono LIKE 'eyJ%' THEN 'BASE64_ENCRIPTADO'
        ELSE 'OTRO'
    END as tipo
FROM TelefonoNegocio
LIMIT 10;

-- ========================================================================
-- 2. LIMPIAR DATOS ENCRIPTADOS
-- ========================================================================

-- Crear tabla temporal con los datos limpios
CREATE TEMPORARY TABLE telefonos_limpios AS
SELECT 
    TelefonoNegocioID,
    CASE 
        -- Si ya es un número limpio (solo dígitos)
        WHEN Telefono REGEXP '^[0-9]+$' THEN SUBSTRING(Telefono, 1, 20)
        -- Si es JSON encriptado, intentar extraer algo
        WHEN Telefono LIKE '{%' THEN SUBSTRING(REGEXP_SUBSTR(Telefono, '[0-9]+'), 1, 20)
        -- Si es Base64, intentar extraer números
        WHEN Telefono LIKE 'eyJ%' THEN SUBSTRING(REGEXP_SUBSTR(Telefono, '[0-9]{6,}'), 1, 20)
        -- Fallback: extraer todos los números
        ELSE SUBSTRING(REGEXP_SUBSTR(Telefono, '[0-9]+'), 1, 20)
    END as telefono_limpio
FROM TelefonoNegocio;

-- Verificar los datos limpios antes de aplicar
SELECT 
    TelefonoNegocioID,
    telefono_limpio,
    LENGTH(telefono_limpio) as longitud
FROM telefonos_limpios
WHERE telefono_limpio IS NULL OR telefono_limpio = ''
ORDER BY TelefonoNegocioID;

-- ========================================================================
-- 3. APLICAR CAMBIOS (DESCOMENTAR SI LOS DATOS SE VEN CORRECTOS)
-- ========================================================================

-- Primero, hacer UPDATE con los teléfonos limpios
UPDATE TelefonoNegocio t
INNER JOIN telefonos_limpios tl ON t.TelefonoNegocioID = tl.TelefonoNegocioID
SET t.Telefono = COALESCE(tl.telefono_limpio, '000000');

-- Revertir la estructura de la columna de LONGTEXT a VARCHAR(20)
ALTER TABLE `TelefonoNegocio` 
MODIFY COLUMN `Telefono` VARCHAR(20) NOT NULL;

-- Crear índice si no existe
ALTER TABLE `TelefonoNegocio` ADD INDEX `idx_telefono` (`Telefono`);

-- ========================================================================
-- 4. VERIFICACIÓN POST-CAMBIOS
-- ========================================================================

-- Ver estructura actualizada
DESCRIBE `TelefonoNegocio`;

-- Ver registros actualizados
SELECT 
    TelefonoNegocioID,
    NegocioID,
    Telefono,
    TipoTelefono,
    Activo
FROM TelefonoNegocio
LIMIT 20;

-- Contar registros por tipo
SELECT 
    TipoTelefono,
    COUNT(*) as cantidad
FROM TelefonoNegocio
GROUP BY TipoTelefono;

-- Verificar si hay registros con teléfono vacío
SELECT COUNT(*) as registros_vacios
FROM TelefonoNegocio
WHERE Telefono IS NULL OR Telefono = '' OR Telefono = '000000';

-- ========================================================================
-- IMPORTANTES:
-- ========================================================================
-- 1. Este script asume que los números están dentro de los datos encriptados
-- 2. Si hay muchos registros vacíos (000000), significa que no se pudieron extraer números
-- 3. Si es así, necesita intervención manual para recuperar los datos originales
-- 4. La tabla temporal se elimina automáticamente al cerrar la sesión
-- ========================================================================

-- DROP TEMPORARY TABLE IF EXISTS telefonos_limpios;
