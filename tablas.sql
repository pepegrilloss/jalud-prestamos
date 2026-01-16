
--
-- Base de datos: `jalud_prestamos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `analisiseconomico`
--

CREATE TABLE `analisiseconomico` (
  `AnalisisEconomicoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `CapitalManifestado` decimal(12,2) DEFAULT 0.00,
  `CapitalEstimado` decimal(12,2) DEFAULT 0.00,
  `VentaManifestadaMin` decimal(12,2) DEFAULT 0.00,
  `VentaManifestadaMax` decimal(12,2) DEFAULT 0.00,
  `VentaEstimada` decimal(12,2) DEFAULT 0.00,
  `MontoMaxRecomendado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `FechaAnalisis` datetime NOT NULL DEFAULT current_timestamp(),
  `UsuarioAnalisis` varchar(100) DEFAULT NULL,
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioModificacion` varchar(100) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aprobacionproposicion`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ciudad`
--

CREATE TABLE `ciudad` (
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cliente`
--

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
  `GaranteID` int(11) DEFAULT NULL,
  `Observaciones` text DEFAULT NULL,
  `PromotorCobradorID` int(11) DEFAULT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL,
  `UsuarioRegistro` varchar(100) DEFAULT NULL,
  `UsuarioModificacion` varchar(100) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `credito`
--

CREATE TABLE `credito` (
  `CreditoID` int(11) NOT NULL,
  `ProposicionCreditoID` int(11) NOT NULL,
  `TipoPagoID` int(11) NOT NULL,
  `ComentarioGeneracion` text DEFAULT NULL,
  `FechaGeneracion` datetime NOT NULL DEFAULT current_timestamp(),
  `UserGeneracionID` bigint(20) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentocliente`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluacioncredito`
--

CREATE TABLE `evaluacioncredito` (
  `EvaluacionCreditoID` int(11) NOT NULL,
  `ClienteID` int(11) NOT NULL,
  `Comentario` text NOT NULL,
  `FechaRegistro` datetime NOT NULL DEFAULT current_timestamp(),
  `UsuarioRegistro` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `giro`
--

CREATE TABLE `giro` (
  `GiroID` int(11) NOT NULL,
  `Codigo` varchar(10) NOT NULL,
  `Descripcion` varchar(200) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `negocio`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nivelaprobacion`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `PagoID` int(11) NOT NULL,
  `CreditoID` int(11) NOT NULL,
  `CuotaID` int(11) DEFAULT NULL,
  `PromotorCobradorID` int(11) NOT NULL,
  `MontoPagado` decimal(12,2) NOT NULL,
  `FechaPago` datetime NOT NULL DEFAULT current_timestamp(),
  `TipoPago` varchar(20) DEFAULT 'EFECTIVO',
  `EsMora` tinyint(1) NOT NULL DEFAULT 0,
  `EsPagoAMayor` tinyint(1) NOT NULL DEFAULT 0,
  `EsPagoForzado` tinyint(1) NOT NULL DEFAULT 0,
  `Comentario` varchar(500) DEFAULT NULL,
  `UsuarioRegistro` varchar(100) DEFAULT NULL,
  `FechaCreacion` datetime DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promotorcobrador`
--

CREATE TABLE `promotorcobrador` (
  `PromotorCobradorID` int(11) NOT NULL,
  `Codigo` varchar(20) NOT NULL,
  `Descripcion` varchar(200) NOT NULL,
  `CiudadID` int(11) NOT NULL,
  `ZonaID` int(11) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proposicioncredito`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subgiro`
--

CREATE TABLE `subgiro` (
  `SubGiroID` int(11) NOT NULL,
  `GiroID` int(11) NOT NULL,
  `Descripcion` varchar(200) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasa`
--

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `telefononegocio`
--

CREATE TABLE `telefononegocio` (
  `TelefonoNegocioID` int(11) NOT NULL,
  `NegocioID` int(11) NOT NULL,
  `Telefono` varchar(20) NOT NULL,
  `TipoTelefono` varchar(20) DEFAULT 'PRINCIPAL' CHECK (`TipoTelefono` in ('PRINCIPAL','SECUNDARIO','ALTERNATIVO')),
  `Observacion` varchar(200) DEFAULT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipocredito`
--

CREATE TABLE `tipocredito` (
  `TipoCreditoID` int(11) NOT NULL,
  `Codigo` varchar(10) NOT NULL,
  `Descripcion` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipopago`
--

CREATE TABLE `tipopago` (
  `TipoPagoID` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usernivelaprobacion`
--

CREATE TABLE `usernivelaprobacion` (
  `UserNivelAprobacionID` int(11) NOT NULL,
  `UserID` bigint(20) NOT NULL,
  `NivelAprobacionID` int(11) NOT NULL,
  `FechaAsignacion` datetime NOT NULL DEFAULT current_timestamp(),
  `Activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `zona`
--

CREATE TABLE `zona` (
  `ZonaID` int(11) NOT NULL,
  `CiudadID` int(11) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT 1,
  `FechaCreacion` datetime NOT NULL DEFAULT current_timestamp(),
  `FechaModificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `analisiseconomico`
--
ALTER TABLE `analisiseconomico`
  ADD PRIMARY KEY (`AnalisisEconomicoID`),
  ADD KEY `FK_AnalisisEconomico_Cliente` (`ClienteID`);

--
-- Indices de la tabla `aprobacionproposicion`
--
ALTER TABLE `aprobacionproposicion`
  ADD PRIMARY KEY (`AprobacionProposicionID`),
  ADD UNIQUE KEY `UQ_AprobacionProposicion` (`ProposicionCreditoID`,`NivelAprobacionID`),
  ADD KEY `FK_AprobacionProposicion_Nivel` (`NivelAprobacionID`),
  ADD KEY `FK_AprobacionProposicion_Usuario` (`UserAprobadorID`);

--
-- Indices de la tabla `ciudad`
--
ALTER TABLE `ciudad`
  ADD PRIMARY KEY (`CiudadID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD PRIMARY KEY (`ClienteID`),
  ADD UNIQUE KEY `DNI` (`DNI`),
  ADD KEY `FK_Cliente_Garante` (`GaranteID`),
  ADD KEY `FK_Cliente_PromotorCobrador` (`PromotorCobradorID`),
  ADD KEY `FK_Cliente_Tasa` (`TasaID`);

--
-- Indices de la tabla `credito`
--
ALTER TABLE `credito`
  ADD PRIMARY KEY (`CreditoID`),
  ADD UNIQUE KEY `ProposicionCreditoID` (`ProposicionCreditoID`),
  ADD KEY `FK_Credito_TipoPago` (`TipoPagoID`);

--
-- Indices de la tabla `cuota`
--
ALTER TABLE `cuota`
  ADD PRIMARY KEY (`CuotaID`),
  ADD KEY `FK_Cuota_Credito` (`CreditoID`);

--
-- Indices de la tabla `documentocliente`
--
ALTER TABLE `documentocliente`
  ADD PRIMARY KEY (`DocumentoClienteID`),
  ADD KEY `FK_DocumentoCliente_Cliente` (`ClienteID`);

--
-- Indices de la tabla `evaluacioncredito`
--
ALTER TABLE `evaluacioncredito`
  ADD PRIMARY KEY (`EvaluacionCreditoID`),
  ADD KEY `FK_EvaluacionCredito_Cliente` (`ClienteID`);

--
-- Indices de la tabla `giro`
--
ALTER TABLE `giro`
  ADD PRIMARY KEY (`GiroID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

--
-- Indices de la tabla `negocio`
--
ALTER TABLE `negocio`
  ADD PRIMARY KEY (`NegocioID`),
  ADD KEY `FK_Negocio_Cliente` (`ClienteID`),
  ADD KEY `FK_Negocio_Giro` (`GiroID`),
  ADD KEY `FK_Negocio_SubGiro` (`SubGiroID`),
  ADD KEY `FK_Negocio_Zona` (`ZonaID`),
  ADD KEY `FK_Negocio_Ciudad` (`CiudadID`);

--
-- Indices de la tabla `nivelaprobacion`
--
ALTER TABLE `nivelaprobacion`
  ADD PRIMARY KEY (`NivelAprobacionID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `pago`
--
ALTER TABLE `pago`
  ADD PRIMARY KEY (`PagoID`),
  ADD KEY `FK_Pago_Cobrador` (`PromotorCobradorID`),
  ADD KEY `FK_Pago_Credito` (`CreditoID`),
  ADD KEY `FK_Pago_Cuota` (`CuotaID`);

--
-- Indices de la tabla `promotorcobrador`
--
ALTER TABLE `promotorcobrador`
  ADD PRIMARY KEY (`PromotorCobradorID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`),
  ADD KEY `fk_promotor_ciudad` (`CiudadID`),
  ADD KEY `fk_promotor_zona` (`ZonaID`);

--
-- Indices de la tabla `proposicioncredito`
--
ALTER TABLE `proposicioncredito`
  ADD PRIMARY KEY (`ProposicionCreditoID`),
  ADD UNIQUE KEY `CodigoCredito` (`CodigoCredito`),
  ADD KEY `FK_ProposicionCredito_Cliente` (`ClienteID`),
  ADD KEY `FK_ProposicionCredito_NivelAprobacion` (`NivelAprobacionRequerido`),
  ADD KEY `FK_ProposicionCredito_Tasa` (`TasaID`),
  ADD KEY `FK_ProposicionCredito_TipoCredito` (`TipoCreditoID`),
  ADD KEY `FK_ProposicionCredito_Zona` (`ZonaID`);

--
-- Indices de la tabla `subgiro`
--
ALTER TABLE `subgiro`
  ADD PRIMARY KEY (`SubGiroID`),
  ADD UNIQUE KEY `UQ_SubGiro_Giro` (`GiroID`,`Descripcion`);

--
-- Indices de la tabla `tasa`
--
ALTER TABLE `tasa`
  ADD PRIMARY KEY (`TasaID`);

--
-- Indices de la tabla `telefononegocio`
--
ALTER TABLE `telefononegocio`
  ADD PRIMARY KEY (`TelefonoNegocioID`),
  ADD KEY `FK_TelefonoNegocio_Negocio` (`NegocioID`);

--
-- Indices de la tabla `tipocredito`
--
ALTER TABLE `tipocredito`
  ADD PRIMARY KEY (`TipoCreditoID`),
  ADD UNIQUE KEY `Codigo` (`Codigo`);

--
-- Indices de la tabla `tipopago`
--
ALTER TABLE `tipopago`
  ADD PRIMARY KEY (`TipoPagoID`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `usernivelaprobacion`
--
ALTER TABLE `usernivelaprobacion`
  ADD PRIMARY KEY (`UserNivelAprobacionID`),
  ADD UNIQUE KEY `UserID` (`UserID`),
  ADD KEY `FK_UserNivel_NivelAprobacion` (`NivelAprobacionID`);

--
-- Indices de la tabla `zona`
--
ALTER TABLE `zona`
  ADD PRIMARY KEY (`ZonaID`),
  ADD UNIQUE KEY `UQ_Zona_Ciudad` (`CiudadID`,`Nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `analisiseconomico`
--
ALTER TABLE `analisiseconomico`
  MODIFY `AnalisisEconomicoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `aprobacionproposicion`
--
ALTER TABLE `aprobacionproposicion`
  MODIFY `AprobacionProposicionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ciudad`
--
ALTER TABLE `ciudad`
  MODIFY `CiudadID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cliente`
--
ALTER TABLE `cliente`
  MODIFY `ClienteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `credito`
--
ALTER TABLE `credito`
  MODIFY `CreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cuota`
--
ALTER TABLE `cuota`
  MODIFY `CuotaID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documentocliente`
--
ALTER TABLE `documentocliente`
  MODIFY `DocumentoClienteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `evaluacioncredito`
--
ALTER TABLE `evaluacioncredito`
  MODIFY `EvaluacionCreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `giro`
--
ALTER TABLE `giro`
  MODIFY `GiroID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `negocio`
--
ALTER TABLE `negocio`
  MODIFY `NegocioID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nivelaprobacion`
--
ALTER TABLE `nivelaprobacion`
  MODIFY `NivelAprobacionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pago`
--
ALTER TABLE `pago`
  MODIFY `PagoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promotorcobrador`
--
ALTER TABLE `promotorcobrador`
  MODIFY `PromotorCobradorID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proposicioncredito`
--
ALTER TABLE `proposicioncredito`
  MODIFY `ProposicionCreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `subgiro`
--
ALTER TABLE `subgiro`
  MODIFY `SubGiroID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tasa`
--
ALTER TABLE `tasa`
  MODIFY `TasaID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `telefononegocio`
--
ALTER TABLE `telefononegocio`
  MODIFY `TelefonoNegocioID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipocredito`
--
ALTER TABLE `tipocredito`
  MODIFY `TipoCreditoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipopago`
--
ALTER TABLE `tipopago`
  MODIFY `TipoPagoID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usernivelaprobacion`
--
ALTER TABLE `usernivelaprobacion`
  MODIFY `UserNivelAprobacionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `zona`
--
ALTER TABLE `zona`
  MODIFY `ZonaID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `analisiseconomico`
--
ALTER TABLE `analisiseconomico`
  ADD CONSTRAINT `FK_AnalisisEconomico_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`);

--
-- Filtros para la tabla `aprobacionproposicion`
--
ALTER TABLE `aprobacionproposicion`
  ADD CONSTRAINT `FK_AprobacionProposicion_Nivel` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `nivelaprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `FK_AprobacionProposicion_Proposicion` FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `proposicioncredito` (`ProposicionCreditoID`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_AprobacionProposicion_Usuario` FOREIGN KEY (`UserAprobadorID`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cliente`
--
ALTER TABLE `cliente`
  ADD CONSTRAINT `FK_Cliente_Garante` FOREIGN KEY (`GaranteID`) REFERENCES `cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_Cliente_PromotorCobrador` FOREIGN KEY (`PromotorCobradorID`) REFERENCES `promotorcobrador` (`PromotorCobradorID`),
  ADD CONSTRAINT `FK_Cliente_Tasa` FOREIGN KEY (`TasaID`) REFERENCES `tasa` (`TasaID`);

--
-- Filtros para la tabla `credito`
--
ALTER TABLE `credito`
  ADD CONSTRAINT `FK_Credito_Proposicion` FOREIGN KEY (`ProposicionCreditoID`) REFERENCES `proposicioncredito` (`ProposicionCreditoID`),
  ADD CONSTRAINT `FK_Credito_TipoPago` FOREIGN KEY (`TipoPagoID`) REFERENCES `tipopago` (`TipoPagoID`);

--
-- Filtros para la tabla `cuota`
--
ALTER TABLE `cuota`
  ADD CONSTRAINT `FK_Cuota_Credito` FOREIGN KEY (`CreditoID`) REFERENCES `credito` (`CreditoID`);

--
-- Filtros para la tabla `documentocliente`
--
ALTER TABLE `documentocliente`
  ADD CONSTRAINT `FK_DocumentoCliente_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`);

--
-- Filtros para la tabla `evaluacioncredito`
--
ALTER TABLE `evaluacioncredito`
  ADD CONSTRAINT `FK_EvaluacionCredito_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`);

--
-- Filtros para la tabla `negocio`
--
ALTER TABLE `negocio`
  ADD CONSTRAINT `FK_Negocio_Ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `ciudad` (`CiudadID`),
  ADD CONSTRAINT `FK_Negocio_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_Negocio_Giro` FOREIGN KEY (`GiroID`) REFERENCES `giro` (`GiroID`),
  ADD CONSTRAINT `FK_Negocio_SubGiro` FOREIGN KEY (`SubGiroID`) REFERENCES `subgiro` (`SubGiroID`),
  ADD CONSTRAINT `FK_Negocio_Zona` FOREIGN KEY (`ZonaID`) REFERENCES `zona` (`ZonaID`);

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `FK_Pago_Cobrador` FOREIGN KEY (`PromotorCobradorID`) REFERENCES `promotorcobrador` (`PromotorCobradorID`),
  ADD CONSTRAINT `FK_Pago_Credito` FOREIGN KEY (`CreditoID`) REFERENCES `credito` (`CreditoID`),
  ADD CONSTRAINT `FK_Pago_Cuota` FOREIGN KEY (`CuotaID`) REFERENCES `cuota` (`CuotaID`);

--
-- Filtros para la tabla `promotorcobrador`
--
ALTER TABLE `promotorcobrador`
  ADD CONSTRAINT `fk_promotor_ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `ciudad` (`CiudadID`),
  ADD CONSTRAINT `fk_promotor_zona` FOREIGN KEY (`ZonaID`) REFERENCES `zona` (`ZonaID`);

--
-- Filtros para la tabla `proposicioncredito`
--
ALTER TABLE `proposicioncredito`
  ADD CONSTRAINT `FK_ProposicionCredito_Cliente` FOREIGN KEY (`ClienteID`) REFERENCES `cliente` (`ClienteID`),
  ADD CONSTRAINT `FK_ProposicionCredito_NivelAprobacion` FOREIGN KEY (`NivelAprobacionRequerido`) REFERENCES `nivelaprobacion` (`NivelAprobacionID`),
  ADD CONSTRAINT `FK_ProposicionCredito_Tasa` FOREIGN KEY (`TasaID`) REFERENCES `tasa` (`TasaID`),
  ADD CONSTRAINT `FK_ProposicionCredito_TipoCredito` FOREIGN KEY (`TipoCreditoID`) REFERENCES `tipocredito` (`TipoCreditoID`),
  ADD CONSTRAINT `FK_ProposicionCredito_Zona` FOREIGN KEY (`ZonaID`) REFERENCES `zona` (`ZonaID`);

--
-- Filtros para la tabla `subgiro`
--
ALTER TABLE `subgiro`
  ADD CONSTRAINT `FK_SubGiro_Giro` FOREIGN KEY (`GiroID`) REFERENCES `giro` (`GiroID`);

--
-- Filtros para la tabla `telefononegocio`
--
ALTER TABLE `telefononegocio`
  ADD CONSTRAINT `FK_TelefonoNegocio_Negocio` FOREIGN KEY (`NegocioID`) REFERENCES `negocio` (`NegocioID`);

--
-- Filtros para la tabla `usernivelaprobacion`
--
ALTER TABLE `usernivelaprobacion`
  ADD CONSTRAINT `FK_UserNivel_NivelAprobacion` FOREIGN KEY (`NivelAprobacionID`) REFERENCES `nivelaprobacion` (`NivelAprobacionID`);

--
-- Filtros para la tabla `zona`
--
ALTER TABLE `zona`
  ADD CONSTRAINT `FK_Zona_Ciudad` FOREIGN KEY (`CiudadID`) REFERENCES `ciudad` (`CiudadID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
