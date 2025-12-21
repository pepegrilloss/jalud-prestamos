SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `analisiseconomico` (
  `AnalisisEconomicoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `CapitalManifestado` decimal(12,2) DEFAULT 0.00,
  `CapitalEstimado` decimal(12,2) DEFAULT 0.00,
  `VentaManifestadaMin` decimal(12,2) DEFAULT 0.00,
  `VentaManifestadaMax` decimal(12,2) DEFAULT 0.00,
  `VentaEstimada` decimal(12,2) DEFAULT 0.00,
  `FechaAnalisis` datetime NOT NULL DEFAULT current_timestamp(),
  `UsuarioAnalisis` varchar(100) DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioModificacion` varchar(100) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `analisiseconomico` (`AnalisisEconomicoID`, `ClienteID`, `CapitalManifestado`, `CapitalEstimado`, `VentaManifestadaMin`, `VentaManifestadaMax`, `VentaEstimada`, `FechaAnalisis`, `UsuarioAnalisis`, `FechaModificacion`, `UsuarioModificacion`, `Activo`) VALUES
(1, 1, 2000.00, 4000.00, 500.00, 800.00, 400.00, '2025-12-21 10:27:46', 'JVILCHERREZ', NULL, NULL, 1),
(2, 2, 2000.00, 4000.00, 500.00, 800.00, 400.00, '2025-12-21 10:39:06', 'JVILCHERREZ', NULL, NULL, 1);

CREATE TABLE `aprobacionproposicion` (
  `AprobacionProposicionID` int(11) NOT NULL,
  `ProposicionCreditoID` int(11) NOT NULL,
  `NivelAprobacionID` int(11) NOT NULL,
  `UserAprobadorID` bigint(20) DEFAULT NULL,
  `Estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `Comentario` text DEFAULT NULL,
  `FechaAprobacion` datetime DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `aprobacionproposicion` (`AprobacionProposicionID`, `ProposicionCreditoID`, `NivelAprobacionID`, `UserAprobadorID`, `Estado`, `Comentario`, `FechaAprobacion`, `FechaCreacion`) VALUES
(1, 2, 1, 1, 'APROBADO', NULL, '2025-12-21 10:54:15', '2025-12-21 10:53:44'),
(2, 3, 1, 1, 'APROBADO', NULL, '2025-12-21 13:16:57', '2025-12-21 13:16:48'),
(3, 4, 1, 1, 'APROBADO', NULL, '2025-12-21 13:17:42', '2025-12-21 13:17:27'),
(4, 5, 1, 1, 'APROBADO', NULL, '2025-12-21 13:18:43', '2025-12-21 13:18:38');

CREATE TABLE `ciudad` (
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ciudad` (`CiudadID`, `Nombre`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'CHICLAYO', 1, '2025-12-20 12:00:00', NULL);

CREATE TABLE `cliente` (
  `ClienteID` int(11) NOT NULL,
  `DNI` varchar(20) NOT NULL,
  `NombresApellidos` varchar(200) NOT NULL,
  `Sexo` char(1) NOT NULL CHECK (`Sexo` in ('M','F')),
  `FechaNacimiento` date DEFAULT NULL,
  `Estado` varchar(20) NOT NULL DEFAULT 'NO OBSERVADO' CHECK (`Estado` in ('OBSERVADO','NO OBSERVADO')),
  `ConyugeDNI` varchar(20) DEFAULT NULL,
  `ConyugeNombresApellidos` varchar(200) DEFAULT NULL,
  `Domicilio` varchar(500) DEFAULT NULL,
  `TasaID` int(11) DEFAULT NULL,
  `MontoMaxRecomendado` decimal(10,2) DEFAULT 0.00,
  `GaranteID` int(11) DEFAULT NULL,
  `Observaciones` text DEFAULT NULL,
  `PromotorCobradorID` int(11) DEFAULT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioRegistro` varchar(100) DEFAULT NULL,
  `UsuarioModificacion` varchar(100) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cliente` (`ClienteID`, `DNI`, `NombresApellidos`, `Sexo`, `FechaNacimiento`, `Estado`, `ConyugeDNI`, `ConyugeNombresApellidos`, `Domicilio`, `TasaID`, `MontoMaxRecomendado`, `GaranteID`, `Observaciones`, `PromotorCobradorID`, `FechaRegistro`, `FechaModificacion`, `UsuarioRegistro`, `UsuarioModificacion`, `Activo`) VALUES
(1, '72883082', 'LEONARDO JUNIOR VILCHERREZ PURIZACA', 'M', '2025-12-21', 'NO OBSERVADO', '72883083', 'Julio Vilcherrez Purizaca', 'Santa Teresita', 1, 2100.00, NULL, NULL, 1, '2025-12-21 10:27:46', NULL, 'JVILCHERREZ', NULL, 1),
(2, '72883083', 'JULIO MARIO VILCHERREZ PURIZACA', 'M', '2025-12-05', 'NO OBSERVADO', '72883080', 'Julio Vilcherrez Purizaca', 'Pedro Pascal', 1, 2100.00, 1, NULL, 1, '2025-12-21 10:39:06', NULL, 'JVILCHERREZ', NULL, 1);

CREATE TABLE `credito` (
  `CreditoID` int(11) NOT NULL,
  `ProposicionCreditoID` int(11) NOT NULL,
  `TipoPagoID` int(11) NOT NULL,
  `ComentarioGeneracion` text DEFAULT NULL,
  `FechaGeneracion` datetime NOT NULL DEFAULT current_timestamp(),
  `UserGeneracionID` bigint(20) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `credito` (`CreditoID`, `ProposicionCreditoID`, `TipoPagoID`, `ComentarioGeneracion`, `FechaGeneracion`, `UserGeneracionID`, `Activo`) VALUES
(1, 2, 1, 'adsadas', '2025-12-21 11:10:41', 1, 1),
(2, 4, 1, NULL, '2025-12-21 13:17:51', 1, 1),
(3, 5, 1, NULL, '2025-12-21 13:18:49', 1, 1);

CREATE TABLE `cuota` (
  `CuotaID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `NumeroCuota` int(11) NOT NULL,
  `FechaVencimiento` date NOT NULL,
  `MontoCuota` decimal(12,2) NOT NULL,
  `MontoCapital` decimal(12,2) NOT NULL,
  `MontoInteres` decimal(12,2) NOT NULL,
  `MontoPagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `SaldoPendiente` decimal(12,2) NOT NULL,
  `Estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `DiasAtraso` int(11) DEFAULT 0,
  `MontoMora` decimal(12,2) DEFAULT 0.00,
  `FechaPago` datetime DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documentocliente` (
  `DocumentoClienteID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `TipoDocumento` varchar(50) NOT NULL CHECK (`TipoDocumento` in ('DNI','RECIBO_SERVICIO','OTROS')),
  `RutaArchivo` varchar(500) NOT NULL,
  `NombreOriginal` varchar(255) NOT NULL,
  `TamanioArchivo` bigint(20) DEFAULT NULL,
  `Extension` varchar(10) DEFAULT NULL,
  `Observaciones` varchar(500) DEFAULT NULL,
  `UsuarioRegistro` varchar(100) DEFAULT NULL,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `UsuarioModificacion` varchar(100) DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `documentocliente` (`DocumentoClienteID`, `ClienteID`, `TipoDocumento`, `RutaArchivo`, `NombreOriginal`, `TamanioArchivo`, `Extension`, `Observaciones`, `UsuarioRegistro`, `FechaCreacion`, `UsuarioModificacion`, `FechaModificacion`, `Activo`) VALUES
(1, 1, 'DNI', 'documentos/dni/Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 'Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 118437, 'jpg', NULL, 'JVILCHERREZ', '2025-12-21 10:27:46', NULL, NULL, 1),
(2, 1, 'RECIBO_SERVICIO', 'documentos/recibos/Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 'Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 118437, 'jpg', NULL, 'JVILCHERREZ', '2025-12-21 10:27:46', NULL, NULL, 1),
(3, 2, 'DNI', 'documentos/dni/Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 'Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 118437, 'jpg', NULL, 'JVILCHERREZ', '2025-12-21 10:39:06', NULL, NULL, 1),
(4, 2, 'RECIBO_SERVICIO', 'documentos/recibos/Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 'Imagen de WhatsApp 2025-12-04 a las 17.10.28_59a8e849.jpg', 118437, 'jpg', NULL, 'JVILCHERREZ', '2025-12-21 10:39:06', NULL, NULL, 1);

CREATE TABLE `evaluacioncredito` (
  `EvaluacionCreditoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `Comentario` text NOT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT current_timestamp(),
  `UsuarioRegistro` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `giro` (
  `GiroID` int(11) NOT NULL,
  `Codigo` varchar(10) NOT NULL,
  `Descripcion` varchar(200) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `giro` (`GiroID`, `Codigo`, `Descripcion`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'FER01', 'FERRETERIA', 1, '2025-12-21 10:25:58', NULL);

CREATE TABLE `negocio` (
  `NegocioID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `DireccionNegocio` varchar(500) NOT NULL,
  `CiudadID` int(11) DEFAULT NULL,
  `ZonaID` int(11) DEFAULT NULL,
  `Antiguedad` decimal(5,2) DEFAULT NULL,
  `GiroID` int(11) DEFAULT NULL,
  `SubGiroID` int(11) DEFAULT NULL,
  `Ubicacion` varchar(20) DEFAULT NULL CHECK (`Ubicacion` in ('MALO','BUENO','REGULAR')),
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `negocio` (`NegocioID`, `ClienteID`, `DireccionNegocio`, `CiudadID`, `ZonaID`, `Antiguedad`, `GiroID`, `SubGiroID`, `Ubicacion`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 1, 'Santa Teresita Vichayal #234', 1, 1, 5.00, 1, 1, 'MALO', 1, '2025-12-21 10:27:46', NULL),
(2, 2, 'Santa Teresita Vichayal #234', 1, 1, 5.00, 1, 1, NULL, 1, '2025-12-21 10:39:06', NULL);

CREATE TABLE `nivelaprobacion` (
  `NivelAprobacionID` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `MontoMinimo` decimal(12,2) NOT NULL,
  `MontoMaximo` decimal(12,2) NOT NULL,
  `Orden` int(11) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nivelaprobacion` (`NivelAprobacionID`, `Nombre`, `MontoMinimo`, `MontoMaximo`, `Orden`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'Jefe de Oficina', 0.00, 5000.00, 3, 1, '2025-12-21 10:52:14', NULL),
(2, 'Supervisor Operativo', 5000.01, 30000.00, 2, 1, '2025-12-21 10:52:27', NULL),
(3, 'Gerencia', 30000.01, 999999999.00, 1, 1, '2025-12-21 10:53:31', NULL);

CREATE TABLE `promotorcobrador` (
  `PromotorCobradorID` int(11) NOT NULL,
  `Codigo` varchar(20) NOT NULL,
  `Descripcion` varchar(200) NOT NULL,
  `CiudadID` int(11) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `promotorcobrador` (`PromotorCobradorID`, `Codigo`, `Descripcion`, `CiudadID`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'FER01', 'FERRETERIA', 1, 1, '2025-12-21 10:01:33', NULL);

CREATE TABLE `proposicioncredito` (
  `ProposicionCreditoID` int(11) NOT NULL,
  `CodigoCredito` varchar(20) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `CodigoCliente` varchar(20) NOT NULL,
  `TipoCreditoID` int(11) NOT NULL,
  `MontoTotal` decimal(12,2) NOT NULL,
  `TasaID` int(11) NOT NULL,
  `TasaInteres` decimal(5,2) NOT NULL,
  `Plazo` int(11) NOT NULL,
  `NumeroCuotas` int(11) NOT NULL,
  `MontoCuota` decimal(12,2) NOT NULL,
  `MontoInteres` decimal(12,2) NOT NULL,
  `TasaMora` decimal(5,2) NOT NULL DEFAULT 0.00,
  `ZonaID` int(11) DEFAULT NULL,
  `Observaciones` text DEFAULT NULL,
  `UserProponenteID` bigint(20) NOT NULL,
  `FechaPropuesta` datetime NOT NULL DEFAULT current_timestamp(),
  `Estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE' CHECK (`Estado` in ('PENDIENTE','APROBADO','RECHAZADO','DESEMBOLSADO','CANCELADO')),
  `NivelAprobacionRequerido` int(11) DEFAULT NULL,
  `UserAprobadorID` bigint(20) DEFAULT NULL,
  `FechaAprobacion` datetime DEFAULT NULL,
  `ComentarioAprobacion` varchar(500) DEFAULT NULL,
  `FechaDesembolso` datetime DEFAULT NULL,
  `UserDesembolsoID` bigint(20) DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `UserModificacionID` bigint(20) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `SaldoPendiente` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `proposicioncredito` (`ProposicionCreditoID`, `CodigoCredito`, `ClienteID`, `CodigoCliente`, `TipoCreditoID`, `MontoTotal`, `TasaID`, `TasaInteres`, `Plazo`, `NumeroCuotas`, `MontoCuota`, `MontoInteres`, `TasaMora`, `ZonaID`, `Observaciones`, `UserProponenteID`, `FechaPropuesta`, `Estado`, `NivelAprobacionRequerido`, `UserAprobadorID`, `FechaAprobacion`, `ComentarioAprobacion`, `FechaDesembolso`, `UserDesembolsoID`, `FechaModificacion`, `UserModificacionID`, `Activo`, `SaldoPendiente`) VALUES
(2, 'C-000001', 2, '72883083', 1, 2100.00, 1, 0.50, 24, 21, 100.50, 10.50, 0.50, 1, NULL, 1, '2025-12-21 10:53:44', 'APROBADO', 1, 1, '2025-12-21 10:54:15', NULL, NULL, NULL, '2025-12-21 10:54:15', NULL, 1, 0.00),
(3, 'C-000002', 1, '72883082', 1, 400.00, 1, 0.50, 24, 21, 19.14, 2.00, 0.50, 1, NULL, 1, '2025-12-21 13:16:48', 'APROBADO', 1, 1, '2025-12-21 13:16:57', NULL, NULL, NULL, '2025-12-21 13:16:57', NULL, 1, 0.00),
(4, 'C-000003', 1, '72883082', 1, 2500.00, 1, 0.50, 24, 40, 62.81, 12.50, 0.50, NULL, NULL, 1, '2025-12-21 13:17:27', 'APROBADO', 1, 1, '2025-12-21 13:17:42', NULL, NULL, NULL, '2025-12-21 13:17:42', NULL, 1, 0.00),
(5, 'C-000004', 2, '72883083', 1, 2800.00, 1, 0.50, 24, 60, 46.90, 14.00, 0.50, 1, NULL, 1, '2025-12-21 13:18:38', 'APROBADO', 1, 1, '2025-12-21 13:18:43', NULL, NULL, NULL, '2025-12-21 13:18:43', NULL, 1, 0.00);

CREATE TABLE `subgiro` (
  `SubGiroID` int(11) NOT NULL,
  `GiroID` int(11) NOT NULL,
  `Descripcion` varchar(200) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subgiro` (`SubGiroID`, `GiroID`, `Descripcion`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 1, 'FERRETERIA', 1, '2025-12-21 10:26:05', NULL);

CREATE TABLE `tasa` (
  `TasaID` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Valor` decimal(5,2) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL,
  `Dias` int(11) NOT NULL,
  `Cuotas` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tasa` (`TasaID`, `Nombre`, `Valor`, `Activo`, `FechaCreacion`, `FechaModificacion`, `Dias`, `Cuotas`) VALUES
(1, 'TASA A 21 CUOTAS', 0.50, 1, '2025-12-21 10:25:45', '2025-12-21 10:25:45', 24, 21);

CREATE TABLE `telefononegocio` (
  `TelefonoNegocioID` int(11) NOT NULL,
  `NegocioID` int(11) NOT NULL,
  `Telefono` varchar(20) NOT NULL,
  `TipoTelefono` varchar(20) DEFAULT 'PRINCIPAL' CHECK (`TipoTelefono` in ('PRINCIPAL','SECUNDARIO','ALTERNATIVO')),
  `Observacion` varchar(200) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `telefononegocio` (`TelefonoNegocioID`, `NegocioID`, `Telefono`, `TipoTelefono`, `Observacion`, `Activo`, `FechaCreacion`) VALUES
(1, 1, '921893786', 'PRINCIPAL', NULL, 1, '2025-12-21 10:27:46'),
(2, 2, '921893786', 'PRINCIPAL', NULL, 1, '2025-12-21 10:39:06');

CREATE TABLE `tipocredito` (
  `TipoCreditoID` int(11) NOT NULL,
  `Codigo` varchar(10) NOT NULL,
  `Descripcion` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipocredito` (`TipoCreditoID`, `Codigo`, `Descripcion`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'PRU', 'PRUEBA', 1, '2025-12-21 10:26:21', NULL);

CREATE TABLE `tipopago` (
  `TipoPagoID` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipopago` (`TipoPagoID`, `Nombre`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 'PAGO DIARIO', 1, '2025-12-21 10:26:30', '2025-12-21 10:26:30');

CREATE TABLE `usernivelaprobacion` (
  `UserNivelAprobacionID` int(11) NOT NULL,
  `UserID` bigint(20) NOT NULL,
  `NivelAprobacionID` int(11) NOT NULL,
  `FechaAsignacion` datetime NOT NULL DEFAULT current_timestamp(),
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `usernivelaprobacion` (`UserNivelAprobacionID`, `UserID`, `NivelAprobacionID`, `FechaAsignacion`, `Activo`) VALUES
(1, 1, 1, '2025-12-21 10:54:08', 1);

CREATE TABLE `zona` (
  `ZonaID` int(11) NOT NULL,
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `zona` (`ZonaID`, `CiudadID`, `Nombre`, `Activo`, `FechaCreacion`, `FechaModificacion`) VALUES
(1, 1, 'CHICLAYO02', 1, '2025-12-21 10:26:42', NULL);


ALTER TABLE `analisiseconomico`
  ADD PRIMARY KEY (`AnalisisEconomicoID`),
  ADD KEY `FK_AnalisisEconomico_Cliente` (`ClienteID`);

ALTER TABLE `aprobacionproposicion`
  ADD PRIMARY KEY (`AprobacionProposicionID`),
  ADD UNIQUE KEY `UQ_AprobacionProposicion` (`ProposicionCreditoID`,`NivelAprobacionID`),
  ADD KEY `FK_AprobacionProposicion_Nivel` (`NivelAprobacionID`),
  ADD KEY `FK_AprobacionProposicion_Usuario` (`UserAprobadorID`);

ALTER TABLE `ciudad`
  ADD PRIMARY KEY (`CiudadID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

ALTER TABLE `cliente`
  ADD PRIMARY KEY (`ClienteID`),
  ADD UNIQUE KEY `DNI` (`DNI`),
  ADD KEY `FK_Cliente_Garante` (`GaranteID`),
  ADD KEY `FK_Cliente_PromotorCobrador` (`PromotorCobradorID`),
  ADD KEY `FK_Cliente_Tasa` (`TasaID`);

ALTER TABLE `credito`
  ADD PRIMARY KEY (`CreditoID`),
  ADD UNIQUE KEY `ProposicionCreditoID` (`ProposicionCreditoID`),
  ADD KEY `FK_Credito_TipoPago` (`TipoPagoID`);

ALTER TABLE `cuota`
  ADD PRIMARY KEY (`CuotaID`),
  ADD KEY `FK_Cuota_Credito` (`CreditoID`);

ALTER TABLE `documentocliente`
  ADD PRIMARY KEY (`DocumentoClienteID`),
  ADD KEY `FK_DocumentoCliente_Cliente` (`ClienteID`);

ALTER TABLE `evaluacioncredito`
  ADD PRIMARY KEY (`EvaluacionCreditoID`),
  ADD KEY `FK_EvaluacionCredito_Cliente` (`ClienteID`);

ALTER TABLE `giro`
  ADD PRIMARY KEY (`GiroID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

ALTER TABLE `negocio`
  ADD PRIMARY KEY (`NegocioID`),
  ADD KEY `FK_Negocio_Cliente` (`ClienteID`),
  ADD KEY `FK_Negocio_Giro` (`GiroID`),
  ADD KEY `FK_Negocio_SubGiro` (`SubGiroID`),
  ADD KEY `FK_Negocio_Zona` (`ZonaID`),
  ADD KEY `FK_Negocio_Ciudad` (`CiudadID`);

ALTER TABLE `nivelaprobacion`
  ADD PRIMARY KEY (`NivelAprobacionID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

ALTER TABLE `promotorcobrador`
  ADD PRIMARY KEY (`PromotorCobradorID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`),
  ADD KEY `fk_promotor_ciudad` (`CiudadID`);

ALTER TABLE `proposicioncredito`
  ADD PRIMARY KEY (`ProposicionCreditoID`),
  ADD UNIQUE KEY `CodigoCredito` (`CodigoCredito`),
  ADD KEY `FK_ProposicionCredito_Cliente` (`ClienteID`),
  ADD KEY `FK_ProposicionCredito_NivelAprobacion` (`NivelAprobacionRequerido`),
  ADD KEY `FK_ProposicionCredito_Tasa` (`TasaID`),
  ADD KEY `FK_ProposicionCredito_TipoCredito` (`TipoCreditoID`),
  ADD KEY `FK_ProposicionCredito_Zona` (`ZonaID`);

ALTER TABLE `subgiro`
  ADD PRIMARY KEY (`SubGiroID`),
  ADD UNIQUE KEY `UQ_SubGiro_Giro` (`GiroID`,`Descripcion`);

ALTER TABLE `tasa`
  ADD PRIMARY KEY (`TasaID`);

ALTER TABLE `telefononegocio`
  ADD PRIMARY KEY (`TelefonoNegocioID`),
  ADD KEY `FK_TelefonoNegocio_Negocio` (`NegocioID`);

ALTER TABLE `tipocredito`
  ADD PRIMARY KEY (`TipoCreditoID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

ALTER TABLE `tipopago`
  ADD PRIMARY KEY (`TipoPagoID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

ALTER TABLE `usernivelaprobacion`
  ADD PRIMARY KEY (`UserNivelAprobacionID`),
  ADD UNIQUE KEY `UserID` (`UserID`),
  ADD KEY `FK_UserNivel_NivelAprobacion` (`NivelAprobacionID`);

ALTER TABLE `zona`
  ADD PRIMARY KEY (`ZonaID`),
  ADD UNIQUE KEY `UQ_Zona_Ciudad` (`CiudadID`,`Nombre`);


ALTER TABLE `analisiseconomico`
  MODIFY `AnalisisEconomicoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `aprobacionproposicion`
  MODIFY `AprobacionProposicionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `ciudad`
  MODIFY `CiudadID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `cliente`
  MODIFY `ClienteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `credito`
  MODIFY `CreditoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `cuota`
  MODIFY `CuotaID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `documentocliente`
  MODIFY `DocumentoClienteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `evaluacioncredito`
  MODIFY `EvaluacionCreditoID` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `giro`
  MODIFY `GiroID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `negocio`
  MODIFY `NegocioID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `nivelaprobacion`
  MODIFY `NivelAprobacionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `promotorcobrador`
  MODIFY `PromotorCobradorID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `proposicioncredito`
  MODIFY `ProposicionCreditoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `subgiro`
  MODIFY `SubGiroID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `tasa`
  MODIFY `TasaID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `telefononegocio`
  MODIFY `TelefonoNegocioID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `tipocredito`
  MODIFY `TipoCreditoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `tipopago`
  MODIFY `TipoPagoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `usernivelaprobacion`
  MODIFY `UserNivelAprobacionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `zona`
  MODIFY `ZonaID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;


ALTER TABLE `analisiseconomico`
  ADD CONSTRAINT `FK_AnalisisEconomico_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`);

ALTER TABLE `aprobacionproposicion`
  ADD CONSTRAINT `FK_AprobacionProposicion_Nivel` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `nivelaprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `FK_AprobacionProposicion_Proposicion` FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `proposicioncredito` (`ProposicionCreditoID`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_AprobacionProposicion_Usuario` FOREIGN KEY (`UserAprobadorID`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `cliente`
  ADD CONSTRAINT `FK_Cliente_Garante` FOREIGN KEY (`GaranteID`) REFERENCES `cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_Cliente_PromotorCobrador` FOREIGN KEY (`PromotorCobradorID`) REFERENCES `promotorcobrador` (`PromotorCobradorID`),
  ADD CONSTRAINT `FK_Cliente_Tasa` FOREIGN KEY (`TasaID`) REFERENCES `tasa` (`TasaID`);

ALTER TABLE `credito`
  ADD CONSTRAINT `FK_Credito_Proposicion` FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `proposicioncredito` (`ProposicionCreditoID`),
  ADD CONSTRAINT `FK_Credito_TipoPago` FOREIGN KEY (`TipoPagoID`) REFERENCES `tipopago` (`TipoPagoID`);

ALTER TABLE `cuota`
  ADD CONSTRAINT `FK_Cuota_Credito` FOREIGN KEY (`CreditoID`) REFERENCES `credito` (`CreditoID`);

ALTER TABLE `documentocliente`
  ADD CONSTRAINT `FK_DocumentoCliente_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`);

ALTER TABLE `evaluacioncredito`
  ADD CONSTRAINT `FK_EvaluacionCredito_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`);

ALTER TABLE `negocio`
  ADD CONSTRAINT `FK_Negocio_Ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `ciudad` (`CiudadID`),
  ADD CONSTRAINT `FK_Negocio_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_Negocio_Giro` FOREIGN KEY (`GiroID`) REFERENCES `giro` (`GiroID`),
  ADD CONSTRAINT `FK_Negocio_SubGiro` FOREIGN KEY (`SubGiroID`) REFERENCES `subgiro` (`SubGiroID`),
  ADD CONSTRAINT `FK_Negocio_Zona` FOREIGN KEY (`ZonaID`) REFERENCES `zona` (`ZonaID`);

ALTER TABLE `promotorcobrador`
  ADD CONSTRAINT `fk_promotor_ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `ciudad` (`CiudadID`);

ALTER TABLE `proposicioncredito`
  ADD CONSTRAINT `FK_ProposicionCredito_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_ProposicionCredito_NivelAprobacion` FOREIGN KEY (`NivelAprobacionRequerido`) REFERENCES `nivelaprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `FK_ProposicionCredito_Tasa` FOREIGN KEY (`TasaID`) REFERENCES `tasa` (`TasaID`),
  ADD CONSTRAINT `FK_ProposicionCredito_TipoCredito` FOREIGN KEY (`TipoCreditoID`) REFERENCES `tipocredito` (`TipoCreditoID`),
  ADD CONSTRAINT `FK_ProposicionCredito_Zona` FOREIGN KEY (`ZonaID`) REFERENCES `zona` (`ZonaID`);

ALTER TABLE `subgiro`
  ADD CONSTRAINT `FK_SubGiro_Giro` FOREIGN KEY (`GiroID`) REFERENCES `giro` (`GiroID`);

ALTER TABLE `telefononegocio`
  ADD CONSTRAINT `FK_TelefonoNegocio_Negocio` FOREIGN KEY (`NegocioID`) REFERENCES `negocio` (`NegocioID`);

ALTER TABLE `usernivelaprobacion`
  ADD CONSTRAINT `FK_UserNivel_NivelAprobacion` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `nivelaprobacion` (`NivelAprobacionID`);

ALTER TABLE `zona`
  ADD CONSTRAINT `FK_Zona_Ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `ciudad` (`CiudadID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
