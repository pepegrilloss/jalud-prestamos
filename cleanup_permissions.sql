-- ============================================
-- EJECUTAR EN LA BD DEL SERVIDOR
-- Elimina permisos personalizados innecesarios
-- Conserva: Abrir Dia Apertura, Cerrar Dia Apertura, Ver Todas Las Sedes
-- ============================================

-- 1. Primero desvincular los permisos de los roles
DELETE FROM role_has_permissions
WHERE permission_id IN (
    SELECT id FROM permissions
    WHERE name IN (
        'Create Crear::proposicion::credito',
        'Delete Crear::proposicion::credito',
        'Update Crear::proposicion::credito',
        'View Any Crear::proposicion::credito',
        'View Crear::proposicion::credito',
        'Widget Apertura Cierre Dia Widget',
        'Widget Cliente Proposicion Stats',
        'Widget Cobranza Stats',
        'Widget Exoneraciones Pendientes Widget',
        'Widget Exoneraciones Stats Widget',
        'Widget Pagos Stats',
        'Widget Proposiciones Stats'
    )
);

-- 2. Luego eliminar los permisos
DELETE FROM permissions
WHERE name IN (
    'Create Crear::proposicion::credito',
    'Delete Crear::proposicion::credito',
    'Update Crear::proposicion::credito',
    'View Any Crear::proposicion::credito',
    'View Crear::proposicion::credito',
    'Widget Apertura Cierre Dia Widget',
    'Widget Cliente Proposicion Stats',
    'Widget Cobranza Stats',
    'Widget Exoneraciones Pendientes Widget',
    'Widget Exoneraciones Stats Widget',
    'Widget Pagos Stats',
    'Widget Proposiciones Stats'
);

-- 3. Verificar que solo quedan los 3 correctos
SELECT * FROM permissions
WHERE name IN ('Abrir Dia Apertura', 'Cerrar Dia Apertura', 'Ver Todas Las Sedes');
