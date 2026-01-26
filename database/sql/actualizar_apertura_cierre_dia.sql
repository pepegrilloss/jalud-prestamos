-- ========================================================
-- SQL PARA EJECUTAR EN phpMyAdmin
-- ========================================================
-- Este script cierra todos los días abiertos excepto el primero
-- y agrega un constraint para asegurar que solo haya 1 día ABIERTO
-- ========================================================

-- 1. Primero, cerrar todos los días abiertos excepto el primero
UPDATE apertura_cierre_dia 
SET EstadoDia = 'CERRADO', 
    FechaCierre = NOW(),
    UsuarioCierreID = 1
WHERE EstadoDia = 'ABIERTO' 
AND AperturaCierreDiaID != (
    SELECT MIN(AperturaCierreDiaID) 
    FROM apertura_cierre_dia 
    WHERE EstadoDia = 'ABIERTO'
);

-- 2. Verificar que solo hay 1 día abierto
SELECT COUNT(*) as dias_abiertos FROM apertura_cierre_dia WHERE EstadoDia = 'ABIERTO';

-- 3. Agregar columna auxiliar para el constraint único
ALTER TABLE apertura_cierre_dia ADD COLUMN abierto_flag INT GENERATED ALWAYS AS (
    CASE WHEN EstadoDia = 'ABIERTO' THEN 1 ELSE NULL END
) STORED;

-- 4. Crear índice único en la columna auxiliar
-- Esto asegura que solo haya UN registro con abierto_flag = 1 (es decir, UN día ABIERTO)
ALTER TABLE apertura_cierre_dia ADD UNIQUE KEY unique_abierto (abierto_flag);

-- ========================================================
-- RESULTADO: 
-- - Solo hay 1 día ABIERTO
-- - No se puede abrir otro día mientras uno esté ABIERTO
-- - Si intentas, MySQL lanzará error de UNIQUE constraint
-- ========================================================

-- Para verificar que todo está bien, ejecuta esto:
SELECT AperturaCierreDiaID, Fecha, EstadoDia, abierto_flag 
FROM apertura_cierre_dia 
ORDER BY Fecha DESC;
