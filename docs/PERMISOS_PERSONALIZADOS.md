# Auditoria de Permisos Personalizados

## Para quien es esta guia

Esta guia es para la persona que crea o edita roles en **Permisos personalizados**. Despues de leerla debe poder decidir que permiso asignar sin dar acceso financiero, global o administrativo por error. Cubre los 20 permisos personalizados configurados y un permiso adicional detectado en uso (`reporte_cartera`).

## Regla principal

Un permiso se otorga por necesidad de trabajo, no por jerarquia. Empieza siempre sin permisos y agrega solo los indispensables. Un usuario con varios permisos suma todas sus facultades.

Los permisos de recursos siguen el patron `view_any`, `view`, `create`, `update` y `delete`. Esta guia cubre los permisos especiales que no se entienden solo por ese patron.

## Alcance por sede

| Permiso | Para que sirve | Recomendado para | Riesgo |
| --- | --- | --- | --- |
| `ver_todas_las_sedes` | Permite consultar todas las sedes, seleccionar Gerencia y usar reportes consolidados. | Gerencia general, direccion y superadministrador. | Critico |
| `seleccionar_sedes_operativas` | Permite cambiar entre sedes operativas, pero no ingresar a Gerencia ni consultar todas las sedes a la vez. | Supervisor operativo regional. | Alto |

No asignes ambos permisos a un supervisor operativo comun. Si necesita recorrer sedes, usa solo `seleccionar_sedes_operativas`.

## Pagos y mora

| Permiso | Para que sirve | Recomendado para | Riesgo |
| --- | --- | --- | --- |
| `ver_todos_los_pagos` | Permite consultar pagos historicos; sin este permiso el usuario queda limitado al dia abierto. | Supervisor de cobranza, auditor interno. | Medio |
| `registrar_pagos_a_mayor` | Permite registrar montos que exceden el saldo ordinario del credito. | Cajero senior o responsable de cobranza. | Alto |
| `registrar_pagos_a_mayor_por_mora` | Permite aplicar pagos por encima del saldo de mora. | Responsable de cobranza con criterio financiero. | Alto |
| `registrar_pago_mora` | Permite registrar pagos especificamente destinados a mora. | Cajero o cobrador autorizado. | Medio |
| `bloquear_pago_promotor` | Permite bloquear y desbloquear el registro de pagos de un promotor o zona. | Supervisor de cobranza. | Alto |

No entregues permisos de pago a mayor a promotores cobradores ni a perfiles de consulta. Requieren validacion financiera y dejan impacto en caja.

## Apertura, cierre y reportes

| Permiso | Para que sirve | Recomendado para | Riesgo |
| --- | --- | --- | --- |
| `abrir_dia_apertura` | Permite reabrir una fecha cerrada para volver a operar. | Jefe de operaciones o administrador de sede. | Alto |
| `cerrar_dia_apertura` | Permite cerrar el dia operativo de la sede. | Jefe de operaciones o administrador de sede. | Alto |
| `balance_diario` | Permite abrir y descargar el balance diario de la sede autorizada. | Administrador de sede, jefe de caja. | Alto |
| `reporte_creditos` | Permite consultar y descargar el reporte de creditos. Respeta el alcance de sede del usuario. | Supervisor, administrador de sede, gerencia. | Medio |
| `reporte_cartera` | Permite consultar y descargar el reporte de cartera. Es un permiso operativo detectado en el sistema, aunque puede aparecer fuera del bloque configurado de personalizados. | Supervisor, administrador de sede, gerencia. | Medio |
| `descargar_excel_clientes` | Habilita la exportacion Excel del listado de clientes. | Supervisor o administrador de sede. | Medio |
| `descargar_pdf_clientes` | Habilita la exportacion PDF del listado de clientes. | Supervisor o administrador de sede. | Medio |
| `view_any_reporte::clientes::atraso` | Permite ver y descargar el reporte de clientes con atraso de la sede autorizada. | Supervisor de cobranza. | Medio |
| `view_any_reporte::clientes::inactivos` | Permite ver y descargar el reporte de clientes inactivos de la sede autorizada. | Supervisor comercial o cobranza. | Medio |

Los permisos de reporte no conceden por si solos acceso a todas las sedes. Para consolidado o Gerencia se necesita `ver_todas_las_sedes`.

## Creditos, extornos y exoneraciones

| Permiso | Para que sirve | Recomendado para | Riesgo |
| --- | --- | --- | --- |
| `editar_capital_tasa` | Permite modificar capital, tasa y zona de una proposicion desde la vista del credito. | Administrador de credito o gerencia. | Alto |
| `aprobar_extornos` | Permite aprobar o rechazar solicitudes de extorno/devolucion. Puede afectar pagos, excedentes y caja abierta. | Gerencia o responsable financiero. | Critico |
| `aprobar_exoneraciones` | Permite aprobar o rechazar exoneraciones. Puede generar pagos automaticos y alterar el saldo del credito. | Gerencia o responsable financiero. | Critico |
| `eliminar_credito` | Permite eliminar un credito cuando las reglas operativas lo permiten. | Solo administrador de credito de alta confianza. | Critico |

Estos permisos no deben combinarse en un mismo perfil operativo si la empresa requiere separacion de funciones. Lo recomendable es que quien registra una solicitud no sea quien la aprueba.

## Compras y facturas

| Permiso | Para que sirve | Recomendado para | Riesgo |
| --- | --- | --- | --- |
| `page_FacturasPendientes` | Permite ingresar a la pantalla de facturas pendientes en el panel operativo. | Encargado de compras o caja chica. | Alto |

Este permiso permite trabajar facturas pendientes dentro de la sede del usuario. El acceso de Gerencia se controla aparte mediante `ver_todas_las_sedes`.

## Roles sugeridos

| Perfil | Permisos personalizados sugeridos |
| --- | --- |
| Promotor cobrador | `registrar_pago_mora` solo cuando sea necesario. No asignar pagos a mayor, aperturas, cierres, aprobaciones ni permisos de sede global. |
| Cajero | `registrar_pago_mora`, `balance_diario` segun responsabilidad. |
| Supervisor operativo | `seleccionar_sedes_operativas`, `ver_todos_los_pagos`, reportes de cartera/creditos segun necesidad. |
| Supervisor de cobranza | `seleccionar_sedes_operativas`, `ver_todos_los_pagos`, `bloquear_pago_promotor`, reportes de atraso e inactivos. |
| Administrador de sede | Apertura/cierre, reportes necesarios y autorizaciones financieras solo si el proceso lo exige. |
| Gerencia | `ver_todas_las_sedes`, reportes y aprobaciones financieras segun la separacion de funciones definida. |
| Superadministrador | Gestion de usuarios, roles y configuracion. Debe ser un grupo muy reducido. |

## Antes de guardar un rol

1. Confirma la sede asignada al usuario.
2. Evita `ver_todas_las_sedes` salvo para Gerencia o direccion.
3. Revisa por separado permisos de dinero: pago a mayor, caja, cierres, extornos y exoneraciones.
4. No asignes simultaneamente registro y aprobacion financiera sin una decision expresa de Gerencia.
5. Prueba el rol con un usuario de prueba antes de usarlo en produccion.

## Nota de seguridad

La administracion de usuarios, roles y niveles de aprobacion esta limitada a `super_admin` y al rol `admin`. Los perfiles operativos no deben recibir esos roles ni permisos equivalentes.
