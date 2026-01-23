-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 22-01-2026 a las 21:41:40
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
CREATE DATABASE IF NOT EXISTS `jvcso1ub_jalud_prestamos` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `jvcso1ub_jalud_prestamos`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AnalisisEconomico`
--

DROP TABLE IF EXISTS `AnalisisEconomico`;
CREATE TABLE `AnalisisEconomico` (
  `AnalisisEconomicoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `CapitalManifestado` decimal(12,2) DEFAULT '0.00',
  `CapitalEstimado` decimal(12,2) DEFAULT '0.00',
  `VentaManifestadaMin` decimal(12,2) DEFAULT '0.00',
  `VentaManifestadaMax` decimal(12,2) DEFAULT '0.00',
  `VentaEstimada` decimal(12,2) DEFAULT '0.00',
  `MontoMaxRecomendado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `FechaAnalisis` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioAnalisis` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioModificacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `AnalisisEconomico`
--

INSERT INTO `AnalisisEconomico` (`AnalisisEconomicoID`, `ClienteID`, `CapitalManifestado`, `CapitalEstimado`, `VentaManifestadaMin`, `VentaManifestadaMax`, `VentaEstimada`, `MontoMaxRecomendado`, `FechaAnalisis`, `UsuarioAnalisis`, `FechaModificacion`, `UsuarioModificacion`, `Activo`) VALUES
(1, 1, 12000.00, 10000.00, 200.00, 700.00, 400.00, 2500.00, '2026-01-22 10:10:41', 'Julio Marco Vilcherrez Criollo', NULL, NULL, 1),
(2, 2, 5000.00, 4500.00, 250.00, 300.00, 250.00, 2000.00, '2026-01-22 10:15:56', 'Julio Marco Vilcherrez Criollo', NULL, NULL, 1),
(3, 3, 5000.00, 4500.00, 250.00, 500.00, 400.00, 1500.00, '2026-01-22 10:20:16', 'Julio Marco Vilcherrez Criollo', NULL, NULL, 1),
(4, 4, 10000.00, 8000.00, 500.00, 1000.00, 800.00, 5000.00, '2026-01-22 10:24:19', 'Julio Marco Vilcherrez Criollo', NULL, NULL, 1),
(5, 5, 20000.00, 15000.00, 1500.00, 2500.00, 2000.00, 10000.00, '2026-01-22 10:27:44', 'Julio Marco Vilcherrez Criollo', NULL, NULL, 1),
(6, 6, 10000.00, 15000.00, 1000.00, 1500.00, 1000.00, 8000.00, '2026-01-22 10:30:10', 'Julio Marco Vilcherrez Criollo', '2026-01-22 10:47:50', 'Julio Marco Vilcherrez Criollo', 0),
(7, 6, 2500.00, 1500.00, 100.00, 200.00, 150.00, 300.00, '2026-01-22 10:47:50', 'Julio Marco Vilcherrez Criollo', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apertura_cierre_dia`
--

DROP TABLE IF EXISTS `apertura_cierre_dia`;
CREATE TABLE `apertura_cierre_dia` (
  `AperturaCierreDiaID` bigint(20) UNSIGNED NOT NULL,
  `Fecha` date NOT NULL,
  `FechaApertura` timestamp NULL DEFAULT NULL,
  `FechaCierre` timestamp NULL DEFAULT NULL,
  `EstadoDia` enum('ABIERTO','CERRADO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CERRADO',
  `UsuarioAperturaID` bigint(20) UNSIGNED DEFAULT NULL,
  `UsuarioCierreID` bigint(20) UNSIGNED DEFAULT NULL,
  `Observaciones` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `apertura_cierre_dia`
--

INSERT INTO `apertura_cierre_dia` (`AperturaCierreDiaID`, `Fecha`, `FechaApertura`, `FechaCierre`, `EstadoDia`, `UsuarioAperturaID`, `UsuarioCierreID`, `Observaciones`, `created_at`, `updated_at`) VALUES
(1, '2026-01-20', '2026-01-21 03:02:44', '2026-01-21 02:55:21', 'ABIERTO', 5, 5, NULL, '2026-01-20 21:21:24', '2026-01-21 03:02:44'),
(2, '2026-01-21', '2026-01-21 21:52:51', NULL, 'ABIERTO', 1, NULL, NULL, '2026-01-21 21:52:51', '2026-01-21 21:52:51'),
(3, '2026-01-22', '2026-01-22 14:41:42', NULL, 'ABIERTO', 5, NULL, NULL, '2026-01-22 14:41:42', '2026-01-22 14:41:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AprobacionProposicion`
--

DROP TABLE IF EXISTS `AprobacionProposicion`;
CREATE TABLE `AprobacionProposicion` (
  `AprobacionProposicionID` int(11) NOT NULL,
  `ProposicionCreditoID` int(11) NOT NULL,
  `NivelAprobacionID` int(11) NOT NULL,
  `UserAprobadorID` bigint(20) DEFAULT NULL,
  `Estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `Comentario` text COLLATE utf8mb4_unicode_ci,
  `FechaAprobacion` datetime DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `AprobacionProposicion`
--

INSERT INTO `AprobacionProposicion` (`AprobacionProposicionID`, `ProposicionCreditoID`, `NivelAprobacionID`, `UserAprobadorID`, `Estado`, `Comentario`, `FechaAprobacion`, `FechaCreacion`) VALUES
(42, 1, 1, NULL, 'PENDIENTE', NULL, NULL, '2026-01-22 10:39:04'),
(43, 2, 1, NULL, 'PENDIENTE', NULL, NULL, '2026-01-22 10:41:16'),
(44, 3, 1, NULL, 'PENDIENTE', NULL, NULL, '2026-01-22 10:43:52'),
(45, 4, 1, NULL, 'PENDIENTE', NULL, NULL, '2026-01-22 10:45:14'),
(46, 5, 1, NULL, 'PENDIENTE', NULL, NULL, '2026-01-22 10:46:14'),
(47, 6, 1, NULL, 'PENDIENTE', NULL, NULL, '2026-01-22 10:49:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ciudad`
--

DROP TABLE IF EXISTS `Ciudad`;
CREATE TABLE `Ciudad` (
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Ciudad`
--

INSERT INTO `Ciudad` (`CiudadID`, `Nombre`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'CHICLAYO', 1, '2025-12-20 12:26:03', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Cliente`
--

DROP TABLE IF EXISTS `Cliente`;
CREATE TABLE `Cliente` (
  `ClienteID` int(11) NOT NULL,
  `DNI` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombresApellidos` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Sexo` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaNacimiento` date DEFAULT NULL,
  `Estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NO OBSERVADO',
  `ConyugeDNI` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ConyugeNombresApellidos` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Domicilio` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `TasaID` int(11) DEFAULT NULL,
  `GaranteID` int(11) DEFAULT NULL,
  `Observaciones` text COLLATE utf8mb4_unicode_ci,
  `PromotorCobradorID` int(11) DEFAULT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioRegistro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UsuarioModificacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Cliente`
--

INSERT INTO `Cliente` (`ClienteID`, `DNI`, `NombresApellidos`, `Sexo`, `FechaNacimiento`, `Estado`, `ConyugeDNI`, `ConyugeNombresApellidos`, `Domicilio`, `TasaID`, `GaranteID`, `Observaciones`, `PromotorCobradorID`, `FechaRegistro`, `FechaModificacion`, `UsuarioRegistro`, `UsuarioModificacion`, `Activo`) VALUES
(1, '72032352', 'NATALIE NICOLE FALLAQUE BARTUREN', 'F', NULL, 'NO OBSERVADO', NULL, NULL, 'PPJJ SAN ANTONIO -CALLE SAN CARLOS 172', 1, NULL, NULL, NULL, '2026-01-22 10:10:41', NULL, 'Julio Marco Vilcherrez Criollo', NULL, 1),
(2, '72157854', 'KIMBERLY INGA FERNANDEZ', 'F', NULL, 'NO OBSERVADO', NULL, NULL, 'PUEBLO JOVEN SAN JUAN MZ I LOTE 03', 1, NULL, NULL, NULL, '2026-01-22 10:15:56', NULL, 'Julio Marco Vilcherrez Criollo', NULL, 1),
(3, '80434439', 'PEDRO WILBER BRAVO DELGADO', 'M', NULL, 'NO OBSERVADO', NULL, NULL, 'MZ A LT 27 SANTO DOMINGO', 1, NULL, NULL, NULL, '2026-01-22 10:20:16', NULL, 'Julio Marco Vilcherrez Criollo', NULL, 1),
(4, '80612585', 'NILA NAVARRO NUÑEZ', 'F', NULL, 'NO OBSERVADO', NULL, NULL, 'PJ VILLA HERMOSA SECTOR IV MZ Q2 LOTE 19- CALLE LOS COCOS N 185', 1, NULL, NULL, NULL, '2026-01-22 10:24:19', NULL, 'Julio Marco Vilcherrez Criollo', NULL, 1),
(5, '76258031', 'ELVIA ELIZABETH DIAZ HERRERA', 'F', NULL, 'NO OBSERVADO', NULL, NULL, 'CALLE ECUADOR 738 CPM BARSALLO J.L.O', 1, NULL, NULL, NULL, '2026-01-22 10:27:44', NULL, 'Julio Marco Vilcherrez Criollo', NULL, 1),
(6, '76355145', 'NICOLE STEFANY HUAMAN VALENCIA', 'F', NULL, 'NO OBSERVADO', NULL, NULL, 'MZ K LT 25 CPM FANNY ABANTO - CHICLAYO', 1, NULL, NULL, NULL, '2026-01-22 10:30:10', NULL, 'Julio Marco Vilcherrez Criollo', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Credito`
--

DROP TABLE IF EXISTS `Credito`;
CREATE TABLE `Credito` (
  `CreditoID` int(11) NOT NULL,
  `ProposicionCreditoID` int(11) NOT NULL,
  `TipoPagoID` int(11) NOT NULL,
  `ComentarioGeneracion` text COLLATE utf8mb4_unicode_ci,
  `FechaGeneracion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaInicio` date DEFAULT NULL COMMENT 'Fecha de inicio del crédito',
  `FechaVencimiento` date DEFAULT NULL COMMENT 'Fecha de vencimiento del crédito (vencimiento de última cuota)',
  `UserGeneracionID` bigint(20) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuota`
--

DROP TABLE IF EXISTS `cuota`;
CREATE TABLE `cuota` (
  `CuotaID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `NumeroCuota` int(11) NOT NULL,
  `FechaVencimiento` date NOT NULL,
  `MontoCuota` decimal(12,2) NOT NULL,
  `Estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `DiasAtraso` int(11) DEFAULT '0',
  `MontoMora` decimal(12,2) DEFAULT '0.00',
  `FechaPago` datetime DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `DocumentoCliente`
--

DROP TABLE IF EXISTS `DocumentoCliente`;
CREATE TABLE `DocumentoCliente` (
  `DocumentoClienteID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `TipoDocumento` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `RutaArchivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `NombreOriginal` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TamanioArchivo` bigint(20) DEFAULT NULL,
  `Extension` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Observaciones` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UsuarioRegistro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioModificacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `EvaluacionCredito`
--

DROP TABLE IF EXISTS `EvaluacionCredito`;
CREATE TABLE `EvaluacionCredito` (
  `EvaluacionCreditoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `Comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioRegistro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `EvaluacionCredito`
--

INSERT INTO `EvaluacionCredito` (`EvaluacionCreditoID`, `ClienteID`, `Comentario`, `FechaRegistro`, `UsuarioRegistro`) VALUES
(1, 1, 'CLIENTE HA TENIDO PROBLEMAS DE EXTORCION , HA SOLICITADO EL MONTO DE 1200, ', '2026-01-22 10:31:37', 'Julio Marco Vilcherrez Criollo'),
(2, 2, 'CLIENTE TUVO PROBLEMAS FAMILIAREZ FALELCIMIENTO DE SU MAMA,HA PAGADO DE A POCOS,QUIERE RENOVAR Y EMPEZAR NUEVO CREDITO', '2026-01-22 10:32:19', 'Julio Marco Vilcherrez Criollo'),
(3, 3, 'renovadora de calzado. aprobado el monto de 1000 por supervisor omar', '2026-01-22 10:33:32', 'Julio Marco Vilcherrez Criollo'),
(4, 4, 'ultima oportunidad, debe mejorar sus pagos, autorizado supervisor omar el monto 1000', '2026-01-22 10:34:21', 'Julio Marco Vilcherrez Criollo'),
(5, 5, 'CLIENTE FUERA DE RANGO,PAGO DIARIO,NEGOCIO ABASTECIDO ', '2026-01-22 10:35:21', 'Julio Marco Vilcherrez Criollo'),
(6, 6, 'cliente buena forma de pagos, aprobado supervisor omar el monto de 1500', '2026-01-22 10:36:11', 'Julio Marco Vilcherrez Criollo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Giro`
--

DROP TABLE IF EXISTS `Giro`;
CREATE TABLE `Giro` (
  `GiroID` int(11) NOT NULL,
  `Codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Giro`
--

INSERT INTO `Giro` (`GiroID`, `Codigo`, `Descripcion`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'G001', 'Abarrotes y Especies', 1, '2025-12-20 12:27:09', NULL),
(2, 'G002', 'Artículos de Vestir y Personal ', 1, '2025-12-20 12:28:24', NULL),
(3, 'G003', 'Restaurantes  - Comida', 1, '2025-12-20 12:31:09', NULL),
(4, 'G004', 'Venta De Carnes Y Pescado', 1, '2025-12-20 12:31:30', NULL),
(5, 'G005', 'Ferretería', 1, '2025-12-20 12:31:54', NULL),
(6, 'G006', 'Venta De Telas', 1, '2025-12-20 12:32:26', NULL),
(7, 'G007', 'Zapatería', 1, '2025-12-20 12:32:45', NULL),
(8, 'G008', 'Zapatería', 1, '2025-12-20 12:34:17', NULL),
(9, 'G009', 'Librería', 1, '2025-12-20 12:34:41', NULL),
(10, 'G010', 'Locería Y Regalos', 1, '2025-12-20 12:35:05', NULL),
(11, 'G011', 'Piñatería', 1, '2025-12-20 12:35:28', NULL),
(14, 'G012', 'Cebichería', 1, '2025-12-20 12:36:24', NULL),
(15, 'G013', 'Venta De Jugos', 1, '2025-12-20 12:37:45', NULL),
(16, 'G014', 'Confecciones Y Estampados', 1, '2025-12-20 12:38:03', NULL),
(17, 'G015', 'Verduras', 1, '2025-12-20 12:38:35', NULL),
(18, 'G016', 'Frutas', 1, '2025-12-20 12:38:49', NULL),
(19, 'G017', 'Otros', 1, '2025-12-20 12:39:05', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(11) DEFAULT NULL,
  `available_at` int(11) NOT NULL,
  `created_at` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` text COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `accion` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `modelo` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  `modelo_id` bigint(20) DEFAULT NULL,
  `old_values` text COLLATE utf8_unicode_ci,
  `new_values` text COLLATE utf8_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `machine_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Volcado de datos para la tabla `logs`