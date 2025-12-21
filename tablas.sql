USE [JALUD_Prestamos]
GO
/****** Object:  Table [dbo].[AnalisisEconomico]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[AnalisisEconomico](
	[AnalisisEconomicoID] [int] IDENTITY(1,1) NOT NULL,
	[ClienteID] [int] NOT NULL,
	[CapitalManifestado] [decimal](12, 2) NULL,
	[CapitalEstimado] [decimal](12, 2) NULL,
	[VentaManifestadaMin] [decimal](12, 2) NULL,
	[VentaManifestadaMax] [decimal](12, 2) NULL,
	[VentaEstimada] [decimal](12, 2) NULL,
	[FechaAnalisis] [datetime] NOT NULL,
	[UsuarioAnalisis] [nvarchar](100) NULL,
	[FechaModificacion] [datetime] NULL,
	[UsuarioModificacion] [nvarchar](100) NULL,
	[Activo] [bit] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[AnalisisEconomicoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[AprobacionProposicion]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[AprobacionProposicion](
	[AprobacionProposicionID] [int] IDENTITY(1,1) NOT NULL,
	[ProposicionCreditoID] [int] NOT NULL,
	[NivelAprobacionID] [int] NOT NULL,
	[UserAprobadorID] [bigint] NULL,
	[Estado] [nvarchar](20) NOT NULL,
	[Comentario] [nvarchar](max) NULL,
	[FechaAprobacion] [datetime] NULL,
	[FechaCreacion] [datetime] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[AprobacionProposicionID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_AprobacionProposicion] UNIQUE NONCLUSTERED 
(
	[ProposicionCreditoID] ASC,
	[NivelAprobacionID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[cache]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[cache](
	[key] [nvarchar](255) NOT NULL,
	[value] [nvarchar](max) NOT NULL,
	[expiration] [int] NOT NULL,
 CONSTRAINT [cache_key_primary] PRIMARY KEY CLUSTERED 
(
	[key] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[cache_locks]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[cache_locks](
	[key] [nvarchar](255) NOT NULL,
	[owner] [nvarchar](255) NOT NULL,
	[expiration] [int] NOT NULL,
 CONSTRAINT [cache_locks_key_primary] PRIMARY KEY CLUSTERED 
(
	[key] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Ciudad]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Ciudad](
	[CiudadID] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[CiudadID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[Nombre] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Cliente]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Cliente](
	[ClienteID] [int] IDENTITY(1,1) NOT NULL,
	[DNI] [nvarchar](20) NOT NULL,
	[NombresApellidos] [nvarchar](200) NOT NULL,
	[Sexo] [char](1) NOT NULL,
	[FechaNacimiento] [date] NULL,
	[Estado] [nvarchar](20) NOT NULL,
	[ConyugeDNI] [nvarchar](20) NULL,
	[ConyugeNombresApellidos] [nvarchar](200) NULL,
	[CiudadID] [int] NULL,
	[ZonaID] [int] NULL,
	[Domicilio] [nvarchar](500) NULL,
	[TasaID] [int] NULL,
	[MontoMaxRecomendado] [decimal](10, 2) NULL,
	[GaranteID] [int] NULL,
	[Observaciones] [nvarchar](max) NULL,
	[PromotorCobradorID] [int] NULL,
	[FechaRegistro] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
	[UsuarioRegistro] [nvarchar](100) NULL,
	[UsuarioModificacion] [nvarchar](100) NULL,
	[Activo] [bit] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[ClienteID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[DNI] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Credito]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Credito](
	[CreditoID] [int] IDENTITY(1,1) NOT NULL,
	[ProposicionCreditoID] [int] NOT NULL,
	[TipoPagoID] [int] NOT NULL,
	[ComentarioGeneracion] [nvarchar](max) NULL,
	[FechaGeneracion] [datetime] NOT NULL,
	[UserGeneracionID] [bigint] NOT NULL,
	[Activo] [bit] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[CreditoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[ProposicionCreditoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[DocumentoCliente]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[DocumentoCliente](
	[DocumentoClienteID] [int] IDENTITY(1,1) NOT NULL,
	[ClienteID] [int] NOT NULL,
	[TipoDocumento] [nvarchar](50) NOT NULL,
	[RutaArchivo] [nvarchar](500) NOT NULL,
	[NombreOriginal] [nvarchar](255) NOT NULL,
	[TamanioArchivo] [bigint] NULL,
	[Extension] [nvarchar](10) NULL,
	[Observaciones] [nvarchar](500) NULL,
	[UsuarioRegistro] [nvarchar](100) NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[UsuarioModificacion] [nvarchar](100) NULL,
	[FechaModificacion] [datetime] NULL,
	[Activo] [bit] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[DocumentoClienteID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[EvaluacionCredito]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[EvaluacionCredito](
	[EvaluacionCreditoID] [int] IDENTITY(1,1) NOT NULL,
	[ClienteID] [int] NOT NULL,
	[Comentario] [nvarchar](max) NOT NULL,
	[FechaRegistro] [datetime] NOT NULL,
	[UsuarioRegistro] [nvarchar](100) NULL,
PRIMARY KEY CLUSTERED 
(
	[EvaluacionCreditoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[failed_jobs]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[failed_jobs](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[uuid] [nvarchar](255) NOT NULL,
	[connection] [nvarchar](max) NOT NULL,
	[queue] [nvarchar](max) NOT NULL,
	[payload] [nvarchar](max) NOT NULL,
	[exception] [nvarchar](max) NOT NULL,
	[failed_at] [datetime] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Giro]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Giro](
	[GiroID] [int] IDENTITY(1,1) NOT NULL,
	[Codigo] [nvarchar](10) NOT NULL,
	[Descripcion] [nvarchar](200) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[GiroID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[Codigo] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[job_batches]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[job_batches](
	[id] [nvarchar](255) NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[total_jobs] [int] NOT NULL,
	[pending_jobs] [int] NOT NULL,
	[failed_jobs] [int] NOT NULL,
	[failed_job_ids] [nvarchar](max) NOT NULL,
	[options] [nvarchar](max) NULL,
	[cancelled_at] [int] NULL,
	[created_at] [int] NOT NULL,
	[finished_at] [int] NULL,
 CONSTRAINT [job_batches_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[jobs]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[jobs](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[queue] [nvarchar](255) NOT NULL,
	[payload] [nvarchar](max) NOT NULL,
	[attempts] [tinyint] NOT NULL,
	[reserved_at] [int] NULL,
	[available_at] [int] NOT NULL,
	[created_at] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[migrations]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[migrations](
	[id] [int] IDENTITY(1,1) NOT NULL,
	[migration] [nvarchar](255) NOT NULL,
	[batch] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[model_has_permissions]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[model_has_permissions](
	[permission_id] [bigint] NOT NULL,
	[model_type] [nvarchar](255) NOT NULL,
	[model_id] [bigint] NOT NULL,
 CONSTRAINT [model_has_permissions_permission_model_type_primary] PRIMARY KEY CLUSTERED 
(
	[permission_id] ASC,
	[model_id] ASC,
	[model_type] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[model_has_roles]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[model_has_roles](
	[role_id] [bigint] NOT NULL,
	[model_type] [nvarchar](255) NOT NULL,
	[model_id] [bigint] NOT NULL,
 CONSTRAINT [model_has_roles_role_model_type_primary] PRIMARY KEY CLUSTERED 
(
	[role_id] ASC,
	[model_id] ASC,
	[model_type] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Negocio]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Negocio](
	[NegocioID] [int] IDENTITY(1,1) NOT NULL,
	[ClienteID] [int] NOT NULL,
	[DireccionNegocio] [nvarchar](500) NOT NULL,
	[Antiguedad] [decimal](5, 2) NULL,
	[GiroID] [int] NULL,
	[SubGiroID] [int] NULL,
	[Ubicacion] [nvarchar](20) NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[NegocioID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[NivelAprobacion]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[NivelAprobacion](
	[NivelAprobacionID] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](50) NOT NULL,
	[MontoMinimo] [decimal](12, 2) NOT NULL,
	[MontoMaximo] [decimal](12, 2) NOT NULL,
	[Orden] [int] NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[NivelAprobacionID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[Nombre] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Pago]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Pago](
	[PagoID] [int] IDENTITY(1,1) NOT NULL,
	[CreditoID] [int] NOT NULL,
	[PromotorCobradorID] [int] NOT NULL,
	[MontoPagado] [decimal](12, 2) NOT NULL,
	[FechaPago] [datetime] NOT NULL,
	[EsMora] [bit] NOT NULL,
	[EsPagoAMayor] [bit] NOT NULL,
	[EsPagoForzado] [bit] NOT NULL,
	[Comentario] [nvarchar](500) NULL,
	[UsuarioRegistro] [nvarchar](100) NULL,
	[Activo] [bit] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[PagoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[password_reset_tokens]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[password_reset_tokens](
	[email] [nvarchar](255) NOT NULL,
	[token] [nvarchar](255) NOT NULL,
	[created_at] [datetime] NULL,
 CONSTRAINT [password_reset_tokens_email_primary] PRIMARY KEY CLUSTERED 
(
	[email] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[permissions]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[permissions](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[guard_name] [nvarchar](255) NOT NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[PromotorCobrador]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[PromotorCobrador](
	[PromotorCobradorID] [int] IDENTITY(1,1) NOT NULL,
	[Codigo] [nvarchar](20) NOT NULL,
	[Descripcion] [nvarchar](200) NOT NULL,
	[Ciudad] [nvarchar](100) NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[PromotorCobradorID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[Codigo] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[ProposicionCredito]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[ProposicionCredito](
	[ProposicionCreditoID] [int] IDENTITY(1,1) NOT NULL,
	[CodigoCredito] [nvarchar](20) NOT NULL,
	[ClienteID] [int] NOT NULL,
	[CodigoCliente] [nvarchar](20) NOT NULL,
	[TipoCreditoID] [int] NOT NULL,
	[MontoTotal] [decimal](12, 2) NOT NULL,
	[TasaID] [int] NOT NULL,
	[TasaInteres] [decimal](5, 2) NOT NULL,
	[Plazo] [int] NOT NULL,
	[NumeroCuotas] [int] NOT NULL,
	[MontoCuota] [decimal](12, 2) NOT NULL,
	[MontoInteres] [decimal](12, 2) NOT NULL,
	[TasaMora] [decimal](5, 2) NOT NULL,
	[ZonaID] [int] NULL,
	[Observaciones] [nvarchar](max) NULL,
	[UserProponenteID] [bigint] NOT NULL,
	[FechaPropuesta] [datetime] NOT NULL,
	[Estado] [nvarchar](20) NOT NULL,
	[NivelAprobacionRequerido] [int] NULL,
	[UserAprobadorID] [bigint] NULL,
	[FechaAprobacion] [datetime] NULL,
	[ComentarioAprobacion] [nvarchar](500) NULL,
	[FechaDesembolso] [datetime] NULL,
	[UserDesembolsoID] [bigint] NULL,
	[FechaModificacion] [datetime] NULL,
	[UserModificacionID] [bigint] NULL,
	[Activo] [bit] NOT NULL,
	[SaldoPendiente] [decimal](12, 2) NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[ProposicionCreditoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[CodigoCredito] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[role_has_permissions]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[role_has_permissions](
	[permission_id] [bigint] NOT NULL,
	[role_id] [bigint] NOT NULL,
 CONSTRAINT [role_has_permissions_permission_id_role_id_primary] PRIMARY KEY CLUSTERED 
(
	[permission_id] ASC,
	[role_id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[roles]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[roles](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[guard_name] [nvarchar](255) NOT NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[sessions]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[sessions](
	[id] [nvarchar](255) NOT NULL,
	[user_id] [bigint] NULL,
	[ip_address] [nvarchar](45) NULL,
	[user_agent] [nvarchar](max) NULL,
	[payload] [nvarchar](max) NOT NULL,
	[last_activity] [int] NOT NULL,
 CONSTRAINT [sessions_id_primary] PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
/****** Object:  Table [dbo].[SubGiro]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[SubGiro](
	[SubGiroID] [int] IDENTITY(1,1) NOT NULL,
	[GiroID] [int] NOT NULL,
	[Descripcion] [nvarchar](200) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[SubGiroID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_SubGiro_Giro] UNIQUE NONCLUSTERED 
(
	[GiroID] ASC,
	[Descripcion] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Tasa]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Tasa](
	[TasaID] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](50) NOT NULL,
	[Valor] [decimal](5, 2) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
	[Dias] [int] NOT NULL,
	[Cuotas] [int] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[TasaID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[TelefonoNegocio]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[TelefonoNegocio](
	[TelefonoNegocioID] [int] IDENTITY(1,1) NOT NULL,
	[NegocioID] [int] NOT NULL,
	[Telefono] [nvarchar](20) NOT NULL,
	[TipoTelefono] [nvarchar](20) NULL,
	[Observacion] [nvarchar](200) NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[TelefonoNegocioID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[TipoCredito]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[TipoCredito](
	[TipoCreditoID] [int] IDENTITY(1,1) NOT NULL,
	[Codigo] [nvarchar](10) NOT NULL,
	[Descripcion] [nvarchar](100) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[TipoCreditoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[Codigo] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[TipoPago]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[TipoPago](
	[TipoPagoID] [int] IDENTITY(1,1) NOT NULL,
	[Nombre] [nvarchar](50) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[TipoPagoID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
UNIQUE NONCLUSTERED 
(
	[Nombre] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[UserNivelAprobacion]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[UserNivelAprobacion](
	[UserNivelAprobacionID] [int] IDENTITY(1,1) NOT NULL,
	[UserID] [bigint] NOT NULL,
	[NivelAprobacionID] [int] NOT NULL,
	[FechaAsignacion] [datetime] NOT NULL,
	[Activo] [bit] NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[UserNivelAprobacionID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_UserNivel_User] UNIQUE NONCLUSTERED 
(
	[UserID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[users]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[users](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[email] [nvarchar](255) NULL,
	[email_verified_at] [datetime] NULL,
	[password] [nvarchar](255) NOT NULL,
	[remember_token] [nvarchar](100) NULL,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL,
	[username] [nvarchar](255) NOT NULL,
PRIMARY KEY CLUSTERED 
(
	[id] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_users_username] UNIQUE NONCLUSTERED 
(
	[username] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
/****** Object:  Table [dbo].[Zona]    Script Date: 19/12/2025 08:34:19 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Zona](
	[ZonaID] [int] IDENTITY(1,1) NOT NULL,
	[CiudadID] [int] NOT NULL,
	[Nombre] [nvarchar](100) NOT NULL,
	[Activo] [bit] NOT NULL,
	[FechaCreacion] [datetime] NOT NULL,
	[FechaModificacion] [datetime] NULL,
PRIMARY KEY CLUSTERED 
(
	[ZonaID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY],
 CONSTRAINT [UQ_Zona_Ciudad] UNIQUE NONCLUSTERED 
(
	[CiudadID] ASC,
	[Nombre] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON, OPTIMIZE_FOR_SEQUENTIAL_KEY = OFF) ON [PRIMARY]
) ON [PRIMARY]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT ((0.00)) FOR [CapitalManifestado]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT ((0.00)) FOR [CapitalEstimado]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT ((0.00)) FOR [VentaManifestadaMin]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT ((0.00)) FOR [VentaManifestadaMax]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT ((0.00)) FOR [VentaEstimada]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT (getdate()) FOR [FechaAnalisis]
GO
ALTER TABLE [dbo].[AnalisisEconomico] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[AprobacionProposicion] ADD  DEFAULT ('PENDIENTE') FOR [Estado]
GO
ALTER TABLE [dbo].[AprobacionProposicion] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[Ciudad] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Ciudad] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[Cliente] ADD  DEFAULT ('NO OBSERVADO') FOR [Estado]
GO
ALTER TABLE [dbo].[Cliente] ADD  DEFAULT ((0.00)) FOR [MontoMaxRecomendado]
GO
ALTER TABLE [dbo].[Cliente] ADD  DEFAULT (getdate()) FOR [FechaRegistro]
GO
ALTER TABLE [dbo].[Cliente] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Credito] ADD  DEFAULT (getdate()) FOR [FechaGeneracion]
GO
ALTER TABLE [dbo].[Credito] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[DocumentoCliente] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[DocumentoCliente] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[EvaluacionCredito] ADD  DEFAULT (getdate()) FOR [FechaRegistro]
GO
ALTER TABLE [dbo].[failed_jobs] ADD  DEFAULT (getdate()) FOR [failed_at]
GO
ALTER TABLE [dbo].[Giro] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Giro] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[Negocio] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Negocio] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[NivelAprobacion] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[NivelAprobacion] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[Pago] ADD  DEFAULT (getdate()) FOR [FechaPago]
GO
ALTER TABLE [dbo].[Pago] ADD  DEFAULT ((0)) FOR [EsMora]
GO
ALTER TABLE [dbo].[Pago] ADD  DEFAULT ((0)) FOR [EsPagoAMayor]
GO
ALTER TABLE [dbo].[Pago] ADD  DEFAULT ((0)) FOR [EsPagoForzado]
GO
ALTER TABLE [dbo].[Pago] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[PromotorCobrador] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[PromotorCobrador] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[ProposicionCredito] ADD  DEFAULT ((0.00)) FOR [TasaMora]
GO
ALTER TABLE [dbo].[ProposicionCredito] ADD  DEFAULT (getdate()) FOR [FechaPropuesta]
GO
ALTER TABLE [dbo].[ProposicionCredito] ADD  DEFAULT ('PENDIENTE') FOR [Estado]
GO
ALTER TABLE [dbo].[ProposicionCredito] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[ProposicionCredito] ADD  DEFAULT ((0.00)) FOR [SaldoPendiente]
GO
ALTER TABLE [dbo].[SubGiro] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[SubGiro] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[Tasa] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Tasa] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[TelefonoNegocio] ADD  DEFAULT ('PRINCIPAL') FOR [TipoTelefono]
GO
ALTER TABLE [dbo].[TelefonoNegocio] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[TelefonoNegocio] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[TipoCredito] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[TipoCredito] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[TipoPago] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[TipoPago] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[UserNivelAprobacion] ADD  DEFAULT (getdate()) FOR [FechaAsignacion]
GO
ALTER TABLE [dbo].[UserNivelAprobacion] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Zona] ADD  DEFAULT ((1)) FOR [Activo]
GO
ALTER TABLE [dbo].[Zona] ADD  DEFAULT (getdate()) FOR [FechaCreacion]
GO
ALTER TABLE [dbo].[AnalisisEconomico]  WITH CHECK ADD  CONSTRAINT [FK_AnalisisEconomico_Cliente] FOREIGN KEY([ClienteID])
REFERENCES [dbo].[Cliente] ([ClienteID])
GO
ALTER TABLE [dbo].[AnalisisEconomico] CHECK CONSTRAINT [FK_AnalisisEconomico_Cliente]
GO
ALTER TABLE [dbo].[AprobacionProposicion]  WITH CHECK ADD  CONSTRAINT [FK_AprobacionProposicion_Nivel] FOREIGN KEY([NivelAprobacionID])
REFERENCES [dbo].[NivelAprobacion] ([NivelAprobacionID])
GO
ALTER TABLE [dbo].[AprobacionProposicion] CHECK CONSTRAINT [FK_AprobacionProposicion_Nivel]
GO
ALTER TABLE [dbo].[AprobacionProposicion]  WITH CHECK ADD  CONSTRAINT [FK_AprobacionProposicion_Proposicion] FOREIGN KEY([ProposicionCreditoID])
REFERENCES [dbo].[ProposicionCredito] ([ProposicionCreditoID])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[AprobacionProposicion] CHECK CONSTRAINT [FK_AprobacionProposicion_Proposicion]
GO
ALTER TABLE [dbo].[AprobacionProposicion]  WITH CHECK ADD  CONSTRAINT [FK_AprobacionProposicion_Usuario] FOREIGN KEY([UserAprobadorID])
REFERENCES [dbo].[users] ([id])
ON DELETE SET NULL
GO
ALTER TABLE [dbo].[AprobacionProposicion] CHECK CONSTRAINT [FK_AprobacionProposicion_Usuario]
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD  CONSTRAINT [FK_Cliente_Ciudad] FOREIGN KEY([CiudadID])
REFERENCES [dbo].[Ciudad] ([CiudadID])
GO
ALTER TABLE [dbo].[Cliente] CHECK CONSTRAINT [FK_Cliente_Ciudad]
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD  CONSTRAINT [FK_Cliente_Garante] FOREIGN KEY([GaranteID])
REFERENCES [dbo].[Cliente] ([ClienteID])
GO
ALTER TABLE [dbo].[Cliente] CHECK CONSTRAINT [FK_Cliente_Garante]
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD  CONSTRAINT [FK_Cliente_PromotorCobrador] FOREIGN KEY([PromotorCobradorID])
REFERENCES [dbo].[PromotorCobrador] ([PromotorCobradorID])
GO
ALTER TABLE [dbo].[Cliente] CHECK CONSTRAINT [FK_Cliente_PromotorCobrador]
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD  CONSTRAINT [FK_Cliente_Tasa] FOREIGN KEY([TasaID])
REFERENCES [dbo].[Tasa] ([TasaID])
GO
ALTER TABLE [dbo].[Cliente] CHECK CONSTRAINT [FK_Cliente_Tasa]
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD  CONSTRAINT [FK_Cliente_Zona] FOREIGN KEY([ZonaID])
REFERENCES [dbo].[Zona] ([ZonaID])
GO
ALTER TABLE [dbo].[Cliente] CHECK CONSTRAINT [FK_Cliente_Zona]
GO
ALTER TABLE [dbo].[Credito]  WITH CHECK ADD  CONSTRAINT [FK_Credito_Proposicion] FOREIGN KEY([ProposicionCreditoID])
REFERENCES [dbo].[ProposicionCredito] ([ProposicionCreditoID])
GO
ALTER TABLE [dbo].[Credito] CHECK CONSTRAINT [FK_Credito_Proposicion]
GO
ALTER TABLE [dbo].[Credito]  WITH CHECK ADD  CONSTRAINT [FK_Credito_TipoPago] FOREIGN KEY([TipoPagoID])
REFERENCES [dbo].[TipoPago] ([TipoPagoID])
GO
ALTER TABLE [dbo].[Credito] CHECK CONSTRAINT [FK_Credito_TipoPago]
GO
ALTER TABLE [dbo].[DocumentoCliente]  WITH CHECK ADD  CONSTRAINT [FK_DocumentoCliente_Cliente] FOREIGN KEY([ClienteID])
REFERENCES [dbo].[Cliente] ([ClienteID])
GO
ALTER TABLE [dbo].[DocumentoCliente] CHECK CONSTRAINT [FK_DocumentoCliente_Cliente]
GO
ALTER TABLE [dbo].[EvaluacionCredito]  WITH CHECK ADD  CONSTRAINT [FK_EvaluacionCredito_Cliente] FOREIGN KEY([ClienteID])
REFERENCES [dbo].[Cliente] ([ClienteID])
GO
ALTER TABLE [dbo].[EvaluacionCredito] CHECK CONSTRAINT [FK_EvaluacionCredito_Cliente]
GO
ALTER TABLE [dbo].[model_has_permissions]  WITH CHECK ADD  CONSTRAINT [model_has_permissions_permission_id_foreign] FOREIGN KEY([permission_id])
REFERENCES [dbo].[permissions] ([id])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[model_has_permissions] CHECK CONSTRAINT [model_has_permissions_permission_id_foreign]
GO
ALTER TABLE [dbo].[model_has_roles]  WITH CHECK ADD  CONSTRAINT [model_has_roles_role_id_foreign] FOREIGN KEY([role_id])
REFERENCES [dbo].[roles] ([id])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[model_has_roles] CHECK CONSTRAINT [model_has_roles_role_id_foreign]
GO
ALTER TABLE [dbo].[Negocio]  WITH CHECK ADD  CONSTRAINT [FK_Negocio_Cliente] FOREIGN KEY([ClienteID])
REFERENCES [dbo].[Cliente] ([ClienteID])
GO
ALTER TABLE [dbo].[Negocio] CHECK CONSTRAINT [FK_Negocio_Cliente]
GO
ALTER TABLE [dbo].[Negocio]  WITH CHECK ADD  CONSTRAINT [FK_Negocio_Giro] FOREIGN KEY([GiroID])
REFERENCES [dbo].[Giro] ([GiroID])
GO
ALTER TABLE [dbo].[Negocio] CHECK CONSTRAINT [FK_Negocio_Giro]
GO
ALTER TABLE [dbo].[Negocio]  WITH CHECK ADD  CONSTRAINT [FK_Negocio_SubGiro] FOREIGN KEY([SubGiroID])
REFERENCES [dbo].[SubGiro] ([SubGiroID])
GO
ALTER TABLE [dbo].[Negocio] CHECK CONSTRAINT [FK_Negocio_SubGiro]
GO
ALTER TABLE [dbo].[Pago]  WITH CHECK ADD  CONSTRAINT [FK_Pago_Cobrador] FOREIGN KEY([PromotorCobradorID])
REFERENCES [dbo].[PromotorCobrador] ([PromotorCobradorID])
GO
ALTER TABLE [dbo].[Pago] CHECK CONSTRAINT [FK_Pago_Cobrador]
GO
ALTER TABLE [dbo].[Pago]  WITH CHECK ADD  CONSTRAINT [FK_Pago_Credito] FOREIGN KEY([CreditoID])
REFERENCES [dbo].[Credito] ([CreditoID])
GO
ALTER TABLE [dbo].[Pago] CHECK CONSTRAINT [FK_Pago_Credito]
GO
ALTER TABLE [dbo].[ProposicionCredito]  WITH CHECK ADD  CONSTRAINT [FK_ProposicionCredito_Cliente] FOREIGN KEY([ClienteID])
REFERENCES [dbo].[Cliente] ([ClienteID])
GO
ALTER TABLE [dbo].[ProposicionCredito] CHECK CONSTRAINT [FK_ProposicionCredito_Cliente]
GO
ALTER TABLE [dbo].[ProposicionCredito]  WITH CHECK ADD  CONSTRAINT [FK_ProposicionCredito_NivelAprobacion] FOREIGN KEY([NivelAprobacionRequerido])
REFERENCES [dbo].[NivelAprobacion] ([NivelAprobacionID])
GO
ALTER TABLE [dbo].[ProposicionCredito] CHECK CONSTRAINT [FK_ProposicionCredito_NivelAprobacion]
GO
ALTER TABLE [dbo].[ProposicionCredito]  WITH CHECK ADD  CONSTRAINT [FK_ProposicionCredito_Tasa] FOREIGN KEY([TasaID])
REFERENCES [dbo].[Tasa] ([TasaID])
GO
ALTER TABLE [dbo].[ProposicionCredito] CHECK CONSTRAINT [FK_ProposicionCredito_Tasa]
GO
ALTER TABLE [dbo].[ProposicionCredito]  WITH CHECK ADD  CONSTRAINT [FK_ProposicionCredito_TipoCredito] FOREIGN KEY([TipoCreditoID])
REFERENCES [dbo].[TipoCredito] ([TipoCreditoID])
GO
ALTER TABLE [dbo].[ProposicionCredito] CHECK CONSTRAINT [FK_ProposicionCredito_TipoCredito]
GO
ALTER TABLE [dbo].[ProposicionCredito]  WITH CHECK ADD  CONSTRAINT [FK_ProposicionCredito_Zona] FOREIGN KEY([ZonaID])
REFERENCES [dbo].[Zona] ([ZonaID])
GO
ALTER TABLE [dbo].[ProposicionCredito] CHECK CONSTRAINT [FK_ProposicionCredito_Zona]
GO
ALTER TABLE [dbo].[role_has_permissions]  WITH CHECK ADD  CONSTRAINT [role_has_permissions_permission_id_foreign] FOREIGN KEY([permission_id])
REFERENCES [dbo].[permissions] ([id])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[role_has_permissions] CHECK CONSTRAINT [role_has_permissions_permission_id_foreign]
GO
ALTER TABLE [dbo].[role_has_permissions]  WITH CHECK ADD  CONSTRAINT [role_has_permissions_role_id_foreign] FOREIGN KEY([role_id])
REFERENCES [dbo].[roles] ([id])
ON DELETE CASCADE
GO
ALTER TABLE [dbo].[role_has_permissions] CHECK CONSTRAINT [role_has_permissions_role_id_foreign]
GO
ALTER TABLE [dbo].[SubGiro]  WITH CHECK ADD  CONSTRAINT [FK_SubGiro_Giro] FOREIGN KEY([GiroID])
REFERENCES [dbo].[Giro] ([GiroID])
GO
ALTER TABLE [dbo].[SubGiro] CHECK CONSTRAINT [FK_SubGiro_Giro]
GO
ALTER TABLE [dbo].[TelefonoNegocio]  WITH CHECK ADD  CONSTRAINT [FK_TelefonoNegocio_Negocio] FOREIGN KEY([NegocioID])
REFERENCES [dbo].[Negocio] ([NegocioID])
GO
ALTER TABLE [dbo].[TelefonoNegocio] CHECK CONSTRAINT [FK_TelefonoNegocio_Negocio]
GO
ALTER TABLE [dbo].[UserNivelAprobacion]  WITH CHECK ADD  CONSTRAINT [FK_UserNivel_NivelAprobacion] FOREIGN KEY([NivelAprobacionID])
REFERENCES [dbo].[NivelAprobacion] ([NivelAprobacionID])
GO
ALTER TABLE [dbo].[UserNivelAprobacion] CHECK CONSTRAINT [FK_UserNivel_NivelAprobacion]
GO
ALTER TABLE [dbo].[Zona]  WITH CHECK ADD  CONSTRAINT [FK_Zona_Ciudad] FOREIGN KEY([CiudadID])
REFERENCES [dbo].[Ciudad] ([CiudadID])
GO
ALTER TABLE [dbo].[Zona] CHECK CONSTRAINT [FK_Zona_Ciudad]
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD CHECK  (([Estado]='NO OBSERVADO' OR [Estado]='OBSERVADO'))
GO
ALTER TABLE [dbo].[Cliente]  WITH CHECK ADD CHECK  (([Sexo]='F' OR [Sexo]='M'))
GO
ALTER TABLE [dbo].[DocumentoCliente]  WITH CHECK ADD CHECK  (([TipoDocumento]='OTROS' OR [TipoDocumento]='RECIBO_SERVICIO' OR [TipoDocumento]='DNI'))
GO
ALTER TABLE [dbo].[Negocio]  WITH CHECK ADD CHECK  (([Ubicacion]='REGULAR' OR [Ubicacion]='BUENO' OR [Ubicacion]='MALO'))
GO
ALTER TABLE [dbo].[ProposicionCredito]  WITH CHECK ADD CHECK  (([Estado]='CANCELADO' OR [Estado]='DESEMBOLSADO' OR [Estado]='RECHAZADO' OR [Estado]='APROBADO' OR [Estado]='PENDIENTE'))
GO
ALTER TABLE [dbo].[TelefonoNegocio]  WITH CHECK ADD CHECK  (([TipoTelefono]='ALTERNATIVO' OR [TipoTelefono]='SECUNDARIO' OR [TipoTelefono]='PRINCIPAL'))
GO
