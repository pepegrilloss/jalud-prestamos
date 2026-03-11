-- =====================================================
-- MIGRACIÓN MULTI-SEDE - Sistema de Préstamos JALUD
-- Fecha: 2026-03-10
-- Descripción: Agrega soporte multi-sede a todas las tablas
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- =====================================================
-- 1. CREAR TABLA SEDE
-- =====================================================
CREATE TABLE IF NOT EXISTS `Sede` (
  `SedeID` int(11) NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL,
  PRIMARY KEY (`SedeID`),
  UNIQUE KEY `UQ_Sede_Nombre` (`Nombre`),
  UNIQUE KEY `UQ_Sede_Codigo` (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. INSERTAR SEDES INICIALES
-- =====================================================
INSERT INTO `Sede` (`Nombre`, `Codigo`) VALUES
('Chiclayo', 'CHI'),
('Trujillo', 'TRU');

-- =====================================================
-- 3. AGREGAR SedeID A TABLA users
-- =====================================================
ALTER TABLE `users` ADD COLUMN `SedeID` int(11) NULL AFTER `PromotorCobradorID`;
ALTER TABLE `users` ADD KEY `FK_users_Sede` (`SedeID`);
ALTER TABLE `users` ADD CONSTRAINT `FK_users_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);

-- Asignar sede por defecto (Chiclayo = 1) a usuarios existentes
UPDATE `users` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- =====================================================
-- 4. AGREGAR SedeID A TABLAS OPERACIONALES
-- =====================================================

-- Cliente
ALTER TABLE `Cliente` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Cliente` ADD KEY `FK_Cliente_Sede` (`SedeID`);
ALTER TABLE `Cliente` ADD CONSTRAINT `FK_Cliente_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Cliente` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- ProposicionCredito
ALTER TABLE `ProposicionCredito` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `ProposicionCredito` ADD KEY `FK_ProposicionCredito_Sede` (`SedeID`);
ALTER TABLE `ProposicionCredito` ADD CONSTRAINT `FK_ProposicionCredito_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `ProposicionCredito` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Credito
ALTER TABLE `Credito` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Credito` ADD KEY `FK_Credito_Sede` (`SedeID`);
ALTER TABLE `Credito` ADD CONSTRAINT `FK_Credito_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Credito` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- cuota
ALTER TABLE `cuota` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `cuota` ADD KEY `FK_cuota_Sede` (`SedeID`);
ALTER TABLE `cuota` ADD CONSTRAINT `FK_cuota_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `cuota` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- pago
ALTER TABLE `pago` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `pago` ADD KEY `FK_pago_Sede` (`SedeID`);
ALTER TABLE `pago` ADD CONSTRAINT `FK_pago_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `pago` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Compra
ALTER TABLE `Compra` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Compra` ADD KEY `FK_Compra_Sede` (`SedeID`);
ALTER TABLE `Compra` ADD CONSTRAINT `FK_Compra_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Compra` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Gasto
ALTER TABLE `Gasto` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Gasto` ADD KEY `FK_Gasto_Sede` (`SedeID`);
ALTER TABLE `Gasto` ADD CONSTRAINT `FK_Gasto_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Gasto` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- apertura_cierre_dia
ALTER TABLE `apertura_cierre_dia` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `apertura_cierre_dia` ADD KEY `FK_apertura_cierre_dia_Sede` (`SedeID`);
ALTER TABLE `apertura_cierre_dia` ADD CONSTRAINT `FK_apertura_cierre_dia_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `apertura_cierre_dia` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Actualizar constraint unique para permitir un día abierto POR SEDE
ALTER TABLE `apertura_cierre_dia` DROP INDEX `unique_abierto`;
ALTER TABLE `apertura_cierre_dia` ADD UNIQUE KEY `unique_abierto_por_sede` (`SedeID`, `abierto_flag`);

-- PromotorCobrador
ALTER TABLE `PromotorCobrador` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `PromotorCobrador` ADD KEY `FK_PromotorCobrador_Sede` (`SedeID`);
ALTER TABLE `PromotorCobrador` ADD CONSTRAINT `FK_PromotorCobrador_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `PromotorCobrador` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- SolicitudExoneracion
ALTER TABLE `SolicitudExoneracion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `SolicitudExoneracion` ADD KEY `FK_SolicitudExoneracion_Sede` (`SedeID`);
ALTER TABLE `SolicitudExoneracion` ADD CONSTRAINT `FK_SolicitudExoneracion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `SolicitudExoneracion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- AprobacionProposicion
ALTER TABLE `AprobacionProposicion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `AprobacionProposicion` ADD KEY `FK_AprobacionProposicion_Sede` (`SedeID`);
ALTER TABLE `AprobacionProposicion` ADD CONSTRAINT `FK_AprobacionProposicion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `AprobacionProposicion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- AprobacionExoneracion
ALTER TABLE `AprobacionExoneracion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `AprobacionExoneracion` ADD KEY `FK_AprobacionExoneracion_Sede` (`SedeID`);
ALTER TABLE `AprobacionExoneracion` ADD CONSTRAINT `FK_AprobacionExoneracion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `AprobacionExoneracion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- AnalisisEconomico
ALTER TABLE `AnalisisEconomico` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `AnalisisEconomico` ADD KEY `FK_AnalisisEconomico_Sede` (`SedeID`);
ALTER TABLE `AnalisisEconomico` ADD CONSTRAINT `FK_AnalisisEconomico_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `AnalisisEconomico` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- EvaluacionCredito
ALTER TABLE `EvaluacionCredito` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `EvaluacionCredito` ADD KEY `FK_EvaluacionCredito_Sede` (`SedeID`);
ALTER TABLE `EvaluacionCredito` ADD CONSTRAINT `FK_EvaluacionCredito_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `EvaluacionCredito` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- DocumentoCliente
ALTER TABLE `DocumentoCliente` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `DocumentoCliente` ADD KEY `FK_DocumentoCliente_Sede` (`SedeID`);
ALTER TABLE `DocumentoCliente` ADD CONSTRAINT `FK_DocumentoCliente_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `DocumentoCliente` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Negocio
ALTER TABLE `Negocio` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Negocio` ADD KEY `FK_Negocio_Sede` (`SedeID`);
ALTER TABLE `Negocio` ADD CONSTRAINT `FK_Negocio_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Negocio` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TelefonoNegocio
ALTER TABLE `TelefonoNegocio` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TelefonoNegocio` ADD KEY `FK_TelefonoNegocio_Sede` (`SedeID`);
ALTER TABLE `TelefonoNegocio` ADD CONSTRAINT `FK_TelefonoNegocio_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TelefonoNegocio` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- HistorialExoneracion
ALTER TABLE `HistorialExoneracion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `HistorialExoneracion` ADD KEY `FK_HistorialExoneracion_Sede` (`SedeID`);
ALTER TABLE `HistorialExoneracion` ADD CONSTRAINT `FK_HistorialExoneracion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `HistorialExoneracion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- mora
ALTER TABLE `mora` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `mora` ADD KEY `FK_mora_Sede` (`SedeID`);
ALTER TABLE `mora` ADD CONSTRAINT `FK_mora_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `mora` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- logs
ALTER TABLE `logs` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `logs` ADD KEY `FK_logs_Sede` (`SedeID`);
ALTER TABLE `logs` ADD CONSTRAINT `FK_logs_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `logs` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- CompraDetalle
ALTER TABLE `CompraDetalle` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `CompraDetalle` ADD KEY `FK_CompraDetalle_Sede` (`SedeID`);
ALTER TABLE `CompraDetalle` ADD CONSTRAINT `FK_CompraDetalle_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `CompraDetalle` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- GastoDetalle
ALTER TABLE `GastoDetalle` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `GastoDetalle` ADD KEY `FK_GastoDetalle_Sede` (`SedeID`);
ALTER TABLE `GastoDetalle` ADD CONSTRAINT `FK_GastoDetalle_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `GastoDetalle` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- =====================================================
-- 5. AGREGAR SedeID A TABLAS CATÁLOGO (por sede)
-- =====================================================

-- Tasa
ALTER TABLE `Tasa` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Tasa` ADD KEY `FK_Tasa_Sede` (`SedeID`);
ALTER TABLE `Tasa` ADD CONSTRAINT `FK_Tasa_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Tasa` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TasaMora
ALTER TABLE `TasaMora` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TasaMora` ADD KEY `FK_TasaMora_Sede` (`SedeID`);
ALTER TABLE `TasaMora` ADD CONSTRAINT `FK_TasaMora_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TasaMora` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TipoCredito
ALTER TABLE `TipoCredito` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TipoCredito` ADD KEY `FK_TipoCredito_Sede` (`SedeID`);
ALTER TABLE `TipoCredito` ADD CONSTRAINT `FK_TipoCredito_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TipoCredito` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TipoPago
ALTER TABLE `TipoPago` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TipoPago` ADD KEY `FK_TipoPago_Sede` (`SedeID`);
ALTER TABLE `TipoPago` ADD CONSTRAINT `FK_TipoPago_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TipoPago` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Giro
ALTER TABLE `Giro` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Giro` ADD KEY `FK_Giro_Sede` (`SedeID`);
ALTER TABLE `Giro` ADD CONSTRAINT `FK_Giro_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Giro` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- SubGiro
ALTER TABLE `SubGiro` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `SubGiro` ADD KEY `FK_SubGiro_Sede` (`SedeID`);
ALTER TABLE `SubGiro` ADD CONSTRAINT `FK_SubGiro_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `SubGiro` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Ciudad
ALTER TABLE `Ciudad` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Ciudad` ADD KEY `FK_Ciudad_Sede` (`SedeID`);
ALTER TABLE `Ciudad` ADD CONSTRAINT `FK_Ciudad_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Ciudad` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Zona
ALTER TABLE `Zona` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Zona` ADD KEY `FK_Zona_Sede` (`SedeID`);
ALTER TABLE `Zona` ADD CONSTRAINT `FK_Zona_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Zona` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- NivelAprobacion
ALTER TABLE `NivelAprobacion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `NivelAprobacion` ADD KEY `FK_NivelAprobacion_Sede` (`SedeID`);
ALTER TABLE `NivelAprobacion` ADD CONSTRAINT `FK_NivelAprobacion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `NivelAprobacion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TipoComprobante
ALTER TABLE `TipoComprobante` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TipoComprobante` ADD KEY `FK_TipoComprobante_Sede` (`SedeID`);
ALTER TABLE `TipoComprobante` ADD CONSTRAINT `FK_TipoComprobante_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TipoComprobante` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TipoComprobanteGasto
ALTER TABLE `TipoComprobanteGasto` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TipoComprobanteGasto` ADD KEY `FK_TipoComprobanteGasto_Sede` (`SedeID`);
ALTER TABLE `TipoComprobanteGasto` ADD CONSTRAINT `FK_TipoComprobanteGasto_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TipoComprobanteGasto` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- Motivo
ALTER TABLE `Motivo` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `Motivo` ADD KEY `FK_Motivo_Sede` (`SedeID`);
ALTER TABLE `Motivo` ADD CONSTRAINT `FK_Motivo_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `Motivo` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- TipoExoneracion
ALTER TABLE `TipoExoneracion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `TipoExoneracion` ADD KEY `FK_TipoExoneracion_Sede` (`SedeID`);
ALTER TABLE `TipoExoneracion` ADD CONSTRAINT `FK_TipoExoneracion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `TipoExoneracion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

-- UserNivelAprobacion
ALTER TABLE `UserNivelAprobacion` ADD COLUMN `SedeID` int(11) NULL;
ALTER TABLE `UserNivelAprobacion` ADD KEY `FK_UserNivelAprobacion_Sede` (`SedeID`);
ALTER TABLE `UserNivelAprobacion` ADD CONSTRAINT `FK_UserNivelAprobacion_Sede` FOREIGN KEY (`SedeID`) REFERENCES `Sede` (`SedeID`);
UPDATE `UserNivelAprobacion` SET `SedeID` = 1 WHERE `SedeID` IS NULL;

COMMIT;

-- =====================================================
-- MIGRACIÓN COMPLETADA
-- Todas las tablas ahora tienen columna SedeID
-- Datos existentes asignados a Sede "Chiclayo" (ID=1)
-- =====================================================
