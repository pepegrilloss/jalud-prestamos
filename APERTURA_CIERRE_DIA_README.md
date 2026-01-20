# Sistema de Apertura/Cierre de Día - Documentación

## ¿Qué es?

Este sistema controla el acceso a todas las operaciones del CRUD (Crear, Editar, Eliminar) en la aplicación. Cuando el día de operaciones está **CERRADO**, ningún usuario podrá realizar ninguna operación EXCEPTO:

- **Creación de usuarios** (solo administrador)
- **Gestión de apertura/cierre de día** (solo administrador)

## Componentes Implementados

### 1. **Migration**: `2026_01_19_000001_create_apertura_cierre_dia_table.php`
- Crea la tabla `apertura_cierre_dia`
- Campos:
  - `AperturaCierreDiaID` - ID primario
  - `Fecha` - Fecha única del día
  - `FechaApertura` - Timestamp de apertura
  - `FechaCierre` - Timestamp de cierre
  - `EstadoDia` - ENUM: 'ABIERTO' | 'CERRADO'
  - `UsuarioAperturaID` - Usuario que abrió el día
  - `UsuarioCierreID` - Usuario que cerró el día
  - `Observaciones` - Notas adicionales

### 2. **Model**: `app/Models/AperturaCierreDia.php`
Métodos útiles:
```php
AperturaCierreDia::hoyOHoy()              // Obtiene registro de hoy
AperturaCierreDia::estaAbierto()          // Verifica si hoy está ABIERTO
AperturaCierreDia::estadoDiaActual()      // Retorna estado actual
```

### 3. **Resource**: `app/Filament/Resources/AperturaCierreDiaResource.php`
- CRUD completo para gestionar apertura/cierre
- **Solo administradores** pueden acceder
- Ubicación en menú: Administración > Apertura/Cierre Día

### 4. **Service**: `app/Services/ValidacionDiaService.php`
- Validación centralizada
- Métodos principales:
  ```php
  ValidacionDiaService::validarParaOperacion()      // Valida antes de CRUD
  ValidacionDiaService::validarAccesoRecurso()      // Valida recurso específico
  ValidacionDiaService::obtenerEstado()             // Info del estado actual
  ```

### 5. **Traits**:
- `BloqueoPorEstadoDia` - Validación a nivel de Resource
- `ValidarDiaAperturado` - Validación a nivel de aplicación

### 6. **Middleware**: `app/Http/Middleware/ValidarDiaAperturado.php`
- Valida solicitudes HTTP POST/PUT/DELETE
- Solo lectura (GET) siempre permitida
- Excepciones automáticas para usuarios y apertura/cierre

---

## Cómo Usar

### **Paso 1: Ejecutar Migración**
```bash
php artisan migrate
```

### **Paso 2: Acceder a Apertura/Cierre**
1. Inicia sesión con usuario **Administrador**
2. Navega a: **Administración** → **Apertura/Cierre Día**
3. Haz clic en **Crear**

### **Paso 3: Abrir el Día**
- Selecciona **Fecha** del día actual
- Selecciona **Estado**: ABIERTO
- El sistema automáticamente:
  - Asigna tu usuario como "Usuario Apertura"
  - Registra la hora de apertura
- Guarda

### **Paso 4: Permitir Operaciones**
Una vez abierto el día, **TODOS** los usuarios podrán:
- Crear clientes, créditos, pagos, etc.
- Editar registros
- Eliminar registros (con confirmación)

**EXCEPTO** pueden hacer esto incluso con día cerrado:
- Crear usuarios (admin)
- Gestionar apertura/cierre (admin)

### **Paso 5: Cerrar el Día**
1. Navega a: **Administración** → **Apertura/Cierre Día**
2. Localiza el registro de hoy
3. Haz clic en **Editar**
4. Cambia **Estado** a: CERRADO
5. El sistema automáticamente:
   - Asigna tu usuario como "Usuario Cierre"
   - Registra la hora de cierre
6. Guarda

### **Paso 6: Bloqueo Automático**
Una vez cerrado el día:
- Todos los intentos de crear/editar/eliminar mostrarán:
  ```
  ❌ Día Cerrado
  "El día de operaciones está cerrado. No se pueden realizar operaciones."
  ```

---

## Aplicar Validación a Todos los Resources (Importante!)

Para que la validación funcione en **TODOS** los Resources, necesitas ejecutar:

### **Opción 1: Automática (Recomendada)**
```bash
php artisan tinker
> include(base_path('scripts/aplicar-validacion-dia-todos-resources.php'));
```

### **Opción 2: Manual**
Agregar esto a cada Resource al final (antes del cierre de clase):

```php
    public static function canCreate(): bool
    {
        \App\Services\ValidacionDiaService::validarParaOperacion(self::class);
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        \App\Services\ValidacionDiaService::validarParaOperacion(self::class);
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        \App\Services\ValidacionDiaService::validarParaOperacion(self::class);
        return true;
    }
```

**Ya aplicado manualmente a:**
- ClienteResource ✅
- PagoResource ✅
- CreditoResource ✅
- CrearProposicionCreditoResource ✅
- GenerarCreditoResource ✅
- CreditosRefinanciadosResource ✅

---

## Ejemplos de Validación

### **Cuando intenta crear un Pago (día cerrado)**
```
Notificación: ❌ Día Cerrado
"El día de operaciones está cerrado. No se pueden realizar operaciones."
[Retrocede a página anterior]
```

### **Cuando intenta editar un Crédito (día cerrado)**
Mismo bloqueo que creación.

### **Cuando intenta eliminar un Cliente (día cerrado)**
Mismo bloqueo que creación.

### **Cuando intenta crear usuario (día cerrado)**
✅ PERMITIDO - Administrador puede crear usuarios en cualquier momento.

### **Cuando intenta gestionar apertura/cierre (día cerrado)**
✅ PERMITIDO - Solo admin, en cualquier momento.

---

## Estados del Sistema

### **ABIERTO** ✅
- ```
  Estado: ABIERTO
  Operaciones permitidas: CREAR, EDITAR, ELIMINAR
  Icono: 🟢 Verde
  ```

### **CERRADO** 🔒
- ```
  Estado: CERRADO
  Operaciones permitidas: Ver datos, Crear usuarios (admin)
  Icono: 🔴 Rojo
  ```

---

## Excepciones

### **Modelos que SIEMPRE permiten operaciones:**
1. **User** - Creación de usuarios (solo admin)
2. **AperturaCierreDia** - Gestión de apertura/cierre (solo admin)

### **Operaciones que NUNCA se bloquean:**
- Lectura (GET) - Siempre permitida
- Listados - Siempre permitidos
- Vistas de detalle - Siempre permitidas

---

## Troubleshooting

### **Pregunta**: "¿Cómo creo un usuario si el día está cerrado?"
**Respuesta**: Los administradores pueden crear usuarios en cualquier momento, incluso con día cerrado.

### **Pregunta**: "¿Qué pasa si me cierro por error?"
**Respuesta**: Accede como admin → Apertura/Cierre Día → Edita el registro → Cambia a ABIERTO.

### **Pregunta**: "¿Puedo ver datos con día cerrado?"
**Respuesta**: Sí, solo la lectura es siempre permitida.

### **Pregunta**: "¿Se puede abrir/cerrar varias veces al día?"
**Respuesta**: No, el sistema usa `unique` en Fecha, por lo que solo un registro por día.

---

## Información de Auditoria

El sistema registra automáticamente:
- ✅ Quién abrió el día
- ✅ Cuándo se abrió
- ✅ Quién cerró el día
- ✅ Cuándo se cerró
- ✅ Observaciones adicionales

Estos datos están disponibles en el Resource de Apertura/Cierre Día para reportes.

---

## Próximos Pasos

1. ✅ Ejecutar migración
2. ✅ Crear registro de apertura para hoy
3. ✅ Probar bloqueos
4. ✅ Aplicar validación a TODOS los resources
5. ✅ Entrenar a administrador sobre uso

---

## Código de Referencia Rápido

```php
// Verificar estado
if (\App\Models\AperturaCierreDia::estaAbierto()) {
    // Día abierto
} else {
    // Día cerrado
}

// Obtener información
$estado = \App\Services\ValidacionDiaService::obtenerEstado();
echo $estado['mensaje']; // ✅ o ❌

// Validar en controlador
\App\Services\ValidacionDiaService::validarParaOperacion();
```

---

**Última actualización**: 19 de Enero de 2026
**Estado**: ✅ Producción
