-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 07-02-2026 a las 20:49:30
-- Versión del servidor: 5.7.23-23
-- Versión de PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `jvcso1ub_jalud_prestamos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ciudad`
--

CREATE TABLE `Ciudad` (
  `CiudadID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Nombre` VARCHAR(100) NOT NULL UNIQUE,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Zona`
--

CREATE TABLE `Zona` (
  `ZonaID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `CiudadID` INT NOT NULL,
  `Nombre` VARCHAR(100) NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  UNIQUE KEY `UQ_Zona_Ciudad` (`CiudadID`, `Nombre`),
  FOREIGN KEY (`CiudadID`) REFERENCES `Ciudad`(`CiudadID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Giro`
--

CREATE TABLE `Giro` (
  `GiroID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Codigo` VARCHAR(10) NOT NULL UNIQUE,
  `Descripcion` VARCHAR(200) NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `SubGiro`
--

CREATE TABLE `SubGiro` (
  `SubGiroID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `GiroID` INT NOT NULL,
  `Descripcion` VARCHAR(200) NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  UNIQUE KEY `UQ_SubGiro_Giro` (`GiroID`, `Descripcion`),
  FOREIGN KEY (`GiroID`) REFERENCES `Giro`(`GiroID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Tasa`
--

CREATE TABLE `Tasa` (
  `TasaID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Nombre` VARCHAR(50) NOT NULL,
  `Valor` DECIMAL(5,2) NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `Dias` INT NOT NULL,
  `Cuotas` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `PromotorCobrador`
--

CREATE TABLE `PromotorCobrador` (
  `PromotorCobradorID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Codigo` VARCHAR(20) NOT NULL UNIQUE,
  `Descripcion` VARCHAR(200) NOT NULL,
  `CiudadID` INT NOT NULL,
  `ZonaID` INT NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  FOREIGN KEY (`CiudadID`) REFERENCES `Ciudad`(`CiudadID`),
  FOREIGN KEY (`ZonaID`) REFERENCES `Zona`(`ZonaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Cliente`
--

CREATE TABLE `Cliente` (
  `ClienteID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `DNI` VARCHAR(20) NOT NULL UNIQUE,
  `NombresApellidos` VARCHAR(200) NOT NULL,
  `Sexo` CHAR(1) NOT NULL,
  `FechaNacimiento` DATE DEFAULT NULL,
  `Estado` VARCHAR(20) NOT NULL DEFAULT 'NO OBSERVADO',
  `ConyugeDNI` VARCHAR(20) DEFAULT NULL,
  `ConyugeNombresApellidos` VARCHAR(200) DEFAULT NULL,
  `Domicilio` VARCHAR(500) DEFAULT NULL,
  `TasaID` INT DEFAULT NULL,
  `GaranteID` INT DEFAULT NULL,
  `Observaciones` TEXT,
  `PromotorCobradorID` INT DEFAULT NULL,
  `FechaRegistro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `UsuarioRegistro` VARCHAR(100) DEFAULT NULL,
  `UsuarioModificacion` VARCHAR(100) DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCierre` DATE DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  KEY `IDX_FechaCierre` (`FechaCierre`),
  FOREIGN KEY (`GaranteID`) REFERENCES `Cliente`(`ClienteID`),
  FOREIGN KEY (`PromotorCobradorID`) REFERENCES `PromotorCobrador`(`PromotorCobradorID`),
  FOREIGN KEY (`TasaID`) REFERENCES `Tasa`(`TasaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AnalisisEconomico`
--

CREATE TABLE `AnalisisEconomico` (
  `AnalisisEconomicoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ClienteID` INT NOT NULL,
  `CapitalManifestado` DECIMAL(12,2) DEFAULT 0.00,
  `CapitalEstimado` DECIMAL(12,2) DEFAULT 0.00,
  `VentaManifestadaMin` DECIMAL(12,2) DEFAULT 0.00,
  `VentaManifestadaMax` DECIMAL(12,2) DEFAULT 0.00,
  `VentaEstimada` DECIMAL(12,2) DEFAULT 0.00,
  `MontoMaxRecomendado` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `FechaAnalisis` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaCierre` DATETIME DEFAULT NULL,
  `UsuarioAnalisis` VARCHAR(100) DEFAULT NULL,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `UsuarioModificacion` VARCHAR(100) DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`ClienteID`) REFERENCES `Cliente`(`ClienteID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apertura_cierre_dia`
--

CREATE TABLE `apertura_cierre_dia` (
  `AperturaCierreDiaID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Fecha` DATE NOT NULL UNIQUE,
  `FechaApertura` TIMESTAMP NULL DEFAULT NULL,
  `FechaCierre` TIMESTAMP NULL DEFAULT NULL,
  `EstadoDia` ENUM('ABIERTO','CERRADO') NOT NULL DEFAULT 'CERRADO',
  `UsuarioAperturaID` BIGINT UNSIGNED DEFAULT NULL,
  `UsuarioCierreID` BIGINT UNSIGNED DEFAULT NULL,
  `Observaciones` LONGTEXT,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `abierto_flag` INT GENERATED ALWAYS AS (CASE WHEN `EstadoDia` = 'ABIERTO' THEN 1 ELSE NULL END) STORED,
  UNIQUE KEY `unique_abierto` (`abierto_flag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `NivelAprobacion`
--

CREATE TABLE `NivelAprobacion` (
  `NivelAprobacionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Nombre` VARCHAR(50) NOT NULL UNIQUE,
  `MontoMinimo` DECIMAL(12,2) NOT NULL,
  `MontoMaximo` DECIMAL(12,2) NOT NULL,
  `Orden` INT NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoCredito`
--

CREATE TABLE `TipoCredito` (
  `TipoCreditoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Codigo` VARCHAR(10) NOT NULL UNIQUE,
  `Descripcion` VARCHAR(100) NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ProposicionCredito`
--

CREATE TABLE `ProposicionCredito` (
  `ProposicionCreditoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `CodigoCredito` VARCHAR(20) NOT NULL UNIQUE,
  `ClienteID` INT NOT NULL,
  `CodigoCliente` VARCHAR(20) NOT NULL,
  `TipoCreditoID` INT NOT NULL,
  `MontoTotal` DECIMAL(12,2) NOT NULL,
  `TasaID` INT NOT NULL,
  `TasaInteres` DECIMAL(5,2) NOT NULL,
  `Plazo` INT NOT NULL,
  `NumeroCuotas` INT NOT NULL,
  `MontoCuota` DECIMAL(12,2) NOT NULL,
  `MontoInteres` DECIMAL(12,2) NOT NULL,
  `TasaMora` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `ZonaID` INT DEFAULT NULL,
  `Observaciones` TEXT,
  `UserProponenteID` BIGINT NOT NULL,
  `FechaPropuesta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Estado` VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
  `NivelAprobacionRequerido` INT DEFAULT NULL,
  `UserAprobadorID` BIGINT DEFAULT NULL,
  `FechaAprobacion` DATETIME DEFAULT NULL,
  `ComentarioAprobacion` VARCHAR(500) DEFAULT NULL,
  `FechaDesembolso` DATETIME DEFAULT NULL,
  `UserDesembolsoID` BIGINT DEFAULT NULL,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `UserModificacionID` BIGINT DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCierre` DATE DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  `EsRefinanciamiento` TINYINT(1) NOT NULL DEFAULT 0,
  `FueRefinanciada` TINYINT(1) NOT NULL DEFAULT 0,
  `ProposicionCreditoAnteriorID` INT DEFAULT NULL,
  `MontoTotalPagar` DECIMAL(12,2) DEFAULT 0.00,
  `SaldoPendiente` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  KEY `IDX_EsRefinanciamiento` (`EsRefinanciamiento`),
  KEY `IDX_ProposicionCreditoAnteriorID` (`ProposicionCreditoAnteriorID`),
  KEY `IDX_FueRefinanciada` (`FueRefinanciada`),
  KEY `IDX_FechaCierre` (`FechaCierre`),
  FOREIGN KEY (`ClienteID`) REFERENCES `Cliente`(`ClienteID`),
  FOREIGN KEY (`NivelAprobacionRequerido`) REFERENCES `NivelAprobacion`(`NivelAprobacionID`),
  FOREIGN KEY (`TasaID`) REFERENCES `Tasa`(`TasaID`),
  FOREIGN KEY (`TipoCreditoID`) REFERENCES `TipoCredito`(`TipoCreditoID`),
  FOREIGN KEY (`ZonaID`) REFERENCES `Zona`(`ZonaID`),
  FOREIGN KEY (`ProposicionCreditoAnteriorID`) REFERENCES `ProposicionCredito`(`ProposicionCreditoID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AprobacionProposicion`
--

CREATE TABLE `AprobacionProposicion` (
  `AprobacionProposicionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ProposicionCreditoID` INT NOT NULL,
  `NivelAprobacionID` INT NOT NULL,
  `UserAprobadorID` BIGINT DEFAULT NULL,
  `Estado` VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
  `Comentario` TEXT,
  `FechaAprobacion` DATETIME DEFAULT NULL,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `UQ_AprobacionProposicion` (`ProposicionCreditoID`, `NivelAprobacionID`),
  FOREIGN KEY (`NivelAprobacionID`) REFERENCES `NivelAprobacion`(`NivelAprobacionID`),
  FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `ProposicionCredito`(`ProposicionCreditoID`) ON DELETE CASCADE,
  FOREIGN KEY (`UserAprobadorID`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoPago`
--

CREATE TABLE `TipoPago` (
  `TipoPagoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Nombre` VARCHAR(50) NOT NULL UNIQUE,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Credito`
--

CREATE TABLE `Credito` (
  `CreditoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ProposicionCreditoID` INT NOT NULL UNIQUE,
  `TipoPagoID` INT NOT NULL,
  `ComentarioGeneracion` TEXT,
  `FechaGeneracion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaInicio` DATE DEFAULT NULL COMMENT 'Fecha de inicio del crédito',
  `FechaVencimiento` DATE DEFAULT NULL COMMENT 'Fecha de vencimiento del crédito (vencimiento de última cuota)',
  `UserGeneracionID` BIGINT NOT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCierre` DATE DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  KEY `IDX_FechaInicio` (`FechaInicio`),
  KEY `IDX_FechaVencimiento` (`FechaVencimiento`),
  KEY `IDX_FechaCierre` (`FechaCierre`),
  FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `ProposicionCredito`(`ProposicionCreditoID`),
  FOREIGN KEY (`TipoPagoID`) REFERENCES `TipoPago`(`TipoPagoID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuota`
--

CREATE TABLE `cuota` (
  `CuotaID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `CreditoID` INT NOT NULL,
  `NumeroCuota` INT NOT NULL,
  `FechaVencimiento` DATE NOT NULL,
  `MontoCuota` DECIMAL(12,2) NOT NULL,
  `Estado` VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
  `DiasAtraso` INT DEFAULT 0,
  `MontoMora` DECIMAL(12,2) DEFAULT 0.00,
  `FechaPago` DATETIME DEFAULT NULL,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCierre` DATE DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  KEY `IDX_FechaCierre` (`FechaCierre`),
  FOREIGN KEY (`CreditoID`) REFERENCES `Credito`(`CreditoID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `DocumentoCliente`
--

CREATE TABLE `DocumentoCliente` (
  `DocumentoClienteID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ClienteID` INT NOT NULL,
  `TipoDocumento` VARCHAR(50) NOT NULL,
  `RutaArchivo` VARCHAR(500) NOT NULL,
  `NombreOriginal` VARCHAR(255) NOT NULL,
  `TamanioArchivo` BIGINT DEFAULT NULL,
  `Extension` VARCHAR(10) DEFAULT NULL,
  `Observaciones` VARCHAR(500) DEFAULT NULL,
  `UsuarioRegistro` VARCHAR(100) DEFAULT NULL,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioModificacion` VARCHAR(100) DEFAULT NULL,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`ClienteID`) REFERENCES `Cliente`(`ClienteID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `EvaluacionCredito`
--

CREATE TABLE `EvaluacionCredito` (
  `EvaluacionCreditoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ClienteID` INT NOT NULL,
  `Comentario` TEXT NOT NULL,
  `FechaRegistro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioRegistro` VARCHAR(100) DEFAULT NULL,
  `FechaCierre` DATETIME DEFAULT NULL,
  FOREIGN KEY (`ClienteID`) REFERENCES `Cliente`(`ClienteID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Negocio`
--

CREATE TABLE `Negocio` (
  `NegocioID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ClienteID` INT NOT NULL,
  `DireccionNegocio` VARCHAR(500) NOT NULL,
  `CiudadID` INT DEFAULT NULL,
  `ZonaID` INT DEFAULT NULL,
  `Antiguedad` DECIMAL(5,2) DEFAULT NULL,
  `GiroID` INT DEFAULT NULL,
  `SubGiroID` INT DEFAULT NULL,
  `Ubicacion` VARCHAR(20) DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  FOREIGN KEY (`ClienteID`) REFERENCES `Cliente`(`ClienteID`),
  FOREIGN KEY (`GiroID`) REFERENCES `Giro`(`GiroID`),
  FOREIGN KEY (`SubGiroID`) REFERENCES `SubGiro`(`SubGiroID`),
  FOREIGN KEY (`ZonaID`) REFERENCES `Zona`(`ZonaID`),
  FOREIGN KEY (`CiudadID`) REFERENCES `Ciudad`(`CiudadID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `PagoID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `CreditoID` INT NOT NULL,
  `CuotaID` INT DEFAULT NULL,
  `PromotorCobradorID` INT DEFAULT NULL,
  `MontoPagado` DECIMAL(12,2) NOT NULL,
  `FechaPago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TipoPago` VARCHAR(20) DEFAULT 'EFECTIVO',
  `EsMora` TINYINT(1) NOT NULL DEFAULT 0,
  `EsPagoAMayor` TINYINT(1) NOT NULL DEFAULT 0,
  `EsPagoForzado` TINYINT(1) NOT NULL DEFAULT 0,
  `EsPagoAutomatico` TINYINT(1) NOT NULL DEFAULT 0,
  `Comentario` VARCHAR(500) DEFAULT NULL,
  `UsuarioRegistro` VARCHAR(100) DEFAULT NULL,
  `FechaCreacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCierre` DATE DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  KEY `IDX_FechaCierre` (`FechaCierre`),
  KEY `idx_es_pago_automatico` (`EsPagoAutomatico`),
  FOREIGN KEY (`PromotorCobradorID`) REFERENCES `PromotorCobrador`(`PromotorCobradorID`),
  FOREIGN KEY (`CreditoID`) REFERENCES `Credito`(`CreditoID`),
  FOREIGN KEY (`CuotaID`) REFERENCES `cuota`(`CuotaID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `roles_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT NOT NULL,
  `role_id` BIGINT NOT NULL,
  PRIMARY KEY (`permission_id`, `role_id`),
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TelefonoNegocio`
--

CREATE TABLE `TelefonoNegocio` (
  `TelefonoNegocioID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `NegocioID` INT NOT NULL,
  `Telefono` VARCHAR(20) NOT NULL,
  `TipoTelefono` VARCHAR(20) DEFAULT 'PRINCIPAL',
  `Observacion` VARCHAR(200) DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`NegocioID`) REFERENCES `Negocio`(`NegocioID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `UserNivelAprobacion`
--

CREATE TABLE `UserNivelAprobacion` (
  `UserNivelAprobacionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `UserID` BIGINT NOT NULL UNIQUE,
  `NivelAprobacionID` INT NOT NULL,
  `FechaAsignacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`NivelAprobacionID`) REFERENCES `NivelAprobacion`(`NivelAprobacionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoExoneracion`
--

CREATE TABLE `TipoExoneracion` (
  `TipoExoneracionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `Codigo` CHAR(1) NOT NULL UNIQUE COMMENT 'P=Pronto Pago, I=Interés, M=Mora',
  `Nombre` VARCHAR(50) NOT NULL,
  `Descripcion` VARCHAR(200) DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `SolicitudExoneracion`
--

CREATE TABLE `SolicitudExoneracion` (
  `SolicitudExoneracionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `CreditoID` INT NOT NULL,
  `TipoExoneracionID` INT NOT NULL,
  `MontoDisponible` DECIMAL(12,2) NOT NULL COMMENT 'Monto total disponible para exonerar',
  `MontoExonerado` DECIMAL(12,2) NOT NULL COMMENT 'Monto a exonerar',
  `Comentario` TEXT NOT NULL,
  `Estado` VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, APROBADO, RECHAZADO',
  `UserSolicitanteID` BIGINT NOT NULL,
  `FechaSolicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `NivelAprobacionRequerido` INT DEFAULT NULL,
  `UserAprobadorID` BIGINT DEFAULT NULL,
  `FechaAprobacion` DATETIME DEFAULT NULL,
  `ComentarioAprobacion` TEXT DEFAULT NULL,
  `PagoGeneradoID` INT DEFAULT NULL COMMENT 'ID del pago automático generado tras aprobación',
  `FechaModificacion` DATETIME DEFAULT NULL,
  `UserModificacionID` BIGINT DEFAULT NULL,
  `Activo` TINYINT(1) NOT NULL DEFAULT 1,
  KEY `IDX_Estado` (`Estado`),
  KEY `IDX_FechaSolicitud` (`FechaSolicitud`),
  KEY `IDX_CreditoID` (`CreditoID`),
  FOREIGN KEY (`CreditoID`) REFERENCES `Credito`(`CreditoID`),
  FOREIGN KEY (`TipoExoneracionID`) REFERENCES `TipoExoneracion`(`TipoExoneracionID`),
  FOREIGN KEY (`NivelAprobacionRequerido`) REFERENCES `NivelAprobacion`(`NivelAprobacionID`),
  FOREIGN KEY (`UserAprobadorID`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`PagoGeneradoID`) REFERENCES `pago`(`PagoID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AprobacionExoneracion`
--

CREATE TABLE `AprobacionExoneracion` (
  `AprobacionExoneracionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `SolicitudExoneracionID` INT NOT NULL,
  `NivelAprobacionID` INT NOT NULL,
  `UserAprobadorID` BIGINT DEFAULT NULL,
  `Estado` VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, APROBADO, RECHAZADO',
  `Comentario` TEXT,
  `FechaAprobacion` DATETIME DEFAULT NULL,
  `FechaCreacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `UQ_AprobacionExoneracion` (`SolicitudExoneracionID`, `NivelAprobacionID`),
  FOREIGN KEY (`SolicitudExoneracionID`) REFERENCES `SolicitudExoneracion`(`SolicitudExoneracionID`) ON DELETE CASCADE,
  FOREIGN KEY (`NivelAprobacionID`) REFERENCES `NivelAprobacion`(`NivelAprobacionID`),
  FOREIGN KEY (`UserAprobadorID`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `HistorialExoneracion`
--

CREATE TABLE `HistorialExoneracion` (
  `HistorialExoneracionID` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `SolicitudExoneracionID` INT NOT NULL,
  `CreditoID` INT NOT NULL,
  `ClienteID` INT NOT NULL,
  `TipoExoneracion` CHAR(1) NOT NULL COMMENT 'P=Pronto Pago, I=Interés, M=Mora',
  `MontoExonerado` DECIMAL(12,2) NOT NULL,
  `FechaExoneracion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioAprobador` VARCHAR(100) DEFAULT NULL,
  `Comentario` TEXT,
  KEY `IDX_CreditoID` (`CreditoID`),
  KEY `IDX_ClienteID` (`ClienteID`),
  KEY `IDX_FechaExoneracion` (`FechaExoneracion`),
  FOREIGN KEY (`SolicitudExoneracionID`) REFERENCES `SolicitudExoneracion`(`SolicitudExoneracionID`),
  FOREIGN KEY (`CreditoID`) REFERENCES `Credito`(`CreditoID`),
  FOREIGN KEY (`ClienteID`) REFERENCES `Cliente`(`ClienteID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Modificación de tabla `pago` para agregar tipo de concepto
--

ALTER TABLE `pago` 
ADD COLUMN `TipoConcepto` CHAR(1) DEFAULT 'C' COMMENT 'C=Cuota, I=Interés, M=Mora, P=Pronto Pago' AFTER `TipoPago`,
ADD KEY `IDX_TipoConcepto` (`TipoConcepto`);

-- --------------------------------------------------------

--
-- Insertar tipos de exoneración
--

INSERT INTO `TipoExoneracion` (`Codigo`, `Nombre`, `Descripcion`) VALUES
('P', 'Pronto Pago', 'Descuento por pagos puntuales'),
('I', 'Interés', 'Exoneración de intereses'),
('M', 'Mora', 'Exoneración de mora acumulada');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;