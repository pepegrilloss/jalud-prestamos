/**
 * ====================================================================
 * MÓDULO DE EXONERACIONES Y DESCUENTOS - DOCUMENTACIÓN TÉCNICA
 * ====================================================================
 * 
 * Sistema de Gestión de Préstamos - JALUD
 * Versión: 1.0
 * Fecha: 07/02/2026
 */

// ====================================================================
// 1. ARQUITECTURA DEL MÓDULO
// ====================================================================

/*
El módulo está estructurado en 5 tablas principales:

┌─────────────────────────────────────────────────────────────────────┐
│                    TABLAS DEL MÓDULO                                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  TipoExoneracion (CATÁLOGO)                                          │
│  ├─ TipoExoneracionID (PK)                                           │
│  ├─ Codigo (CHAR 1): P, I, M                                        │
│  ├─ Nombre: Pronto Pago, Interés, Mora                              │
│  └─ Descripcion                                                      │
│                                                                      │
│  SolicitudExoneracion (SOLICITUD)                                    │
│  ├─ SolicitudExoneracionID (PK)                                      │
│  ├─ CreditoID (FK → Credito)                                        │
│  ├─ TipoExoneracionID (FK → TipoExoneracion)                        │
│  ├─ MontoDisponible (monto máximo a exonerar)                       │
│  ├─ MontoExonerado (monto solicitado)                               │
│  ├─ Estado: PENDIENTE, APROBADO, RECHAZADO                          │
│  ├─ UserSolicitanteID (quién solicita)                              │
│  ├─ NivelAprobacionRequerido (FK → NivelAprobacion)                 │
│  ├─ UserAprobadorID (quién aprueba)                                 │
│  └─ PagoGeneradoID (FK → pago, el pago automático creado)           │
│                                                                      │
│  AprobacionExoneracion (FLUJO MULTINIVEL)                           │
│  ├─ AprobacionExoneracionID (PK)                                     │
│  ├─ SolicitudExoneracionID (FK)                                      │
│  ├─ NivelAprobacionID (FK)                                           │
│  ├─ UserAprobadorID (FK)                                             │
│  └─ Estado: PENDIENTE, APROBADO, RECHAZADO                          │
│                                                                      │
│  HistorialExoneracion (AUDITORÍA)                                   │
│  └─ Registro histórico de todas las exoneraciones aplicadas         │
│                                                                      │
│  pago (TABLA EXISTENTE - MODIFICADA)                                │
│  └─ Agregada columna TipoConcepto: C, I, M, P                       │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
*/

// ====================================================================
// 2. TIPOS DE EXONERACIÓN
// ====================================================================

/*
┌─────────────────────────────────────────────────────────────────────┐
│ TIPO P: PRONTO PAGO                                                 │
├─────────────────────────────────────────────────────────────────────┤
│ Qué es:  Bonificación por pagos puntuales                          │
│ Aplica a: Cuota pendiente (generalmente la última)                 │
│ Requisito: Cliente sin atrasos en su historial                     │
│ Cálculo: Se exonera el monto completo de la cuota                  │
│          como recompensa por buen comportamiento de pago           │
│                                                                     │
│ Validación:                                                         │
│   - Verificar que NO haya cuotas atrasadas (DiasAtraso > 0)         │
│   - Validar que sea cuota pendiente                                 │
│   - Usuario solicitante ≠ Usuario aprobador                        │
│                                                                     │
│ Ejemplo:                                                            │
│   Cliente: Juan Pérez                                              │
│   Crédito: CLI-001                                                 │
│   Última Cuota: $150.00                                             │
│   Situación: Ha pagado todas a tiempo (0 atrasos)                  │
│   Acción: Exonerar $150.00 como premio                             │
│   Resultado: Crédito completamente pagado                          │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ TIPO I: INTERÉS                                                     │
├─────────────────────────────────────────────────────────────────────┤
│ Qué es:  Reducir o eliminar intereses pendientes                  │
│ Aplica a: Monto total de intereses del crédito                    │
│ Requisito: Saldo pendiente > 0                                     │
│ Cálculo: MontoInteres (de ProposicionCredito)                      │
│          - Intereses exonerados previamente                         │
│          = Monto disponible para exonerar                           │
│                                                                     │
│ Validación:                                                         │
│   - Verificar monto disponible > 0                                  │
│   - Monto a exonerar ≤ Monto disponible                            │
│   - No duplicidad (1 solicitud pendiente por tipo)                 │
│                                                                     │
│ Ejemplo:                                                            │
│   Crédito: CLI-002                                                 │
│   Monto Interés Total: $500.00                                      │
│   Ya Exonerado: $100.00                                             │
│   Disponible: $400.00                                               │
│   Solicitar: $200.00 (exoneración parcial)                          │
│   Resultado: Quedan $200.00 disponibles para futuras solicitudes   │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ TIPO M: MORA                                                        │
├─────────────────────────────────────────────────────────────────────┤
│ Qué es:  Reducir o eliminar mora acumulada por atrasos            │
│ Aplica a: Mora generada por pagos atrasados                       │
│ Requisito: DiasAtraso > 0 en alguna cuota                          │
│ Cálculo: Suma de MontoMora de cuotas atrasadas                    │
│          - Mora exonerada previamente                               │
│          = Monto disponible                                         │
│                                                                     │
│ Validación:                                                         │
│   - Verificar que exista mora acumulada > 0                         │
│   - Monto a exonerar ≤ Mora disponible                             │
│   - Fecha de solicitud después de generar mora                     │
│                                                                     │
│ Ejemplo:                                                            │
│   Cuota 1: Vencimiento 15/01/2026, Hoy 20/02/2026 (36 días)        │
│   TasaMora: 1.00 sol/día                                            │
│   MontoMora: 36.00 soles                                            │
│   Cuota 2: Vencimiento 15/02/2026, Hoy 20/02/2026 (5 días)         │
│   MontoMora: 5.00 soles                                             │
│   Total Mora: 41.00 soles                                           │
│   Solicitar: Exonerar 30.00 soles (dejar 11.00 pendiente)          │
└─────────────────────────────────────────────────────────────────────┘
*/

// ====================================================================
// 3. FLUJO DE APROBACIÓN
// ====================================================================

/*
PASO 1: CREAR SOLICITUD
┌────────────────────────────────────────────────────┐
│ Usuario visualiza crédito activo                   │
│ Selecciona tipo de exoneracion (P, I, M)           │
│ Ingresa:                                           │
│   - Monto a exonerar                               │
│   - Comentario/Justificación                       │
│ Sistema valida:                                    │
│   ✓ Monto ≤ Monto disponible                       │
│   ✓ No existe solicitud PENDIENTE del mismo tipo   │
│   ✓ Crédito está ACTIVO                            │
│   ✓ Usuario solicitante tiene permisos             │
│ Se crea registro en SolicitudExoneracion            │
│   Estado: PENDIENTE                                │
│   Fecha: NOW()                                      │
│   UserSolicitanteID: usuario logueado              │
└────────────────────────────────────────────────────┘

PASO 2: DETERMINAR NIVEL DE APROBACIÓN
┌────────────────────────────────────────────────────┐
│ Sistema consulta NivelAprobacion                    │
│ Busca rango donde:                                 │
│   MontoMinimo ≤ MontoExonerado ≤ MontoMaximo      │
│ Asigna NivelAprobacionRequerido                    │
│ Ejemplo:                                           │
│   Monto: $250                                      │
│   Nivel Gerente: $200-$500 ✓                       │
│   NivelAprobacionRequerido = 2 (Gerente)           │
└────────────────────────────────────────────────────┘

PASO 3: CREAR REGISTRO DE APROBACIÓN
┌────────────────────────────────────────────────────┐
│ Se crea AprobacionExoneracion                       │
│ Por cada NivelAprobacionID requerido                │
│ Estado: PENDIENTE                                  │
│                                                    │
│ En el ejemplo anterior:                            │
│   AprobacionExoneracion #1:                        │
│   - NivelAprobacionID = 1 (Supervisor)             │
│   - Estado = PENDIENTE                             │
│   AprobacionExoneracion #2:                        │
│   - NivelAprobacionID = 2 (Gerente)                │
│   - Estado = PENDIENTE                             │
│                                                    │
│ Nota: Si Gerente ≤ Supervisor, solo Gerente aprueba
└────────────────────────────────────────────────────┘

PASO 4: NOTIFICAR APROBADORES
┌────────────────────────────────────────────────────┐
│ Sistema envía notificaciones a aprobadores         │
│ Asignados por NivelAprobacion                      │
│ Información en notificación:                       │
│   - Crédito: CLI-001                               │
│   - Cliente: Juan Pérez                            │
│   - Tipo: Exoneracion de Mora                      │
│   - Monto: $50.00                                  │
│   - Solicitante: jperez (25/01/2026)               │
│   - Nivel requerido: Gerente                       │
│ Link a pantalla de aprobación                      │
└────────────────────────────────────────────────────┘

PASO 5: APROBAR O RECHAZAR
┌────────────────────────────────────────────────────┐
│ OPCIÓN A: APROBAR                                  │
│ ├─ Aprobador ingresa comentario (opcional)        │
│ ├─ Estados se actualizan a APROBADO               │
│ └─ Mensaje: "Exoneracion aprobada"                │
│                                                    │
│ OPCIÓN B: RECHAZAR                                 │
│ ├─ Aprobador DEBE ingresar motivo (requerido)     │
│ ├─ Estados se actualizan a RECHAZADO              │
│ ├─ SolicitudExoneracion.Estado = RECHAZADO        │
│ └─ NO se genera pago                               │
│ └─ Notificar al solicitante                        │
└────────────────────────────────────────────────────┘

PASO 6: VALIDAR APROBACIÓN COMPLETA
┌────────────────────────────────────────────────────┐
│ Si hay múltiples niveles:                          │
│   1. Supervisor aprueba → Estado = APROBADO       │
│   2. Gerente aprueba → Estado = APROBADO          │
│   Todas aprobadas → Procesar                       │
│                                                    │
│ Si alguna es RECHAZADA:                            │
│   Abortar proceso → Notificar solicitante         │
└────────────────────────────────────────────────────┘

PASO 7: GENERAR PAGO AUTOMÁTICO
┌────────────────────────────────────────────────────┐
│ Sistema crea registro en tabla pago:               │
│   {                                                │
│     CreditoID: <id>,                               │
│     CuotaID: NULL,                                 │
│     PromotorCobradorID: NULL,                      │
│     MontoPagado: <monto exonerado>,                │
│     FechaPago: NOW(),                              │
│     TipoPago: 'SISTEMA',                           │
│     TipoConcepto: 'I' (I, M o P según tipo),      │
│     EsMora: 1 si TipoConcepto='M', sino 0,        │
│     EsPagoAutomatico: 1,                           │
│     Comentario: 'Exoneración aprobada - ...',      │
│     UsuarioRegistro: 'UserID_<aprobador>',        │
│     Activo: 1                                      │
│   }                                                │
│                                                    │
│ Se actualiza:                                      │
│   SolicitudExoneracion.PagoGeneradoID = <pago_id> │
│   SolicitudExoneracion.FechaAprobacion = NOW()     │
│   SolicitudExoneracion.UserAprobadorID = <user>    │
└────────────────────────────────────────────────────┘

PASO 8: REGISTRAR EN HISTORIAL
┌────────────────────────────────────────────────────┐
│ Se crea HistorialExoneracion:                       │
│   {                                                │
│     SolicitudExoneracionID: <id>,                  │
│     CreditoID: <id>,                               │
│     ClienteID: <id>,                               │
│     TipoExoneracion: 'M',                          │
│     MontoExonerado: 50.00,                         │
│     FechaExoneracion: NOW(),                       │
│     UsuarioAprobador: <nombre usuario>,            │
│     Comentario: 'Cliente en dificultades ...'      │
│   }                                                │
│ Para auditoría y reportes                          │
└────────────────────────────────────────────────────┘

PASO 9: ACTUALIZAR SALDO
┌────────────────────────────────────────────────────┐
│ El sistema debe recalcular:                        │
│   ProposicionCredito.SaldoPendiente -= MontoExonerado
│   (Mediante trigger o procedimiento almacenado)    │
│                                                    │
│ Ejemplo:                                           │
│   Antes: SaldoPendiente = $150.00                  │
│   Exoneracion: $50.00                              │
│   Después: SaldoPendiente = $100.00                │
└────────────────────────────────────────────────────┘

PASO 10: NOTIFICAR RESULTADO
┌────────────────────────────────────────────────────┐
│ Notificar al solicitante:                          │
│   ✓ APROBADO: "Exoneracion de $X aprobada por ..."
│   ✗ RECHAZADO: "Exoneracion rechazada. Motivo: ..."
└────────────────────────────────────────────────────┘
*/

// ====================================================================
// 4. VALIDACIONES Y REGLAS DE NEGOCIO
// ====================================================================

/*
┌─────────────────────────────────────────────────────────────────────┐
│ VALIDACIONES CRÍTICAS                                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│ 1. VALIDACIÓN DE CRÉDITO                                            │
│    ✓ Credito.Activo = 1                                             │
│    ✓ ProposicionCredito.SaldoPendiente > 0                          │
│    ✓ No crédito cerrado (FechaCierre IS NOT NULL)                  │
│                                                                      │
│ 2. VALIDACIÓN DE MONTO                                              │
│    ✓ MontoExonerado > 0                                             │
│    ✓ MontoExonerado ≤ MontoDisponible                              │
│    ✓ MontoExonerado ≤ SaldoPendiente del crédito                   │
│                                                                      │
│ 3. VALIDACIÓN DE USUARIO                                            │
│    ✓ UserSolicitanteID ≠ UserAprobadorID                           │
│    ✓ Aprobador tiene permiso 'approve_exoneracion'                 │
│    ✓ Aprobador pertenece al NivelAprobacion requerido             │
│                                                                      │
│ 4. VALIDACIÓN DE SOLICITUD DUPLICADA                               │
│    ✓ No existe SolicitudExoneracion PENDIENTE                      │
│      para el mismo (CreditoID, TipoExoneracionID)                  │
│    ✓ Excepción: Si estado = RECHAZADO, puede crear nueva          │
│                                                                      │
│ 5. VALIDACIÓN DE TIPO ESPECÍFICO                                   │
│                                                                      │
│    PRONTO PAGO (P):                                                 │
│      ✓ Cuota pendiente > 0                                          │
│      ✓ DiasAtraso = 0 para TODAS las cuotas                         │
│      ✓ Monto exonerar = MontoCuota (cuota específica)              │
│                                                                      │
│    INTERÉS (I):                                                     │
│      ✓ ProposicionCredito.MontoInteres > 0                         │
│      ✓ Intereses no completamente exonerados antes                 │
│                                                                      │
│    MORA (M):                                                        │
│      ✓ Existe SUM(cuota.MontoMora) > 0                             │
│      ✓ Existe alguna cuota con DiasAtraso > 0                      │
│                                                                      │
│ 6. VALIDACIÓN DE APROBACIÓN                                         │
│    ✓ Todas las AprobacionExoneracion = APROBADO                    │
│    ✓ Si alguna = RECHAZADO, todo se rechaza                        │
│    ✓ Si alguna = PENDIENTE, no se genera pago aún                  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
*/

// ====================================================================
// 5. EJEMPLOS DE CASOS DE USO
// ====================================================================

/*
═══════════════════════════════════════════════════════════════════════
CASO 1: Exoneracion de MORA por dificultades económicas
═══════════════════════════════════════════════════════════════════════

Contexto:
  Cliente: María García
  DNI: 12345678
  Crédito: CLI-001
  Monto: $2,000.00
  Cuotas: 6 x $333.33
  Fecha Vencimiento: 15/07/2026

Situación Actual (20/02/2026):
  Cuota 1 (Vencimiento 15/01): NO PAGADA (36 días de atraso)
  Cuota 2 (Vencimiento 15/02): NO PAGADA (5 días de atraso)
  Cuota 3 (Vencimiento 15/03): PENDIENTE (sin atraso aún)
  
  Mora Acumulada: 36 + 5 = 41 días
  Tasa Mora: 1.00 sol/día
  MontoMora Total: $41.00

Solicitud:
  Tipo: M (Mora)
  Monto a Exonerar: $30.00
  Comentario: "Cliente en situación de desempleo temporal. Solicita exoneración de mora para poder regularizarse"
  Usuario Solicitante: Luis Pérez (Supervisor)
  Nivel Requerido: 2 (Gerente)

Aprobación:
  1. Gerente González revisa
  2. Comenta: "Aprobado. Cliente con buena historia crediticia. Dificultades temporales justificadas."
  3. Aprueba

Resultado:
  ✓ Se crea Pago automático:
      - MontoPagado: $30.00
      - TipoConcepto: 'M'
      - EsMora: 1
      - EsPagoAutomatico: 1
      - Comentario: "Exoneración aprobada - Cliente en situación de desempleo..."
  
  ✓ Mora pendiente: $41.00 - $30.00 = $11.00
  ✓ Cliente aún debe $11.00 en mora + cuotas pendientes
  ✓ Se registra en HistorialExoneracion para auditoría

═══════════════════════════════════════════════════════════════════════
CASO 2: Exoneracion de INTERÉS por cliente preferente
═══════════════════════════════════════════════════════════════════════

Contexto:
  Cliente: Carlos Rodríguez
  DNI: 87654321
  Crédito: CLI-002
  Monto: $5,000.00
  Tasa: 10% trimestral
  MontoInteres: $500.00

Situación:
  Pagos realizados: $3,000.00
  Saldo Pendiente: $2,500.00
  Intereses pagados: $0.00
  Intereses disponibles para exonerar: $500.00

Solicitud:
  Tipo: I (Interés)
  Monto a Exonerar: $500.00 (completo)
  Comentario: "Cliente preferente. Buena trayectoria de pagos. Interés exonerado como gesto comercial."
  Usuario Solicitante: Ana Sánchez (Ejecutivo)
  Nivel Requerido: 2 (Gerente)

Aprobación:
  1. Gerente González aprueba
  2. Comenta: "Aprobado. Es cliente preferente desde 2023."

Resultado:
  ✓ Se crea Pago automático:
      - MontoPagado: $500.00
      - TipoConcepto: 'I'
      - EsMora: 0
      - EsPagoAutomatico: 1
  
  ✓ Nuevo SaldoPendiente: $2,500.00 - $500.00 = $2,000.00
  ✓ Cliente ahora debe: $2,000.00 (solo capital)

═══════════════════════════════════════════════════════════════════════
CASO 3: PRONTO PAGO por cumplimiento perfecto
═══════════════════════════════════════════════════════════════════════

Contexto:
  Cliente: Pedro López
  Crédito: CLI-003
  Total Crédito: $3,000.00 en 4 cuotas = $750.00 c/u
  
Historial de Pagos:
  Cuota 1: Pagada 10/01 (5 días antes ✓)
  Cuota 2: Pagada 05/02 (10 días antes ✓)
  Cuota 3: Pagada 02/03 (13 días antes ✓)
  Cuota 4: PENDIENTE (Vencimiento 15/03/2026)

Estado Actual:
  ✓ Cero atrasos
  ✓ Historial perfecto de pagos
  ✓ La última cuota: $750.00 (PENDIENTE)

Solicitud:
  Tipo: P (Pronto Pago)
  Monto a Exonerar: $750.00 (cuota completa)
  Comentario: "Premio por excelente comportamiento de pago. Cliente siempre ha pagado adelantado."
  Usuario Solicitante: Juana Martínez (Supervisor)
  Nivel Requerido: 1 (Supervisor) - bajo monto

Aprobación:
  1. Supervisor González aprueba
  2. Comenta: "Aprobado. Cliente modelo. Excelente comportamiento."

Resultado:
  ✓ Se crea Pago automático:
      - MontoPagado: $750.00
      - TipoConcepto: 'P'
      - EsMora: 0
      - EsPagoAutomatico: 1
  
  ✓ Crédito completamente cancelado
  ✓ SaldoPendiente: $0.00
  ✓ Cliente tiene beneficio/recompensa por buen comportamiento

═══════════════════════════════════════════════════════════════════════
*/

// ====================================================================
// 6. CONSULTAS ÚTILES PARA REPORTES
// ====================================================================

/*
REPORTE 1: Exoneraciones aprobadas por período
  SELECT * FROM vw_DashboardExoneraciones 
  WHERE Fecha BETWEEN '2026-01-01' AND '2026-02-28'
  ORDER BY Fecha DESC;

REPORTE 2: Clientes con más exoneraciones
  SELECT * FROM vw_ExoneracionesPorCliente 
  ORDER BY MontoTotalExonerado DESC 
  LIMIT 20;

REPORTE 3: Solicitudes pendientes de aprobación
  SELECT * FROM vw_ExoneracionesPendientesPorNivel 
  ORDER BY DiasEnEspera DESC;

REPORTE 4: Montos disponibles por crédito
  (Usar la query del archivo EXONERACIONES_HELPERS.sql)

REPORTE 5: Estado de cada aprobación
  SELECT * FROM vw_EstadoAprobacionesExoneracion 
  WHERE EstadoGeneral = 'EN PROCESO';
*/

// ====================================================================
// 7. NOTAS IMPORTANTES DE IMPLEMENTACIÓN
// ====================================================================

/*
1. TRIGGERS NECESARIOS:
   - Actualizar ProposicionCredito.SaldoPendiente cuando se aprueba exoneracion
   - Validar integridad de datos en AprobacionExoneracion
   - Registrar cambios en HistorialExoneracion automáticamente

2. PERMISOS FILAMENT:
   Los permisos deben incluir:
   - create_solicitud_exoneracion
   - read_solicitud_exoneracion
   - update_solicitud_exoneracion
   - delete_solicitud_exoneracion (restricción: solo admin)
   - approve_exoneracion
   - view_exoneraciones_pendientes
   - view_exoneraciones_reportes

3. CÁLCULOS DINÁMICOS:
   Los montos "disponibles" se calculan EN TIEMPO REAL:
     Intereses Disponibles = MontoInteres - (SELECT SUM(MontoPagado) 
                             FROM pago WHERE TipoConcepto = 'I')
     
   Esto permite que si se aprueba una exoneracion parcial, 
   el resto se pueda solicitar después.

4. AUDITORÍA:
   ✓ Todos los cambios se registran en HistorialExoneracion
   ✓ Se mantiene registro de quién aprobó y cuándo
   ✓ Los comentarios se almacenan para trazabilidad

5. TRANSACCIONES:
   Las operaciones críticas deben estar en transacciones:
   - Crear solicitud + validar duplicidad
   - Generar pago + actualizar saldo + registrar historial

6. INTEGRACIÓN CON FILAMENT:
   - Vista de Exoneraciones debe mostrar cálculos en tiempo real
   - Botones de acción varían según estado y nivel de usuario
   - Dashboard debe mostrar KPIs principales

*/

// ====================================================================
// FIN DE DOCUMENTACIÓN
// ====================================================================
