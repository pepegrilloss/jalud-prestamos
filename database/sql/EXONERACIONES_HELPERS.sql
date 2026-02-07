-- ====================================================================
-- HELPERS: Consultas y Procedimientos para el Módulo de Exoneraciones
-- ====================================================================

-- ====================================================================
-- 1. CONSULTAS PARA CALCULAR MONTOS DISPONIBLES
-- ====================================================================

-- Consulta: Obtener información completa de un crédito para exonerar
-- Devuelve: Montos de interés, mora acumulada, saldo pendiente
SELECT 
    c.CreditoID,
    c.ProposicionCreditoID,
    p.CodigoCredito,
    cl.ClienteID,
    cl.DNI,
    cl.NombresApellidos,
    pc.MontoTotal,
    pc.MontoInteres,
    pc.TasaMora,
    pc.SaldoPendiente,
    c.FechaInicio,
    c.FechaVencimiento,
    -- Suma de pagos realizados
    COALESCE(SUM(CASE WHEN pag.TipoConcepto = 'C' THEN pag.MontoPagado ELSE 0 END), 0) AS MontoPagadoCuotas,
    -- Mora acumulada (pagos realizados con EsMora = 1)
    COALESCE(SUM(CASE WHEN pag.EsMora = 1 THEN pag.MontoPagado ELSE 0 END), 0) AS MoraAcumulada,
    -- Intereses exonerados previamente
    COALESCE(SUM(CASE WHEN pag.TipoConcepto = 'I' THEN pag.MontoPagado ELSE 0 END), 0) AS InteresesExonerados,
    -- Mora exonerada previamente  
    COALESCE(SUM(CASE WHEN pag.TipoConcepto = 'M' THEN pag.MontoPagado ELSE 0 END), 0) AS MoraExonerada
FROM Credito c
INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
INNER JOIN Cliente cl ON pc.ClienteID = cl.ClienteID
LEFT JOIN pago pag ON c.CreditoID = pag.CreditoID
WHERE c.CreditoID = ? -- Reemplazar con ID del crédito
  AND c.Activo = 1
GROUP BY c.CreditoID;

-- ====================================================================
-- 2. CONSULTA: Validar elegibilidad para Pronto Pago
-- Requerimiento: Cliente debe estar al día (sin cuotas atrasadas)
-- ====================================================================

-- Validar si un cliente tiene cuotas atrasadas
SELECT 
    c.CreditoID,
    COUNT(cu.CuotaID) AS CuotasAtrasadas,
    CASE 
        WHEN COUNT(cu.CuotaID) = 0 THEN 'SI'
        ELSE 'NO'
    END AS EsElegibleProntoPago
FROM Credito c
INNER JOIN cuota cu ON c.CreditoID = cu.CreditoID
WHERE c.CreditoID = ? -- Reemplazar con ID del crédito
  AND c.Activo = 1
  AND cu.Estado = 'PENDIENTE'
  AND cu.DiasAtraso > 0
GROUP BY c.CreditoID;

-- ====================================================================
-- 3. CONSULTA: Listar créditos elegibles para exoneración
-- Solo créditos activos con saldo pendiente
-- ====================================================================

SELECT 
    c.CreditoID,
    c.ProposicionCreditoID,
    pc.CodigoCredito,
    cl.ClienteID,
    cl.NombresApellidos,
    pc.MontoTotal,
    pc.SaldoPendiente,
    c.FechaVencimiento,
    c.Activo
FROM Credito c
INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
INNER JOIN Cliente cl ON pc.ClienteID = cl.ClienteID
WHERE c.Activo = 1
  AND pc.SaldoPendiente > 0
ORDER BY pc.SaldoPendiente DESC;

-- ====================================================================
-- 4. CONSULTA: Obtener exoneraciones pendientes por aprobar
-- Agrupa por nivel de aprobación requerido
-- ====================================================================

SELECT 
    se.SolicitudExoneracionID,
    se.CreditoID,
    pc.CodigoCredito,
    cl.NombresApellidos,
    te.Nombre AS TipoExoneracion,
    te.Codigo,
    se.MontoExonerado,
    se.Estado,
    se.FechaSolicitud,
    na.Nombre AS NivelAprobacionRequerido,
    ae.Estado AS EstadoAprobacion,
    ae.AprobacionExoneracionID
FROM SolicitudExoneracion se
INNER JOIN Credito c ON se.CreditoID = c.CreditoID
INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
INNER JOIN Cliente cl ON pc.ClienteID = cl.ClienteID
INNER JOIN TipoExoneracion te ON se.TipoExoneracionID = te.TipoExoneracionID
LEFT JOIN NivelAprobacion na ON se.NivelAprobacionRequerido = na.NivelAprobacionID
LEFT JOIN AprobacionExoneracion ae ON se.SolicitudExoneracionID = ae.SolicitudExoneracionID
WHERE se.Estado = 'PENDIENTE'
  AND ae.Estado = 'PENDIENTE'
ORDER BY se.FechaSolicitud ASC;

-- ====================================================================
-- 5. CONSULTA: Cálculo de mora acumulada con días de atraso
-- Calcula la mora basada en DiasAtraso de cada cuota
-- ====================================================================

SELECT 
    c.CreditoID,
    pc.CodigoCredito,
    cl.NombresApellidos,
    pc.TasaMora,
    SUM(cu.MontoMora) AS MoraAcumuladaCalculada,
    SUM(cu.DiasAtraso) AS DiasTotalAtraso,
    MAX(cu.FechaVencimiento) AS UltimaFechaVencimiento
FROM Credito c
INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
INNER JOIN Cliente cl ON pc.ClienteID = cl.ClienteID
INNER JOIN cuota cu ON c.CreditoID = cu.CreditoID
WHERE c.CreditoID = ? -- Reemplazar con ID del crédito
  AND cu.DiasAtraso > 0
GROUP BY c.CreditoID;

-- ====================================================================
-- 6. PROCEDIMIENTO: Determinar nivel de aprobación según monto
-- Basado en el rango de MontoMinimo y MontoMaximo
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_ObtenerNivelAprobacionPorMonto`(
    IN p_MontoExonerado DECIMAL(12,2)
)
BEGIN
    SELECT 
        NivelAprobacionID,
        Nombre,
        MontoMinimo,
        MontoMaximo,
        Orden
    FROM NivelAprobacion
    WHERE p_MontoExonerado >= MontoMinimo 
      AND p_MontoExonerado <= MontoMaximo
      AND Activo = 1
    ORDER BY Orden ASC
    LIMIT 1;
END$$

DELIMITER ;

-- ====================================================================
-- 7. PROCEDIMIENTO: Validar monto disponible para exonerar (Intereses)
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_ValidarMontoInteresesDisponibles`(
    IN p_CreditoID INT,
    OUT p_MontoDisponible DECIMAL(12,2),
    OUT p_Valido TINYINT
)
BEGIN
    DECLARE v_MontoInteres DECIMAL(12,2);
    DECLARE v_InteresesExonerados DECIMAL(12,2);
    
    -- Obtener monto de intereses del crédito
    SELECT pc.MontoInteres INTO v_MontoInteres
    FROM Credito c
    INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
    WHERE c.CreditoID = p_CreditoID;
    
    -- Obtener intereses exonerados previamente
    SELECT COALESCE(SUM(MontoPagado), 0) INTO v_InteresesExonerados
    FROM pago
    WHERE CreditoID = p_CreditoID 
      AND TipoConcepto = 'I';
    
    -- Calcular disponible
    SET p_MontoDisponible = v_MontoInteres - v_InteresesExonerados;
    SET p_Valido = CASE WHEN p_MontoDisponible > 0 THEN 1 ELSE 0 END;
END$$

DELIMITER ;

-- ====================================================================
-- 8. PROCEDIMIENTO: Validar monto disponible para exonerar (Mora)
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_ValidarMontoMoraDisponible`(
    IN p_CreditoID INT,
    OUT p_MontoDisponible DECIMAL(12,2),
    OUT p_Valido TINYINT
)
BEGIN
    DECLARE v_MoraAcumulada DECIMAL(12,2);
    DECLARE v_MoraExonerada DECIMAL(12,2);
    
    -- Obtener mora acumulada (pagos con EsMora = 1)
    SELECT COALESCE(SUM(MontoPagado), 0) INTO v_MoraAcumulada
    FROM pago
    WHERE CreditoID = p_CreditoID 
      AND EsMora = 1;
    
    -- Obtener mora exonerada previamente
    SELECT COALESCE(SUM(MontoPagado), 0) INTO v_MoraExonerada
    FROM pago
    WHERE CreditoID = p_CreditoID 
      AND TipoConcepto = 'M';
    
    -- Calcular disponible
    SET p_MontoDisponible = v_MoraAcumulada - v_MoraExonerada;
    SET p_Valido = CASE WHEN p_MontoDisponible > 0 THEN 1 ELSE 0 END;
END$$

DELIMITER ;

-- ====================================================================
-- 9. PROCEDIMIENTO: Crear pago automático tras aprobación
-- Se ejecuta cuando se aprueba una solicitud de exoneracion
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_GenerarPagoExoneracion`(
    IN p_SolicitudExoneracionID INT,
    IN p_CreditoID INT,
    IN p_MontoExonerado DECIMAL(12,2),
    IN p_TipoConcepto CHAR(1),
    IN p_UserAprobadorID BIGINT,
    IN p_ComentarioAprobacion TEXT,
    OUT p_PagoID INT
)
BEGIN
    INSERT INTO pago (
        CreditoID,
        CuotaID,
        PromotorCobradorID,
        MontoPagado,
        FechaPago,
        TipoPago,
        TipoConcepto,
        EsMora,
        EsPagoAMayor,
        EsPagoForzado,
        EsPagoAutomatico,
        Comentario,
        UsuarioRegistro,
        FechaCreacion,
        Activo
    ) VALUES (
        p_CreditoID,
        NULL,  -- No aplica a cuota específica
        NULL,  -- No aplica promotor
        p_MontoExonerado,
        NOW(),
        'SISTEMA',  -- Tipo de pago SISTEMA
        p_TipoConcepto,  -- I, M, o P
        CASE WHEN p_TipoConcepto = 'M' THEN 1 ELSE 0 END,  -- EsMora solo para tipo M
        0,
        0,
        1,  -- EsPagoAutomatico
        CONCAT('Exoneración aprobada - ', p_ComentarioAprobacion),
        CONCAT('UserID_', p_UserAprobadorID),
        NOW(),
        1
    );
    
    SET p_PagoID = LAST_INSERT_ID();
END$$

DELIMITER ;

-- ====================================================================
-- 10. PROCEDIMIENTO: Validar duplicidad de solicitud
-- Evita crear múltiples solicitudes para el mismo crédito y tipo
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_ValidarDuplicidadSolicitud`(
    IN p_CreditoID INT,
    IN p_TipoExoneracionID INT,
    OUT p_Existe TINYINT
)
BEGIN
    DECLARE v_Count INT;
    
    SELECT COUNT(*) INTO v_Count
    FROM SolicitudExoneracion
    WHERE CreditoID = p_CreditoID
      AND TipoExoneracionID = p_TipoExoneracionID
      AND Estado IN ('PENDIENTE', 'APROBADO')
      AND Activo = 1;
    
    SET p_Existe = CASE WHEN v_Count > 0 THEN 1 ELSE 0 END;
END$$

DELIMITER ;

-- ====================================================================
-- 11. VISTA: Dashboard de exoneraciones por período
-- ====================================================================

CREATE OR REPLACE VIEW vw_DashboardExoneraciones AS
SELECT 
    DATE(h.FechaExoneracion) AS Fecha,
    te.Codigo,
    te.Nombre AS TipoExoneracion,
    COUNT(h.HistorialExoneracionID) AS CantidadExoneraciones,
    SUM(h.MontoExonerado) AS MontoTotalExonerado,
    COUNT(DISTINCT h.ClienteID) AS ClientesAfectados,
    COUNT(DISTINCT h.CreditoID) AS CreditosAfectados
FROM HistorialExoneracion h
INNER JOIN TipoExoneracion te ON h.TipoExoneracion = te.Codigo
GROUP BY DATE(h.FechaExoneracion), te.Codigo, te.Nombre
ORDER BY Fecha DESC, te.Nombre;

-- ====================================================================
-- 12. VISTA: Resumen de exoneraciones por cliente
-- ====================================================================

CREATE OR REPLACE VIEW vw_ExoneracionesPorCliente AS
SELECT 
    cl.ClienteID,
    cl.NombresApellidos,
    cl.DNI,
    COUNT(DISTINCT h.HistorialExoneracionID) AS CantidadExoneraciones,
    SUM(h.MontoExonerado) AS MontoTotalExonerado,
    GROUP_CONCAT(DISTINCT te.Nombre) AS TiposExonerados,
    MAX(h.FechaExoneracion) AS UltimaExoneracion
FROM HistorialExoneracion h
INNER JOIN Cliente cl ON h.ClienteID = cl.ClienteID
INNER JOIN TipoExoneracion te ON h.TipoExoneracion = te.Codigo
GROUP BY cl.ClienteID, cl.NombresApellidos, cl.DNI
ORDER BY SUM(h.MontoExonerado) DESC;

-- ====================================================================
-- 13. VISTA: Solicitudes pendientes por nivel de aprobación
-- ====================================================================

CREATE OR REPLACE VIEW vw_ExoneracionesPendientesPorNivel AS
SELECT 
    se.SolicitudExoneracionID,
    pc.CodigoCredito,
    cl.NombresApellidos,
    te.Nombre AS TipoExoneracion,
    se.MontoExonerado,
    na.Nombre AS NivelAprobacion,
    na.Orden,
    se.FechaSolicitud,
    DATEDIFF(NOW(), se.FechaSolicitud) AS DiasEnEspera
FROM SolicitudExoneracion se
INNER JOIN Credito c ON se.CreditoID = c.CreditoID
INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
INNER JOIN Cliente cl ON pc.ClienteID = cl.ClienteID
INNER JOIN TipoExoneracion te ON se.TipoExoneracionID = te.TipoExoneracionID
LEFT JOIN NivelAprobacion na ON se.NivelAprobacionRequerido = na.NivelAprobacionID
WHERE se.Estado = 'PENDIENTE'
ORDER BY na.Orden ASC, se.FechaSolicitud ASC;

-- ====================================================================
-- 14. VISTA: Estado de aprobaciones por solicitud
-- ====================================================================

CREATE OR REPLACE VIEW vw_EstadoAprobacionesExoneracion AS
SELECT 
    se.SolicitudExoneracionID,
    pc.CodigoCredito,
    te.Nombre AS TipoExoneracion,
    se.MontoExonerado,
    na.Nombre AS NivelAprobacion,
    ae.Estado AS EstadoAprobacion,
    CASE 
        WHEN COUNT(CASE WHEN ae.Estado = 'APROBADO' THEN 1 END) = COUNT(*) THEN 'COMPLETADA'
        WHEN COUNT(CASE WHEN ae.Estado = 'RECHAZADO' THEN 1 END) > 0 THEN 'RECHAZADA'
        ELSE 'EN PROCESO'
    END AS EstadoGeneral
FROM SolicitudExoneracion se
INNER JOIN Credito c ON se.CreditoID = c.CreditoID
INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
INNER JOIN TipoExoneracion te ON se.TipoExoneracionID = te.TipoExoneracionID
INNER JOIN AprobacionExoneracion ae ON se.SolicitudExoneracionID = ae.SolicitudExoneracionID
LEFT JOIN NivelAprobacion na ON ae.NivelAprobacionID = na.NivelAprobacionID
GROUP BY se.SolicitudExoneracionID
ORDER BY se.FechaSolicitud DESC;
