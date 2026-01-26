-- ================================================================
-- SCRIPT DE VALIDACIÓN - INYECCIÓN AUTOMÁTICA DE FECHA
-- ================================================================
-- Este script verifica que la fecha abierta se está inyectando
-- correctamente en todos los registros creados desde Filament

-- IMPORTANTE: Ejecutar después de crear registros de prueba

-- ================================================================
-- 1. VERIFICAR QUE HAY UN DÍA ABIERTO
-- ================================================================
SELECT 
    'ESTADO DEL DÍA ABIERTO' as 'Validación',
    AperturaCierreDiaID,
    Fecha,
    EstadoDia,
    FechaApertura,
    UsuarioApertura.name as 'Usuario Apertura'
FROM apertura_cierre_dia
LEFT JOIN users as UsuarioApertura ON apertura_cierre_dia.UsuarioAperturaID = UsuarioApertura.id
WHERE EstadoDia = 'ABIERTO'
ORDER BY Fecha DESC
LIMIT 1;

-- ================================================================
-- 2. VALIDAR CLIENTES CREADOS HOY
-- ================================================================
SELECT 
    'CLIENTES' as 'Modelo',
    COUNT(*) as 'Total Creados',
    DATE(FechaRegistro) as 'Fecha Registro'
FROM cliente
WHERE DATE(FechaRegistro) = CURDATE()
GROUP BY DATE(FechaRegistro);

-- Mostrar detalle
SELECT 
    ClienteID,
    NombresApellidos,
    DNI,
    FechaRegistro,
    UsuarioRegistro
FROM cliente
WHERE DATE(FechaRegistro) = CURDATE()
ORDER BY FechaRegistro DESC
LIMIT 5;

-- ================================================================
-- 3. VALIDAR PAGOS CREADOS HOY
-- ================================================================
SELECT 
    'PAGOS' as 'Modelo',
    COUNT(*) as 'Total Creados',
    DATE(FechaCreacion) as 'Fecha Creación'
FROM pago
WHERE DATE(FechaCreacion) = CURDATE()
GROUP BY DATE(FechaCreacion);

-- Mostrar detalle
SELECT 
    PagoID,
    MontoPagado,
    FechaCreacion,
    Activo
FROM pago
WHERE DATE(FechaCreacion) = CURDATE()
ORDER BY FechaCreacion DESC
LIMIT 5;

-- ================================================================
-- 4. VALIDAR PROPOSICIONES CREADAS HOY
-- ================================================================
SELECT 
    'PROPOSICIONES' as 'Modelo',
    COUNT(*) as 'Total Creadas',
    DATE(FechaPropuesta) as 'Fecha Propuesta'
FROM proposicioncredito
WHERE DATE(FechaPropuesta) = CURDATE()
GROUP BY DATE(FechaPropuesta);

-- Mostrar detalle
SELECT 
    ProposicionCreditoID,
    CodigoCredito,
    FechaPropuesta,
    Estado,
    Activo
FROM proposicioncredito
WHERE DATE(FechaPropuesta) = CURDATE()
ORDER BY FechaPropuesta DESC
LIMIT 5;

-- ================================================================
-- 5. VALIDAR CRÉDITOS CREADOS HOY
-- ================================================================
SELECT 
    'CRÉDITOS' as 'Modelo',
    COUNT(*) as 'Total Creados',
    DATE(FechaGeneracion) as 'Fecha Generación'
FROM credito
WHERE DATE(FechaGeneracion) = CURDATE()
GROUP BY DATE(FechaGeneracion);

-- Mostrar detalle
SELECT 
    CreditoID,
    ProposicionCreditoID,
    FechaGeneracion,
    Activo
FROM credito
WHERE DATE(FechaGeneracion) = CURDATE()
ORDER BY FechaGeneracion DESC
LIMIT 5;

-- ================================================================
-- 6. VALIDAR ZONAS CREADAS HOY
-- ================================================================
SELECT 
    'ZONAS' as 'Modelo',
    COUNT(*) as 'Total Creadas',
    DATE(FechaCreacion) as 'Fecha Creación'
FROM zona
WHERE DATE(FechaCreacion) = CURDATE()
GROUP BY DATE(FechaCreacion);

-- Mostrar detalle
SELECT 
    ZonaID,
    Nombre,
    FechaCreacion,
    Activo
FROM zona
WHERE DATE(FechaCreacion) = CURDATE()
ORDER BY FechaCreacion DESC
LIMIT 5;

-- ================================================================
-- 7. VALIDAR OTROS MODELOS
-- ================================================================

-- Giros
SELECT 'GIROS' as 'Modelo', COUNT(*) as 'Total', DATE(FechaCreacion) as 'Fecha'
FROM giro WHERE DATE(FechaCreacion) = CURDATE() GROUP BY DATE(FechaCreacion);

-- Tipos de Crédito
SELECT 'TIPOS CRÉDITO' as 'Modelo', COUNT(*) as 'Total', DATE(FechaCreacion) as 'Fecha'
FROM tipokredito WHERE DATE(FechaCreacion) = CURDATE() GROUP BY DATE(FechaCreacion);

-- Tipos de Pago
SELECT 'TIPOS PAGO' as 'Modelo', COUNT(*) as 'Total', DATE(FechaCreacion) as 'Fecha'
FROM tipopago WHERE DATE(FechaCreacion) = CURDATE() GROUP BY DATE(FechaCreacion);

-- ================================================================
-- 8. RESUMEN GENERAL
-- ================================================================
SELECT 
    'RESUMEN DEL DÍA' as 'Reporte',
    CONCAT(
        'Clientes: ', (SELECT COUNT(*) FROM cliente WHERE DATE(FechaRegistro) = CURDATE()), ' | ',
        'Pagos: ', (SELECT COUNT(*) FROM pago WHERE DATE(FechaCreacion) = CURDATE()), ' | ',
        'Proposiciones: ', (SELECT COUNT(*) FROM proposicioncredito WHERE DATE(FechaPropuesta) = CURDATE()), ' | ',
        'Créditos: ', (SELECT COUNT(*) FROM credito WHERE DATE(FechaGeneracion) = CURDATE()), ' | ',
        'Zonas: ', (SELECT COUNT(*) FROM zona WHERE DATE(FechaCreacion) = CURDATE())
    ) as 'Totales';

-- ================================================================
-- 9. VERIFICAR CONSISTENCIA
-- ================================================================
-- Todos los registros del día abierto deben tener la misma fecha
SELECT 
    'CONSISTENCIA' as 'Validación',
    DATE(apertura_cierre_dia.Fecha) as 'Fecha Abierta',
    DATE(cliente.FechaRegistro) as 'Fecha Cliente',
    DATE(pago.FechaCreacion) as 'Fecha Pago',
    DATE(proposicioncredito.FechaPropuesta) as 'Fecha Proposición',
    DATE(credito.FechaGeneracion) as 'Fecha Crédito'
FROM apertura_cierre_dia
LEFT JOIN cliente ON DATE(cliente.FechaRegistro) = apertura_cierre_dia.Fecha
LEFT JOIN pago ON DATE(pago.FechaCreacion) = apertura_cierre_dia.Fecha
LEFT JOIN proposicioncredito ON DATE(proposicioncredito.FechaPropuesta) = apertura_cierre_dia.Fecha
LEFT JOIN credito ON DATE(credito.FechaGeneracion) = apertura_cierre_dia.Fecha
WHERE apertura_cierre_dia.EstadoDia = 'ABIERTO'
LIMIT 1;

-- ================================================================
-- INTERPRETACIÓN DE RESULTADOS
-- ================================================================
-- ✅ ÉXITO: Si todas las fechas coinciden con la fecha abierta
-- ❌ FALLO: Si hay NULLs o fechas diferentes
-- 
-- En caso de éxito, la inyección automática está funcionando
-- correctamente en todos los recursos Filament
-- ================================================================
