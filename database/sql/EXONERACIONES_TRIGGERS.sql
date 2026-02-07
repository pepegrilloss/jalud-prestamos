-- ====================================================================
-- TRIGGERS Y PROCEDIMIENTOS ALMACENADOS CRÍTICOS
-- ====================================================================
-- Archivo: EXONERACIONES_TRIGGERS.sql
-- Propósito: Mantener integridad de datos y automatizar flujos

-- ====================================================================
-- TRIGGER 1: Validar que usuario solicitante ≠ aprobador
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_validar_usuarios_exoneracion` 
BEFORE UPDATE ON `SolicitudExoneracion`
FOR EACH ROW
BEGIN
    DECLARE v_error_message VARCHAR(255);
    
    IF NEW.Estado = 'APROBADO' AND NEW.UserAprobadorID IS NOT NULL THEN
        IF NEW.UserAprobadorID = NEW.UserSolicitanteID THEN
            SET v_error_message = 'El usuario solicitante no puede aprobar su propia exoneracion';
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
        END IF;
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- TRIGGER 2: Crear AprobacionExoneracion automáticamente
-- Cuando se crea una SolicitudExoneracion,
-- se crean registros en AprobacionExoneracion para cada nivel requerido
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_crear_aprobaciones_exoneracion`
AFTER INSERT ON `SolicitudExoneracion`
FOR EACH ROW
BEGIN
    DECLARE v_NivelID INT;
    DECLARE v_Orden INT;
    DECLARE v_MaxOrden INT;
    DECLARE done INT DEFAULT FALSE;
    
    DECLARE cursor_niveles CURSOR FOR
        SELECT NivelAprobacionID, Orden
        FROM NivelAprobacion
        WHERE NivelAprobacionID >= (
            SELECT NivelAprobacionID FROM NivelAprobacion 
            WHERE NivelAprobacionID = NEW.NivelAprobacionRequerido
        )
        AND Activo = 1
        ORDER BY Orden ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cursor_niveles;
    read_loop: LOOP
        FETCH cursor_niveles INTO v_NivelID, v_Orden;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO AprobacionExoneracion (
            SolicitudExoneracionID,
            NivelAprobacionID,
            Estado,
            FechaCreacion
        ) VALUES (
            NEW.SolicitudExoneracionID,
            v_NivelID,
            'PENDIENTE',
            NOW()
        );
    END LOOP;
    
    CLOSE cursor_niveles;
END$$

DELIMITER ;

-- ====================================================================
-- TRIGGER 3: Actualizar estado de SolicitudExoneracion
-- Cuando TODAS las aprobaciones son APROBADO, cambiar a APROBADO
-- Si alguna es RECHAZADO, cambiar a RECHAZADO
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_actualizar_estado_solicitud`
AFTER UPDATE ON `AprobacionExoneracion`
FOR EACH ROW
BEGIN
    DECLARE v_TotalAprobaciones INT;
    DECLARE v_TotalAprobadas INT;
    DECLARE v_TotalRechazadas INT;
    DECLARE v_SolicitudID INT;
    
    SET v_SolicitudID = NEW.SolicitudExoneracionID;
    
    -- Contar aprobaciones totales, aprobadas y rechazadas
    SELECT 
        COUNT(*),
        SUM(CASE WHEN Estado = 'APROBADO' THEN 1 ELSE 0 END),
        SUM(CASE WHEN Estado = 'RECHAZADO' THEN 1 ELSE 0 END)
    INTO v_TotalAprobaciones, v_TotalAprobadas, v_TotalRechazadas
    FROM AprobacionExoneracion
    WHERE SolicitudExoneracionID = v_SolicitudID;
    
    -- Si hay rechazo, rechazar la solicitud completa
    IF v_TotalRechazadas > 0 THEN
        UPDATE SolicitudExoneracion
        SET Estado = 'RECHAZADO'
        WHERE SolicitudExoneracionID = v_SolicitudID;
    
    -- Si todas están aprobadas, aprobar la solicitud
    ELSEIF v_TotalAprobadas = v_TotalAprobaciones THEN
        UPDATE SolicitudExoneracion
        SET Estado = 'APROBADO'
        WHERE SolicitudExoneracionID = v_SolicitudID;
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- TRIGGER 4: Registrar en HistorialExoneracion cuando se aprueba
-- Crear pago automático y registrar histórico
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_procesar_aprobacion_exoneracion`
AFTER UPDATE ON `SolicitudExoneracion`
FOR EACH ROW
BEGIN
    DECLARE v_TipoConcepto CHAR(1);
    DECLARE v_PagoID INT;
    DECLARE v_ClienteID INT;
    DECLARE v_UsuarioAprobador VARCHAR(100);
    
    -- Solo procesar si estado cambió a APROBADO
    IF NEW.Estado = 'APROBADO' AND OLD.Estado != 'APROBADO' THEN
        
        -- Obtener código de tipo de exoneracion (P, I, M)
        SELECT Codigo INTO v_TipoConcepto
        FROM TipoExoneracion
        WHERE TipoExoneracionID = NEW.TipoExoneracionID;
        
        -- Obtener ClienteID del crédito
        SELECT pc.ClienteID INTO v_ClienteID
        FROM Credito c
        INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
        WHERE c.CreditoID = NEW.CreditoID;
        
        -- Obtener usuario aprobador
        SELECT u.name INTO v_UsuarioAprobador
        FROM users u
        WHERE u.id = NEW.UserAprobadorID
        LIMIT 1;
        
        -- 1. Crear pago automático
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
            NEW.CreditoID,
            NULL,
            NULL,
            NEW.MontoExonerado,
            NOW(),
            'SISTEMA',
            v_TipoConcepto,
            CASE WHEN v_TipoConcepto = 'M' THEN 1 ELSE 0 END,
            0,
            0,
            1,
            CONCAT('Exoneración aprobada - ', NEW.ComentarioAprobacion),
            CONCAT('UserID_', NEW.UserAprobadorID),
            NOW(),
            1
        );
        
        SET v_PagoID = LAST_INSERT_ID();
        
        -- 2. Actualizar SolicitudExoneracion con ID del pago
        UPDATE SolicitudExoneracion
        SET PagoGeneradoID = v_PagoID,
            FechaAprobacion = NOW()
        WHERE SolicitudExoneracionID = NEW.SolicitudExoneracionID;
        
        -- 3. Registrar en HistorialExoneracion
        INSERT INTO HistorialExoneracion (
            SolicitudExoneracionID,
            CreditoID,
            ClienteID,
            TipoExoneracion,
            MontoExonerado,
            FechaExoneracion,
            UsuarioAprobador,
            Comentario
        ) VALUES (
            NEW.SolicitudExoneracionID,
            NEW.CreditoID,
            v_ClienteID,
            v_TipoConcepto,
            NEW.MontoExonerado,
            NOW(),
            v_UsuarioAprobador,
            NEW.ComentarioAprobacion
        );
    
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- TRIGGER 5: Actualizar SaldoPendiente cuando se registra pago
-- Al crear un pago automático, restar del SaldoPendiente
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_actualizar_saldo_por_exoneracion`
AFTER INSERT ON `pago`
FOR EACH ROW
BEGIN
    DECLARE v_ProposicionID INT;
    
    -- Solo procesar si es pago automático de exoneracion
    IF NEW.EsPagoAutomatico = 1 AND NEW.TipoConcepto IN ('I', 'M', 'P') THEN
        
        -- Obtener ProposicionCreditoID del crédito
        SELECT ProposicionCreditoID INTO v_ProposicionID
        FROM Credito
        WHERE CreditoID = NEW.CreditoID;
        
        -- Actualizar SaldoPendiente
        UPDATE ProposicionCredito
        SET SaldoPendiente = SaldoPendiente - NEW.MontoPagado
        WHERE ProposicionCreditoID = v_ProposicionID;
        
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- TRIGGER 6: Prevenir exoneracion de crédito inactivo o cerrado
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_validar_credito_activo`
BEFORE INSERT ON `SolicitudExoneracion`
FOR EACH ROW
BEGIN
    DECLARE v_Activo INT;
    DECLARE v_SaldoPendiente DECIMAL(12,2);
    DECLARE v_error_message VARCHAR(255);
    
    SELECT c.Activo, pc.SaldoPendiente
    INTO v_Activo, v_SaldoPendiente
    FROM Credito c
    INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
    WHERE c.CreditoID = NEW.CreditoID;
    
    IF v_Activo = 0 THEN
        SET v_error_message = 'No se puede exonerar un crédito inactivo';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;
    
    IF v_SaldoPendiente <= 0 THEN
        SET v_error_message = 'El crédito no tiene saldo pendiente';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- TRIGG ER 7: Validar monto no supera disponible (para Interés y Mora)
-- ====================================================================

DELIMITER $$

CREATE TRIGGER `trg_validar_monto_disponible`
BEFORE INSERT ON `SolicitudExoneracion`
FOR EACH ROW
BEGIN
    DECLARE v_error_message VARCHAR(255);
    
    -- Validar que monto a exonerar no supere el disponible
    IF NEW.MontoExonerado > NEW.MontoDisponible THEN
        SET v_error_message = CONCAT('Monto a exonerar ($', NEW.MontoExonerado, 
                                      ') no puede superar monto disponible ($', 
                                      NEW.MontoDisponible, ')');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;
    
    -- Validar monto positivo
    IF NEW.MontoExonerado <= 0 THEN
        SET v_error_message = 'Monto a exonerar debe ser mayor que 0';
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_message;
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- PROCEDIMIENTO: Calcular monto total de exoneraciones por cliente
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_CalcularExoneracionesTotalesCliente`(
    IN p_ClienteID INT,
    OUT p_MontoTotalExonerado DECIMAL(12,2),
    OUT p_CantidadExoneraciones INT,
    OUT p_TiposExonerados VARCHAR(50)
)
BEGIN
    SELECT 
        COALESCE(SUM(h.MontoExonerado), 0),
        COUNT(DISTINCT h.HistorialExoneracionID),
        GROUP_CONCAT(DISTINCT te.Nombre ORDER BY te.Nombre)
    INTO p_MontoTotalExonerado, p_CantidadExoneraciones, p_TiposExonerados
    FROM HistorialExoneracion h
    INNER JOIN TipoExoneracion te ON h.TipoExoneracion = te.Codigo
    WHERE h.ClienteID = p_ClienteID;
END$$

DELIMITER ;

-- ====================================================================
-- PROCEDIMIENTO: Validar elegibilidad para Pronto Pago
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_ValidarElegibilidadProntoPago`(
    IN p_CreditoID INT,
    OUT p_EsElegible TINYINT,
    OUT p_Razon VARCHAR(255)
)
BEGIN
    DECLARE v_CuotasAtrasadas INT;
    DECLARE v_DiasAtrasoTotal INT;
    
    SELECT 
        COUNT(CASE WHEN DiasAtraso > 0 THEN 1 END),
        SUM(CASE WHEN DiasAtraso > 0 THEN DiasAtraso ELSE 0 END)
    INTO v_CuotasAtrasadas, v_DiasAtrasoTotal
    FROM cuota
    WHERE CreditoID = p_CreditoID;
    
    IF v_CuotasAtrasadas > 0 THEN
        SET p_EsElegible = 0;
        SET p_Razon = CONCAT('Cliente tiene ', v_CuotasAtrasadas, 
                            ' cuota(s) con atraso de ', v_DiasAtrasoTotal, ' días');
    ELSE
        SET p_EsElegible = 1;
        SET p_Razon = 'Cliente es elegible para Pronto Pago';
    END IF;
END$$

DELIMITER ;

-- ====================================================================
-- PROCEDIMIENTO: Obtener solicitudes pendientes de aprobación
-- Por usuario aprobador
-- ====================================================================

DELIMITER $$

CREATE PROCEDURE `sp_ObtenerSolicitudesPendientes`(
    IN p_UserID BIGINT
)
BEGIN
    SELECT 
        se.SolicitudExoneracionID,
        pc.CodigoCredito,
        cl.NombresApellidos,
        te.Nombre AS TipoExoneracion,
        se.MontoExonerado,
        na.Nombre AS NivelAprobacion,
        se.FechaSolicitud,
        DATEDIFF(NOW(), se.FechaSolicitud) AS DiasEnEspera,
        se.Comentario
    FROM SolicitudExoneracion se
    INNER JOIN Credito c ON se.CreditoID = c.CreditoID
    INNER JOIN ProposicionCredito pc ON c.ProposicionCreditoID = pc.ProposicionCreditoID
    INNER JOIN Cliente cl ON pc.ClienteID = cl.ClienteID
    INNER JOIN TipoExoneracion te ON se.TipoExoneracionID = te.TipoExoneracionID
    INNER JOIN NivelAprobacion na ON se.NivelAprobacionRequerido = na.NivelAprobacionID
    INNER JOIN AprobacionExoneracion ae ON se.SolicitudExoneracionID = ae.SolicitudExoneracionID
    INNER JOIN UserNivelAprobacion una ON na.NivelAprobacionID = una.NivelAprobacionID
    WHERE 
        se.Estado = 'PENDIENTE'
        AND ae.Estado = 'PENDIENTE'
        AND una.UserID = p_UserID
        AND una.Activo = 1
    ORDER BY se.FechaSolicitud ASC;
END$$

DELIMITER ;

-- ====================================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- ====================================================================

CREATE INDEX idx_solicitud_estado_fecha 
ON SolicitudExoneracion(Estado, FechaSolicitud);

CREATE INDEX idx_solicitud_credito_tipo 
ON SolicitudExoneracion(CreditoID, TipoExoneracionID);

CREATE INDEX idx_aprobacion_solicitud_estado 
ON AprobacionExoneracion(SolicitudExoneracionID, Estado);

CREATE INDEX idx_historial_credito_cliente 
ON HistorialExoneracion(CreditoID, ClienteID, FechaExoneracion);

CREATE INDEX idx_pago_tipo_concepto 
ON pago(TipoConcepto, EsPagoAutomatico);

-- ====================================================================
-- FIN DE TRIGGERS Y PROCEDIMIENTOS
-- ====================================================================
