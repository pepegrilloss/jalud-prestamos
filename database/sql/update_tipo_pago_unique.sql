-- Fix unique constraint for TipoPago to allow same name in different sedes
-- 1. Drop the existing unique constraint on 'Nombre'
ALTER TABLE `TipoPago` DROP INDEX `Nombre`;

-- 2. Add a new composite unique constraint for 'SedeID' and 'Nombre'
ALTER TABLE `TipoPago` ADD UNIQUE KEY `tipo_pago_sede_nombre_unique` (`SedeID`, `Nombre`);
