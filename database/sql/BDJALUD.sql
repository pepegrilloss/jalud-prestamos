-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 10-03-2026 a las 19:07:43
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
-- Estructura de tabla para la tabla `AnalisisEconomico`
--

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
  `FechaCierre` datetime DEFAULT NULL,
  `UsuarioAnalisis` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioModificacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apertura_cierre_dia`
--

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
  `updated_at` timestamp NULL DEFAULT NULL,
  `abierto_flag` int(11) GENERATED ALWAYS AS ((case when (`EstadoDia` = 'ABIERTO') then 1 else NULL end)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AprobacionExoneracion`
--

CREATE TABLE `AprobacionExoneracion` (
  `AprobacionExoneracionID` int(11) NOT NULL,
  `SolicitudExoneracionID` int(11) NOT NULL,
  `NivelAprobacionID` int(11) NOT NULL,
  `UserAprobadorID` bigint(20) DEFAULT NULL,
  `Estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, APROBADO, RECHAZADO',
  `Comentario` text COLLATE utf8mb4_unicode_ci,
  `FechaAprobacion` datetime DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `AprobacionProposicion`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ciudad`
--

CREATE TABLE `Ciudad` (
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Cliente`
--

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
  `TasaMoraID` int(11) DEFAULT NULL,
  `GaranteID` int(11) DEFAULT NULL,
  `Observaciones` text COLLATE utf8mb4_unicode_ci,
  `PromotorCobradorID` int(11) DEFAULT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioRegistro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UsuarioModificacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCierre` date DEFAULT NULL COMMENT 'Fecha en que se cerró este registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Compra`
--

CREATE TABLE `Compra` (
  `CompraID` bigint(20) UNSIGNED NOT NULL,
  `TipoComprobanteID` bigint(20) UNSIGNED NOT NULL,
  `Serie` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaEmision` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `NombreProveedor` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ProductoServicio` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Cantidad` decimal(10,2) NOT NULL,
  `PrecioUnitario` decimal(12,2) NOT NULL,
  `Total` decimal(12,2) NOT NULL,
  `Observaciones` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Credito`
--

CREATE TABLE `Credito` (
  `CreditoID` int(11) NOT NULL,
  `ProposicionCreditoID` int(11) NOT NULL,
  `TipoPagoID` int(11) NOT NULL,
  `ComentarioGeneracion` text COLLATE utf8mb4_unicode_ci,
  `FechaGeneracion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaInicio` date DEFAULT NULL COMMENT 'Fecha de inicio del crédito',
  `FechaVencimiento` date DEFAULT NULL COMMENT 'Fecha de vencimiento del crédito (vencimiento de última cuota)',
  `UserGeneracionID` bigint(20) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCierre` date DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  `EstatusCreditoFinal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVO' COMMENT 'ACTIVO, SALDADO',
  `FechaSaldamiento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuota`
--

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
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCierre` date DEFAULT NULL COMMENT 'Fecha en que se cerró este registro'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `DocumentoCliente`
--

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

CREATE TABLE `EvaluacionCredito` (
  `EvaluacionCreditoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `Comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioRegistro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaCierre` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Gasto`
--

CREATE TABLE `Gasto` (
  `GastoID` bigint(20) UNSIGNED NOT NULL,
  `TipoComprobanteID` bigint(20) UNSIGNED NOT NULL,
  `Numero` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `FechaEmision` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `NombreProveedor` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MotivoID` bigint(20) UNSIGNED NOT NULL,
  `MetodoGasto` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Total` decimal(12,2) NOT NULL,
  `Observaciones` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Giro`
--

CREATE TABLE `Giro` (
  `GiroID` int(11) NOT NULL,
  `Codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `HistorialExoneracion`
--

CREATE TABLE `HistorialExoneracion` (
  `HistorialExoneracionID` int(11) NOT NULL,
  `SolicitudExoneracionID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `TipoExoneracion` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'P=Pronto Pago, I=Interés, M=Mora',
  `MontoExonerado` decimal(12,2) NOT NULL,
  `FechaExoneracion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UsuarioAprobador` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Comentario` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mora`
--

CREATE TABLE `mora` (
  `MoraID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `FechaMora` date NOT NULL,
  `SaldoPendiente` decimal(12,2) NOT NULL COMMENT 'Saldo sobre el que se calculó la mora',
  `PorcentajeMora` decimal(5,2) NOT NULL COMMENT 'Porcentaje de mora aplicado del cliente',
  `MontoMora` decimal(12,2) NOT NULL COMMENT 'Monto de mora calculado para este día',
  `MoraAcumulada` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Mora total acumulada hasta esa fecha',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro diario de mora automática por vencimiento';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Motivo`
--

CREATE TABLE `Motivo` (
  `MotivoID` bigint(20) UNSIGNED NOT NULL,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Negocio`
--

CREATE TABLE `Negocio` (
  `NegocioID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `DireccionNegocio` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CiudadID` int(11) DEFAULT NULL,
  `ZonaID` int(11) DEFAULT NULL,
  `Antiguedad` decimal(5,2) DEFAULT NULL,
  `GiroID` int(11) DEFAULT NULL,
  `SubGiroID` int(11) DEFAULT NULL,
  `Ubicacion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `NivelAprobacion`
--

CREATE TABLE `NivelAprobacion` (
  `NivelAprobacionID` int(11) NOT NULL,
  `Nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MontoMinimo` decimal(12,2) NOT NULL,
  `MontoMaximo` decimal(12,2) NOT NULL,
  `Orden` int(11) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `PagoID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `CuotaID` int(11) DEFAULT NULL,
  `PromotorCobradorID` int(11) DEFAULT NULL,
  `MontoPagado` decimal(12,2) NOT NULL,
  `FechaPago` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `TipoPago` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EFECTIVO' COMMENT 'Método de pago: EFECTIVO, YAPE_PLIN, TRANSFERENCIA_BANCARIA',
  `TipoConcepto` char(1) COLLATE utf8mb4_unicode_ci DEFAULT 'C' COMMENT 'C=Cuota, I=Interés, M=Mora, P=Pronto Pago',
  `EsMora` tinyint(1) NOT NULL DEFAULT '0',
  `EsPagoAMayor` tinyint(1) NOT NULL DEFAULT '0',
  `EsPagoForzado` tinyint(1) NOT NULL DEFAULT '0',
  `EsPagoAutomatico` tinyint(1) NOT NULL DEFAULT '0',
  `Comentario` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `UsuarioRegistro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaCreacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCierre` date DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  `EsPagoInicial` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `PromotorCobrador`
--

CREATE TABLE `PromotorCobrador` (
  `PromotorCobradorID` int(11) NOT NULL,
  `Codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `CiudadID` int(11) NOT NULL,
  `ZonaID` int(11) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ProposicionCredito`
--

CREATE TABLE `ProposicionCredito` (
  `ProposicionCreditoID` int(11) NOT NULL,
  `CodigoCredito` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `CodigoCliente` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoCreditoID` int(11) NOT NULL,
  `MontoTotal` decimal(12,2) NOT NULL,
  `TasaID` int(11) NOT NULL,
  `TasaInteres` decimal(5,2) NOT NULL,
  `Plazo` int(11) NOT NULL,
  `NumeroCuotas` int(11) NOT NULL,
  `MontoCuota` decimal(12,2) NOT NULL,
  `MontoInteres` decimal(12,2) NOT NULL,
  `TasaMora` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ZonaID` int(11) DEFAULT NULL,
  `Observaciones` text COLLATE utf8mb4_unicode_ci,
  `UserProponenteID` bigint(20) NOT NULL,
  `FechaPropuesta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `NivelAprobacionRequerido` int(11) DEFAULT NULL,
  `UserAprobadorID` bigint(20) DEFAULT NULL,
  `FechaAprobacion` datetime DEFAULT NULL,
  `ComentarioAprobacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FechaDesembolso` datetime DEFAULT NULL,
  `UserDesembolsoID` bigint(20) DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `UserModificacionID` bigint(20) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCierre` date DEFAULT NULL COMMENT 'Fecha en que se cerró este registro',
  `EsRefinanciamiento` tinyint(1) NOT NULL DEFAULT '0',
  `FueRefinanciada` tinyint(1) NOT NULL DEFAULT '0',
  `ProposicionCreditoAnteriorID` int(11) DEFAULT NULL,
  `MontoTotalPagar` decimal(12,2) DEFAULT '0.00',
  `SaldoPendiente` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `SolicitudExoneracion`
--

CREATE TABLE `SolicitudExoneracion` (
  `SolicitudExoneracionID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `TipoExoneracionID` int(11) NOT NULL,
  `MontoDisponible` decimal(12,2) NOT NULL COMMENT 'Monto total disponible para exonerar',
  `MontoExonerado` decimal(12,2) NOT NULL COMMENT 'Monto a exonerar',
  `Comentario` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `Estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE, APROBADO, RECHAZADO',
  `UserSolicitanteID` bigint(20) NOT NULL,
  `FechaSolicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `NivelAprobacionRequerido` int(11) DEFAULT NULL,
  `UserAprobadorID` bigint(20) DEFAULT NULL,
  `FechaAprobacion` datetime DEFAULT NULL,
  `ComentarioAprobacion` text COLLATE utf8mb4_unicode_ci,
  `PagoGeneradoID` int(11) DEFAULT NULL COMMENT 'ID del pago automático generado tras aprobación',
  `FechaModificacion` datetime DEFAULT NULL,
  `UserModificacionID` bigint(20) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `SubGiro`
--

CREATE TABLE `SubGiro` (
  `SubGiroID` int(11) NOT NULL,
  `GiroID` int(11) NOT NULL,
  `Descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Tasa`
--

CREATE TABLE `Tasa` (
  `TasaID` int(11) NOT NULL,
  `Nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Valor` decimal(5,2) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL,
  `Dias` int(11) NOT NULL,
  `Cuotas` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TasaMora`
--

CREATE TABLE `TasaMora` (
  `TasaMoraID` int(11) NOT NULL,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Porcentaje` decimal(5,2) NOT NULL COMMENT 'Porcentaje de mora (ej: 0.5, 1.0, 2.5)',
  `Descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TelefonoNegocio`
--

CREATE TABLE `TelefonoNegocio` (
  `TelefonoNegocioID` int(11) NOT NULL,
  `NegocioID` int(11) NOT NULL,
  `Telefono` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `TipoTelefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'PRINCIPAL',
  `Observacion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoComprobante`
--

CREATE TABLE `TipoComprobante` (
  `TipoComprobanteID` bigint(20) UNSIGNED NOT NULL,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoCredito`
--

CREATE TABLE `TipoCredito` (
  `TipoCreditoID` int(11) NOT NULL,
  `Codigo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoExoneracion`
--

CREATE TABLE `TipoExoneracion` (
  `TipoExoneracionID` int(11) NOT NULL,
  `Codigo` char(1) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'P=Pronto Pago, I=Interés, M=Mora',
  `Nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `TipoPago`
--

CREATE TABLE `TipoPago` (
  `TipoPagoID` int(11) NOT NULL,
  `Nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `UserNivelAprobacion`
--

CREATE TABLE `UserNivelAprobacion` (
  `UserNivelAprobacionID` int(11) NOT NULL,
  `UserID` bigint(20) NOT NULL,
  `NivelAprobacionID` int(11) NOT NULL,
  `FechaAsignacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `Activo` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Zona`
--

CREATE TABLE `Zona` (
  `ZonaID` int(11) NOT NULL,
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `FechaCreacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `AnalisisEconomico`
--
ALTER TABLE `AnalisisEconomico`
  ADD PRIMARY KEY (`AnalisisEconomicoID`),
  ADD KEY `FK_AnalisisEconomico_Cliente` (`ClienteID`);

--
-- Indices de la tabla `apertura_cierre_dia`
--
ALTER TABLE `apertura_cierre_dia`
  ADD PRIMARY KEY (`AperturaCierreDiaID`),
  ADD UNIQUE KEY `Fecha` (`Fecha`),
  ADD UNIQUE KEY `unique_abierto` (`abierto_flag`);

--
-- Indices de la tabla `AprobacionExoneracion`
--
ALTER TABLE `AprobacionExoneracion`
  ADD PRIMARY KEY (`AprobacionExoneracionID`),
  ADD UNIQUE KEY `UQ_AprobacionExoneracion` (`SolicitudExoneracionID`,`NivelAprobacionID`),
  ADD KEY `NivelAprobacionID` (`NivelAprobacionID`),
  ADD KEY `UserAprobadorID` (`UserAprobadorID`);

--
-- Indices de la tabla `AprobacionProposicion`
--
ALTER TABLE `AprobacionProposicion`
  ADD PRIMARY KEY (`AprobacionProposicionID`),
  ADD UNIQUE KEY `UQ_AprobacionProposicion` (`ProposicionCreditoID`,`NivelAprobacionID`),
  ADD KEY `FK_AprobacionProposicion_Nivel` (`NivelAprobacionID`),
  ADD KEY `FK_AprobacionProposicion_Usuario` (`UserAprobadorID`);

--
-- Indices de la tabla `Ciudad`
--
ALTER TABLE `Ciudad`
  ADD PRIMARY KEY (`CiudadID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `Cliente`
--
ALTER TABLE `Cliente`
  ADD PRIMARY KEY (`ClienteID`),
  ADD UNIQUE KEY `DNI` (`DNI`),
  ADD KEY `FK_Cliente_Garante` (`GaranteID`),
  ADD KEY `FK_Cliente_PromotorCobrador` (`PromotorCobradorID`),
  ADD KEY `FK_Cliente_Tasa` (`TasaID`),
  ADD KEY `IDX_FechaCierre` (`FechaCierre`),
  ADD KEY `TasaMoraID` (`TasaMoraID`);

--
-- Indices de la tabla `Compra`
--
ALTER TABLE `Compra`
  ADD PRIMARY KEY (`CompraID`),
  ADD KEY `tipo_comprobante_idx` (`TipoComprobanteID`),
  ADD KEY `fecha_idx` (`FechaEmision`),
  ADD KEY `proveedor_idx` (`NombreProveedor`),
  ADD KEY `idx_compra_num_serie` (`Serie`,`Numero`),
  ADD KEY `idx_compra_producto` (`ProductoServicio`);

--
-- Indices de la tabla `Credito`
--
ALTER TABLE `Credito`
  ADD PRIMARY KEY (`CreditoID`),
  ADD UNIQUE KEY `ProposicionCreditoID` (`ProposicionCreditoID`),
  ADD KEY `FK_Credito_TipoPago` (`TipoPagoID`),
  ADD KEY `IDX_FechaInicio` (`FechaInicio`),
  ADD KEY `IDX_FechaVencimiento` (`FechaVencimiento`),
  ADD KEY `IDX_FechaCierre` (`FechaCierre`),
  ADD KEY `IDX_EstatusCreditoFinal` (`EstatusCreditoFinal`);

--
-- Indices de la tabla `cuota`
--
ALTER TABLE `cuota`
  ADD PRIMARY KEY (`CuotaID`),
  ADD KEY `FK_Cuota_Credito` (`CreditoID`),
  ADD KEY `IDX_FechaCierre` (`FechaCierre`);

--
-- Indices de la tabla `DocumentoCliente`
--
ALTER TABLE `DocumentoCliente`
  ADD PRIMARY KEY (`DocumentoClienteID`),
  ADD KEY `FK_DocumentoCliente_Cliente` (`ClienteID`);

--
-- Indices de la tabla `EvaluacionCredito`
--
ALTER TABLE `EvaluacionCredito`
  ADD PRIMARY KEY (`EvaluacionCreditoID`),
  ADD KEY `FK_EvaluacionCredito_Cliente` (`ClienteID`);

--
-- Indices de la tabla `Gasto`
--
ALTER TABLE `Gasto`
  ADD PRIMARY KEY (`GastoID`),
  ADD KEY `tipo_comprobante_idx` (`TipoComprobanteID`),
  ADD KEY `motivo_idx` (`MotivoID`),
  ADD KEY `fecha_idx` (`FechaEmision`),
  ADD KEY `proveedor_idx` (`NombreProveedor`),
  ADD KEY `idx_gasto_numero` (`Numero`),
  ADD KEY `idx_gasto_metodo` (`MetodoGasto`);

--
-- Indices de la tabla `Giro`
--
ALTER TABLE `Giro`
  ADD PRIMARY KEY (`GiroID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

--
-- Indices de la tabla `HistorialExoneracion`
--
ALTER TABLE `HistorialExoneracion`
  ADD PRIMARY KEY (`HistorialExoneracionID`),
  ADD KEY `IDX_CreditoID` (`CreditoID`),
  ADD KEY `IDX_ClienteID` (`ClienteID`),
  ADD KEY `IDX_FechaExoneracion` (`FechaExoneracion`),
  ADD KEY `SolicitudExoneracionID` (`SolicitudExoneracionID`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `mora`
--
ALTER TABLE `mora`
  ADD PRIMARY KEY (`MoraID`),
  ADD UNIQUE KEY `unique_credito_fecha` (`CreditoID`,`FechaMora`),
  ADD KEY `idx_creditoId` (`CreditoID`),
  ADD KEY `idx_fechaMora` (`FechaMora`);

--
-- Indices de la tabla `Motivo`
--
ALTER TABLE `Motivo`
  ADD PRIMARY KEY (`MotivoID`),
  ADD UNIQUE KEY `nombre_unique` (`Nombre`);

--
-- Indices de la tabla `Negocio`
--
ALTER TABLE `Negocio`
  ADD PRIMARY KEY (`NegocioID`),
  ADD KEY `FK_Negocio_Cliente` (`ClienteID`),
  ADD KEY `FK_Negocio_Giro` (`GiroID`),
  ADD KEY `FK_Negocio_SubGiro` (`SubGiroID`),
  ADD KEY `FK_Negocio_Zona` (`ZonaID`),
  ADD KEY `FK_Negocio_Ciudad` (`CiudadID`);

--
-- Indices de la tabla `NivelAprobacion`
--
ALTER TABLE `NivelAprobacion`
  ADD PRIMARY KEY (`NivelAprobacionID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `pago`
--
ALTER TABLE `pago`
  ADD PRIMARY KEY (`PagoID`),
  ADD KEY `FK_Pago_Cobrador` (`PromotorCobradorID`),
  ADD KEY `FK_Pago_Credito` (`CreditoID`),
  ADD KEY `FK_Pago_Cuota` (`CuotaID`),
  ADD KEY `IDX_FechaCierre` (`FechaCierre`),
  ADD KEY `idx_es_pago_automatico` (`EsPagoAutomatico`),
  ADD KEY `IDX_TipoConcepto` (`TipoConcepto`),
  ADD KEY `idx_tipo_pago` (`TipoPago`),
  ADD KEY `idx_es_pago_inicial` (`EsPagoInicial`);

--
-- Indices de la tabla `PromotorCobrador`
--
ALTER TABLE `PromotorCobrador`
  ADD PRIMARY KEY (`PromotorCobradorID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`),
  ADD KEY `fk_promotor_ciudad` (`CiudadID`),
  ADD KEY `fk_promotor_zona` (`ZonaID`);

--
-- Indices de la tabla `ProposicionCredito`
--
ALTER TABLE `ProposicionCredito`
  ADD PRIMARY KEY (`ProposicionCreditoID`),
  ADD UNIQUE KEY `CodigoCredito` (`CodigoCredito`),
  ADD KEY `FK_ProposicionCredito_Cliente` (`ClienteID`),
  ADD KEY `FK_ProposicionCredito_NivelAprobacion` (`NivelAprobacionRequerido`),
  ADD KEY `FK_ProposicionCredito_Tasa` (`TasaID`),
  ADD KEY `FK_ProposicionCredito_TipoCredito` (`TipoCreditoID`),
  ADD KEY `FK_ProposicionCredito_Zona` (`ZonaID`),
  ADD KEY `IDX_EsRefinanciamiento` (`EsRefinanciamiento`),
  ADD KEY `IDX_ProposicionCreditoAnteriorID` (`ProposicionCreditoAnteriorID`),
  ADD KEY `IDX_FueRefinanciada` (`FueRefinanciada`),
  ADD KEY `IDX_FechaCierre` (`FechaCierre`);

--
-- Indices de la tabla `SolicitudExoneracion`
--
ALTER TABLE `SolicitudExoneracion`
  ADD PRIMARY KEY (`SolicitudExoneracionID`),
  ADD KEY `IDX_Estado` (`Estado`),
  ADD KEY `IDX_FechaSolicitud` (`FechaSolicitud`),
  ADD KEY `CreditoID` (`CreditoID`),
  ADD KEY `TipoExoneracionID` (`TipoExoneracionID`),
  ADD KEY `NivelAprobacionRequerido` (`NivelAprobacionRequerido`),
  ADD KEY `UserAprobadorID` (`UserAprobadorID`),
  ADD KEY `PagoGeneradoID` (`PagoGeneradoID`);

--
-- Indices de la tabla `SubGiro`
--
ALTER TABLE `SubGiro`
  ADD PRIMARY KEY (`SubGiroID`),
  ADD UNIQUE KEY `UQ_SubGiro_Giro` (`GiroID`,`Descripcion`);

--
-- Indices de la tabla `Tasa`
--
ALTER TABLE `Tasa`
  ADD PRIMARY KEY (`TasaID`);

--
-- Indices de la tabla `TasaMora`
--
ALTER TABLE `TasaMora`
  ADD PRIMARY KEY (`TasaMoraID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`),
  ADD KEY `IDX_Activo` (`Activo`);

--
-- Indices de la tabla `TelefonoNegocio`
--
ALTER TABLE `TelefonoNegocio`
  ADD PRIMARY KEY (`TelefonoNegocioID`),
  ADD KEY `FK_TelefonoNegocio_Negocio` (`NegocioID`);

--
-- Indices de la tabla `TipoComprobante`
--
ALTER TABLE `TipoComprobante`
  ADD PRIMARY KEY (`TipoComprobanteID`),
  ADD UNIQUE KEY `nombre_unique` (`Nombre`);

--
-- Indices de la tabla `TipoCredito`
--
ALTER TABLE `TipoCredito`
  ADD PRIMARY KEY (`TipoCreditoID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

--
-- Indices de la tabla `TipoExoneracion`
--
ALTER TABLE `TipoExoneracion`
  ADD PRIMARY KEY (`TipoExoneracionID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

--
-- Indices de la tabla `TipoPago`
--
ALTER TABLE `TipoPago`
  ADD PRIMARY KEY (`TipoPagoID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `UserNivelAprobacion`
--
ALTER TABLE `UserNivelAprobacion`
  ADD PRIMARY KEY (`UserNivelAprobacionID`),
  ADD UNIQUE KEY `UserID` (`UserID`),
  ADD KEY `FK_UserNivel_NivelAprobacion` (`NivelAprobacionID`);

--
-- Indices de la tabla `Zona`
--
ALTER TABLE `Zona`
  ADD PRIMARY KEY (`ZonaID`),
  ADD UNIQUE KEY `UQ_Zona_Ciudad` (`CiudadID`,`Nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `AnalisisEconomico`
--
ALTER TABLE `AnalisisEconomico`
  MODIFY `AnalisisEconomicoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `apertura_cierre_dia`
--
ALTER TABLE `apertura_cierre_dia`
  MODIFY `AperturaCierreDiaID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `AprobacionExoneracion`
--
ALTER TABLE `AprobacionExoneracion`
  MODIFY `AprobacionExoneracionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `AprobacionProposicion`
--
ALTER TABLE `AprobacionProposicion`
  MODIFY `AprobacionProposicionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Ciudad`
--
ALTER TABLE `Ciudad`
  MODIFY `CiudadID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Cliente`
--
ALTER TABLE `Cliente`
  MODIFY `ClienteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Compra`
--
ALTER TABLE `Compra`
  MODIFY `CompraID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Credito`
--
ALTER TABLE `Credito`
  MODIFY `CreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuota`
--
ALTER TABLE `cuota`
  MODIFY `CuotaID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `DocumentoCliente`
--
ALTER TABLE `DocumentoCliente`
  MODIFY `DocumentoClienteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `EvaluacionCredito`
--
ALTER TABLE `EvaluacionCredito`
  MODIFY `EvaluacionCreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Gasto`
--
ALTER TABLE `Gasto`
  MODIFY `GastoID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Giro`
--
ALTER TABLE `Giro`
  MODIFY `GiroID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `HistorialExoneracion`
--
ALTER TABLE `HistorialExoneracion`
  MODIFY `HistorialExoneracionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mora`
--
ALTER TABLE `mora`
  MODIFY `MoraID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Motivo`
--
ALTER TABLE `Motivo`
  MODIFY `MotivoID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Negocio`
--
ALTER TABLE `Negocio`
  MODIFY `NegocioID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `NivelAprobacion`
--
ALTER TABLE `NivelAprobacion`
  MODIFY `NivelAprobacionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pago`
--
ALTER TABLE `pago`
  MODIFY `PagoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `PromotorCobrador`
--
ALTER TABLE `PromotorCobrador`
  MODIFY `PromotorCobradorID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ProposicionCredito`
--
ALTER TABLE `ProposicionCredito`
  MODIFY `ProposicionCreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `SolicitudExoneracion`
--
ALTER TABLE `SolicitudExoneracion`
  MODIFY `SolicitudExoneracionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `SubGiro`
--
ALTER TABLE `SubGiro`
  MODIFY `SubGiroID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Tasa`
--
ALTER TABLE `Tasa`
  MODIFY `TasaID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TasaMora`
--
ALTER TABLE `TasaMora`
  MODIFY `TasaMoraID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TelefonoNegocio`
--
ALTER TABLE `TelefonoNegocio`
  MODIFY `TelefonoNegocioID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TipoComprobante`
--
ALTER TABLE `TipoComprobante`
  MODIFY `TipoComprobanteID` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TipoCredito`
--
ALTER TABLE `TipoCredito`
  MODIFY `TipoCreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TipoExoneracion`
--
ALTER TABLE `TipoExoneracion`
  MODIFY `TipoExoneracionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `TipoPago`
--
ALTER TABLE `TipoPago`
  MODIFY `TipoPagoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `UserNivelAprobacion`
--
ALTER TABLE `UserNivelAprobacion`
  MODIFY `UserNivelAprobacionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Zona`
--
ALTER TABLE `Zona`
  MODIFY `ZonaID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `AnalisisEconomico`
--
ALTER TABLE `AnalisisEconomico`
  ADD CONSTRAINT `FK_AnalisisEconomico_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `Cliente` (`ClienteID`);

--
-- Filtros para la tabla `AprobacionExoneracion`
--
ALTER TABLE `AprobacionExoneracion`
  ADD CONSTRAINT `AprobacionExoneracion_ibfk_1` FOREIGN KEY (`SolicitudExoneracionID`) REFERENCES `SolicitudExoneracion` (`SolicitudExoneracionID`) ON DELETE CASCADE,
  ADD CONSTRAINT `AprobacionExoneracion_ibfk_2` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `NivelAprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `AprobacionExoneracion_ibfk_3` FOREIGN KEY (`UserAprobadorID`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `AprobacionProposicion`
--
ALTER TABLE `AprobacionProposicion`
  ADD CONSTRAINT `FK_AprobacionProposicion_Nivel` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `NivelAprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `FK_AprobacionProposicion_Proposicion` FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `ProposicionCredito` (`ProposicionCreditoID`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_AprobacionProposicion_Usuario` FOREIGN KEY (`UserAprobadorID`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `Cliente`
--
ALTER TABLE `Cliente`
  ADD CONSTRAINT `Cliente_ibfk_1` FOREIGN KEY (`TasaMoraID`) REFERENCES `TasaMora` (`TasaMoraID`),
  ADD CONSTRAINT `FK_Cliente_Garante` FOREIGN KEY (`GaranteID`) REFERENCES `Cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_Cliente_PromotorCobrador` FOREIGN KEY (`PromotorCobradorID`) REFERENCES `PromotorCobrador` (`PromotorCobradorID`),
  ADD CONSTRAINT `FK_Cliente_Tasa` FOREIGN KEY (`TasaID`) REFERENCES `Tasa` (`TasaID`);

--
-- Filtros para la tabla `Compra`
--
ALTER TABLE `Compra`
  ADD CONSTRAINT `compra_tipo_comprobante_fk` FOREIGN KEY (`TipoComprobanteID`) REFERENCES `TipoComprobante` (`TipoComprobanteID`);

--
-- Filtros para la tabla `Credito`
--
ALTER TABLE `Credito`
  ADD CONSTRAINT `FK_Credito_Proposicion` FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `ProposicionCredito` (`ProposicionCreditoID`),
  ADD CONSTRAINT `FK_Credito_TipoPago` FOREIGN KEY (`TipoPagoID`) REFERENCES `TipoPago` (`TipoPagoID`);

--
-- Filtros para la tabla `cuota`
--
ALTER TABLE `cuota`
  ADD CONSTRAINT `FK_Cuota_Credito` FOREIGN KEY (`CreditoID`) REFERENCES `Credito` (`CreditoID`);

--
-- Filtros para la tabla `DocumentoCliente`
--
ALTER TABLE `DocumentoCliente`
  ADD CONSTRAINT `FK_DocumentoCliente_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `Cliente` (`ClienteID`);

--
-- Filtros para la tabla `EvaluacionCredito`
--
ALTER TABLE `EvaluacionCredito`
  ADD CONSTRAINT `FK_EvaluacionCredito_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `Cliente` (`ClienteID`);

--
-- Filtros para la tabla `Gasto`
--
ALTER TABLE `Gasto`
  ADD CONSTRAINT `gasto_motivo_fk` FOREIGN KEY (`MotivoID`) REFERENCES `Motivo` (`MotivoID`),
  ADD CONSTRAINT `gasto_tipo_comprobante_fk` FOREIGN KEY (`TipoComprobanteID`) REFERENCES `TipoComprobante` (`TipoComprobanteID`);

--
-- Filtros para la tabla `HistorialExoneracion`
--
ALTER TABLE `HistorialExoneracion`
  ADD CONSTRAINT `HistorialExoneracion_ibfk_1` FOREIGN KEY (`SolicitudExoneracionID`) REFERENCES `SolicitudExoneracion` (`SolicitudExoneracionID`),
  ADD CONSTRAINT `HistorialExoneracion_ibfk_2` FOREIGN KEY (`CreditoID`) REFERENCES `Credito` (`CreditoID`),
  ADD CONSTRAINT `HistorialExoneracion_ibfk_3` FOREIGN KEY (`ClienteID`) REFERENCES `Cliente` (`ClienteID`);

--
-- Filtros para la tabla `mora`
--
ALTER TABLE `mora`
  ADD CONSTRAINT `mora_ibfk_1` FOREIGN KEY (`CreditoID`) REFERENCES `Credito` (`CreditoID`) ON DELETE CASCADE;

--
-- Filtros para la tabla `Negocio`
--
ALTER TABLE `Negocio`
  ADD CONSTRAINT `FK_Negocio_Ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `Ciudad` (`CiudadID`),
  ADD CONSTRAINT `FK_Negocio_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `Cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_Negocio_Giro` FOREIGN KEY (`GiroID`) REFERENCES `Giro` (`GiroID`),
  ADD CONSTRAINT `FK_Negocio_SubGiro` FOREIGN KEY (`SubGiroID`) REFERENCES `SubGiro` (`SubGiroID`),
  ADD CONSTRAINT `FK_Negocio_Zona` FOREIGN KEY (`ZonaID`) REFERENCES `Zona` (`ZonaID`);

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `FK_Pago_Cobrador` FOREIGN KEY (`PromotorCobradorID`) REFERENCES `PromotorCobrador` (`PromotorCobradorID`),
  ADD CONSTRAINT `FK_Pago_Credito` FOREIGN KEY (`CreditoID`) REFERENCES `Credito` (`CreditoID`),
  ADD CONSTRAINT `FK_Pago_Cuota` FOREIGN KEY (`CuotaID`) REFERENCES `cuota` (`CuotaID`);

--
-- Filtros para la tabla `PromotorCobrador`
--
ALTER TABLE `PromotorCobrador`
  ADD CONSTRAINT `fk_promotor_ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `Ciudad` (`CiudadID`),
  ADD CONSTRAINT `fk_promotor_zona` FOREIGN KEY (`ZonaID`) REFERENCES `Zona` (`ZonaID`);

--
-- Filtros para la tabla `ProposicionCredito`
--
ALTER TABLE `ProposicionCredito`
  ADD CONSTRAINT `FK_ProposicionCredito_Anterior` FOREIGN KEY (`ProposicionCreditoAnteriorID`) REFERENCES `ProposicionCredito` (`ProposicionCreditoID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_ProposicionCredito_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `Cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_ProposicionCredito_NivelAprobacion` FOREIGN KEY (`NivelAprobacionRequerido`) REFERENCES `NivelAprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `FK_ProposicionCredito_Tasa` FOREIGN KEY (`TasaID`) REFERENCES `Tasa` (`TasaID`),
  ADD CONSTRAINT `FK_ProposicionCredito_TipoCredito` FOREIGN KEY (`TipoCreditoID`) REFERENCES `TipoCredito` (`TipoCreditoID`),
  ADD CONSTRAINT `FK_ProposicionCredito_Zona` FOREIGN KEY (`ZonaID`) REFERENCES `Zona` (`ZonaID`);

--
-- Filtros para la tabla `SolicitudExoneracion`
--
ALTER TABLE `SolicitudExoneracion`
  ADD CONSTRAINT `SolicitudExoneracion_ibfk_1` FOREIGN KEY (`CreditoID`) REFERENCES `Credito` (`CreditoID`),
  ADD CONSTRAINT `SolicitudExoneracion_ibfk_2` FOREIGN KEY (`TipoExoneracionID`) REFERENCES `TipoExoneracion` (`TipoExoneracionID`),
  ADD CONSTRAINT `SolicitudExoneracion_ibfk_3` FOREIGN KEY (`NivelAprobacionRequerido`) REFERENCES `NivelAprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `SolicitudExoneracion_ibfk_4` FOREIGN KEY (`UserAprobadorID`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `SolicitudExoneracion_ibfk_5` FOREIGN KEY (`PagoGeneradoID`) REFERENCES `pago` (`PagoID`) ON DELETE SET NULL;

--
-- Filtros para la tabla `SubGiro`
--
ALTER TABLE `SubGiro`
  ADD CONSTRAINT `FK_SubGiro_Giro` FOREIGN KEY (`GiroID`) REFERENCES `Giro` (`GiroID`);

--
-- Filtros para la tabla `TelefonoNegocio`
--
ALTER TABLE `TelefonoNegocio`
  ADD CONSTRAINT `FK_TelefonoNegocio_Negocio` FOREIGN KEY (`NegocioID`) REFERENCES `Negocio` (`NegocioID`);

--
-- Filtros para la tabla `UserNivelAprobacion`
--
ALTER TABLE `UserNivelAprobacion`
  ADD CONSTRAINT `FK_UserNivel_NivelAprobacion` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `NivelAprobacion` (`NivelAprobacionID`);

--
-- Filtros para la tabla `Zona`
--
ALTER TABLE `Zona`
  ADD CONSTRAINT `FK_Zona_Ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `Ciudad` (`CiudadID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
