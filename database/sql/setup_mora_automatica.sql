-- =====================================================
-- SCRIPT DE SETUP PARA SISTEMA DE MORA AUTOMÁTICA
-- =====================================================
-- Este script implementa el sistema de cálculo automático de mora
-- Fecha: 18/02/2026

-- 1. CREAR TABLA MORA
-- Esta tabla almacena el cálculo diario de mora para cada crédito vencido

-- =====================================================
-- CREAR TABLA TASADORA MORA (si no existe)
-- =====================================================

CREATE TABLE IF NOT EXISTS `tasaMora` (
  `TasaMoraID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Nombre` VARCHAR(50) NOT NULL UNIQUE,
  `Porcentaje` DECIMAL(5,2) NOT NULL,
  `Descripcion` VARCHAR(200) DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tasas de mora por porcentaje diario';

-- Insertar tasas de mora por defecto
INSERT IGNORE INTO `tasaMora` (`Nombre`, `Porcentaje`, `Descripcion`, `Activo`) VALUES
('Mora 0.5%', 0.50, 'Tasa de mora 0.5% diario', 1),
('Mora 0.8%', 0.80, 'Tasa de mora 0.8% diario', 1),
('Mora 1.0%', 1.00, 'Tasa de mora 1.0% diario', 1),
('Mora 1.5%', 1.50, 'Tasa de mora 1.5% diario', 1),
('Mora 2.0%', 2.00, 'Tasa de mora 2.0% diario', 1),
('Mora 2.5%', 2.50, 'Tasa de mora 2.5% diario', 1);

-- =====================================================
-- AGREGAR COLUMNA TasaMoraID a tabla Cliente (si no existe)
-- =====================================================

ALTER TABLE `Cliente` 
ADD COLUMN `TasaMoraID` INT DEFAULT NULL AFTER `PromotorCobradorID`,
ADD FOREIGN KEY (`TasaMoraID`) REFERENCES `tasaMora`(`TasaMoraID`) ON DELETE SET NULL;

-- =====================================================
-- CREAR TABLA MORA
-- Esta tabla almacena el cálculo diario de mora para cada crédito vencido
-- =====================================================

CREATE TABLE IF NOT EXISTS `mora` (
  `MoraID` INT NOT NULL AUTO_INCREMENT,
  `CreditoID` INT NOT NULL,
  `FechaMora` date NOT NULL,
  `SaldoPendiente` decimal(12,2) NOT NULL COMMENT 'Saldo sobre el que se calculó la mora',
  `PorcentajeMora` decimal(5,2) NOT NULL COMMENT 'Porcentaje de mora aplicado del cliente',
  `MontoMora` decimal(12,2) NOT NULL COMMENT 'Monto de mora calculado para este día',
  `MoraAcumulada` decimal(12,2) NOT NULL DEFAULT 0 COMMENT 'Mora total acumulada hasta esa fecha',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`MoraID`),
  KEY `idx_creditoId` (`CreditoID`),
  KEY `idx_fechaMora` (`FechaMora`),
  UNIQUE KEY `unique_credito_fecha` (`CreditoID`,`FechaMora`),
  FOREIGN KEY (`CreditoID`) REFERENCES `Credito` (`CreditoID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro diario de mora automática por vencimiento';

-- =====================================================
-- EXPLICACIÓN DEL CÁLCULO DE MORA
-- =====================================================
-- 
-- EJEMPLO PRÁCTICO:
-- ~~~~~~~~~~~~~~~~
-- Hoy: 14/02/2026
-- Crédito vence: 14/02/2026
-- Cliente tiene tasa de mora: 0.5% diario
-- Saldo pendiente: 100 soles
--
-- DÍA 1 (14/02/2026) - Primer día vencido:
--   Mora = 100 × (0.5 / 100) = 0.50 soles
--   Mora Acumulada = 0 + 0.50 = 0.50 soles
--
-- DÍA 2 (15/02/2026) - Sigue sin pagar:
--   Mora = 100 × (0.5 / 100) = 0.50 soles
--   Mora Acumulada = 0.50 + 0.50 = 1.00 soles
--
-- DÍA 3 (16/02/2026) - Cliente paga 50 soles, quedan 50:
--   Mora = 50 × (0.5 / 100) = 0.25 soles
--   Mora Acumulada = 1.00 + 0.25 = 1.25 soles
--
-- DÍA 4 (17/02/2026) - Sigue con 50 en deuda:
--   Mora = 50 × (0.5 / 100) = 0.25 soles
--   Mora Acumulada = 1.25 + 0.25 = 1.50 soles
--
-- Y así sucesivamente hasta que el saldo sea 0

-- =====================================================
-- COMPONENTES CREADOS
-- =====================================================
--
-- 1. MIGRATION FILE:
--    database/migrations/2026_02_18_create_moras_table.php
--
-- 2. MODEL:
--    app/Models/Mora.php
--    - Relación con Credito
--    - Método getMoraActual()
--
-- 3. JOB (Ejecutable):
--    app/Jobs/CalcularMoraAutomatica.php
--    - Lógica completa de cálculo
--    - Validaciones de saldo y vencimiento
--
-- 4. SCHEDULER CONFIGURATION:
--    app/Console/Kernel.php
--    - Ejecuta diariamente a las 00:01 AM
--    - OnOneServer para evitar duplicados en múltiples servidores
--
-- 5. ARTISAN COMMAND:
--    app/Console/Commands/CalcularMoraCommand.php
--    - Permite ejecutar manualmente
--    - Comando: php artisan mora:calcular
--
-- 6. FILAMENT RESOURCE:
--    app/Filament/Resources/MoraResource.php
--    - Panel de visualización de mora
--    - Filtros por crédito y período
--    - Vista en "Finanzas"
--
-- 7. FILAMENT PAGE:
--    app/Filament/Resources/MoraResource/Pages/ListMoras.php
--    - Tabla lista solo lectura

-- =====================================================
-- PASOS DE INSTALACIÓN
-- =====================================================
--
-- 1. Ejecutar esta migration:
--    No es necesario si ejecutas: php artisan migrate
--    (Ya está en el archivo migration de Laravel)
--
-- 2. Ejecutar las migraciones:
--    $ php artisan migrate
--
-- 3. Verificar que la tabla se creó:
--    $ SELECT COUNT(*) FROM mora;
--
-- 4. Configurar el Scheduler de Laravel:
--    - En tu servidor, agregar a crontab:
--    $ * * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
--    
--    - Esto ejecutará todas las tareas programadas cada minuto
--    - A las 00:01 AM se ejecutará automáticamente CalcularMoraAutomatica
--
-- 5. Para testing manual, ejecutar:
--    $ php artisan mora:calcular
--
-- 6. Verificar logs:
--    storage/logs/laravel.log

-- =====================================================
-- CONSULTAS ÚTILES PARA MONITOREO
-- =====================================================

-- Ver mora acumulada por cliente:
/*
SELECT 
    c.DNI,
    c.NombresApellidos,
    cr.CreditoID,
    cr.FechaVencimiento,
    m.FechaMora,
    m.MoraAcumulada,
    m.SaldoPendiente,
    m.PorcentajeMora
FROM mora m
JOIN credito cr ON m.CreditoID = cr.CreditoID
JOIN proposicion_credito pc ON cr.ProposicionCreditoID = pc.ProposicionCreditoID
JOIN cliente c ON pc.ClienteID = c.ClienteID
WHERE m.FechaMora = CURDATE()
ORDER BY m.MoraAcumulada DESC;
*/

-- Ver totalMora por crédito:
/*
SELECT 
    CreditoID,
    MAX(FechaMora) as UltimaFecha,
    MAX(MoraAcumulada) as MoraTotal,
    COUNT(*) as DiasEnMora
FROM mora
GROUP BY CreditoID
HAVING MoraTotal > 0
ORDER BY MoraTotal DESC;
*/

-- Ver créditos SIN mora (pagos al día):
/*
SELECT cr.CreditoID, cr.FechaVencimiento
FROM credito cr
WHERE cr.Activo = 1
AND cr.FechaVencimiento <= CURDATE()
AND cr.CreditoID NOT IN (SELECT DISTINCT CreditoID FROM mora);
*/
