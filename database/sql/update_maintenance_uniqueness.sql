-- SCRIPT LINEAL SIMPLE PARA AJUSTAR UNICIDAD POR SEDE
-- Ejecuta estos comandos uno por uno o en bloques.

-- 1. Tasas De Mora
ALTER TABLE `TasaMora` DROP INDEX `Nombre`;
ALTER TABLE `TasaMora` ADD UNIQUE KEY `UQ_TasaMora_Sede_Nombre` (`SedeID`, `Nombre`);

-- 2. Ciudades
ALTER TABLE `Ciudad` DROP INDEX `Nombre`;
ALTER TABLE `Ciudad` ADD UNIQUE KEY `UQ_Ciudad_Sede_Nombre` (`SedeID`, `Nombre`);

-- 3. Giros
ALTER TABLE `Giro` DROP INDEX `Codigo`;
ALTER TABLE `Giro` ADD UNIQUE KEY `UQ_Giro_Sede_Codigo` (`SedeID`, `Codigo`);

-- 4. Sub Giros (Requiere índice para FK primero)
ALTER TABLE `SubGiro` ADD INDEX `idx_subgiro_giro_fk` (`GiroID`);
ALTER TABLE `SubGiro` DROP INDEX `UQ_SubGiro_Giro`;
ALTER TABLE `SubGiro` ADD UNIQUE KEY `UQ_SubGiro_Sede_Giro_Desc` (`SedeID`, `GiroID`, `Descripcion`);

-- 5. Promotores y Cobradores
ALTER TABLE `PromotorCobrador` DROP INDEX `Codigo`;
ALTER TABLE `PromotorCobrador` ADD UNIQUE KEY `UQ_PromotorCobrador_Sede_Codigo` (`SedeID`, `Codigo`);

-- 6. Tasas
ALTER TABLE `Tasa` ADD UNIQUE KEY `UQ_Tasa_Sede_Nombre` (`SedeID`, `Nombre`);

-- 7. Tipos de Crédito
ALTER TABLE `TipoCredito` DROP INDEX `Codigo`;
ALTER TABLE `TipoCredito` ADD UNIQUE KEY `UQ_TipoCredito_Sede_Codigo` (`SedeID`, `Codigo`);

-- 8. Tipos De Pago
ALTER TABLE `TipoPago` DROP INDEX `Nombre`;
ALTER TABLE `TipoPago` ADD UNIQUE KEY `UQ_TipoPago_Sede_Nombre` (`SedeID`, `Nombre`);

-- 9. Niveles de Aprobación
ALTER TABLE `NivelAprobacion` DROP INDEX `Nombre`;
ALTER TABLE `NivelAprobacion` ADD UNIQUE KEY `UQ_NivelAprobacion_Sede_Nombre` (`SedeID`, `Nombre`);

-- 10. Tipos De Exoneración
ALTER TABLE `TipoExoneracion` DROP INDEX `Codigo`;
ALTER TABLE `TipoExoneracion` DROP INDEX `Nombre`;
ALTER TABLE `TipoExoneracion` ADD UNIQUE KEY `UQ_TipoExoneracion_Sede_Codigo` (`SedeID`, `Codigo`);
ALTER TABLE `TipoExoneracion` ADD UNIQUE KEY `UQ_TipoExoneracion_Sede_Nombre` (`SedeID`, `Nombre`);

-- 11. Tipos De Comprobante (Gastos)
ALTER TABLE `TipoComprobanteGasto` DROP INDEX `tipocomprobantegasto_nombre_unique`;
ALTER TABLE `TipoComprobanteGasto` ADD UNIQUE KEY `UQ_TipoComprobanteGasto_Sede_Nombre` (`SedeID`, `Nombre`);

-- 12. Zonas (Requiere índice para FK primero)
ALTER TABLE `Zona` ADD INDEX `idx_zona_ciudad_fk` (`CiudadID`);
ALTER TABLE `Zona` DROP INDEX `UQ_Zona_Ciudad`;
ALTER TABLE `Zona` ADD UNIQUE KEY `UQ_Zona_Sede_Ciudad_Nombre` (`SedeID`, `CiudadID`, `Nombre`);

-- 13. Tipos De Comprobante (Compras)
ALTER TABLE `TipoComprobante` DROP INDEX `nombre_unique`;
ALTER TABLE `TipoComprobante` ADD UNIQUE KEY `UQ_TipoComprobante_Sede_Nombre` (`SedeID`, `Nombre`);

-- 14. Motivos De Gasto
ALTER TABLE `Motivo` DROP INDEX `nombre_unique`;
ALTER TABLE `Motivo` ADD UNIQUE KEY `UQ_Motivo_Sede_Nombre` (`SedeID`, `Nombre`);
