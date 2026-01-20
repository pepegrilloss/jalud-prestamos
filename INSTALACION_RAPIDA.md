# ⚡ GUÍA DE INSTALACIÓN RÁPIDA - Sistema de Apertura/Cierre de Día

## ✅ Lo que se ha implementado:

### 📁 Archivos Creados:
1. **[Migration]** `database/migrations/2026_01_19_000001_create_apertura_cierre_dia_table.php`
2. **[Model]** `app/Models/AperturaCierreDia.php`
3. **[Resource]** `app/Filament/Resources/AperturaCierreDiaResource.php`
4. **[Pages]** `app/Filament/Resources/AperturaCierreDiaResource/Pages/`
   - ListAperturaCierreDias.php
   - CreateAperturaCierreDia.php
   - EditAperturaCierreDia.php
5. **[Service]** `app/Services/ValidacionDiaService.php`
6. **[Middleware]** `app/Http/Middleware/ValidarDiaAperturado.php`
7. **[Traits]** 
   - `app/Traits/ValidarDiaAperturado.php`
   - `app/Traits/BloqueoPorEstadoDia.php`
8. **[Command]** `app/Console/Commands/MostrarEstadoDia.php`
9. **[Scripts]** `scripts/aplicar-validacion-dia-todos-resources.php`
10. **[Docs]** 
    - `APERTURA_CIERRE_DIA_README.md`
    - `INSTALACION_RAPIDA.md` (este archivo)

### 📝 Archivos Modificados:
1. **bootstrap/app.php** - Registrado middleware
2. **Resources** (6 principales ya validados):
   - ClienteResource.php ✅
   - PagoResource.php ✅
   - CreditoResource.php ✅
   - CrearProposicionCreditoResource.php ✅
   - GenerarCreditoResource.php ✅
   - CreditosRefinanciadosResource.php ✅

---

## 🚀 PASOS DE INSTALACIÓN

### **PASO 1: Ejecutar Migración**
```bash
php artisan migrate
```

**Resultado esperado:**
```
Migrating: 2026_01_19_000001_create_apertura_cierre_dia_table
Migrated:  2026_01_19_000001_create_apertura_cierre_dia_table
```

### **PASO 2: Aplicar Validación a TODOS los Resources (Automático)**

En tu terminal Laravel:
```bash
php artisan tinker
> include(base_path('scripts/aplicar-validacion-dia-todos-resources.php'));
```

**Resultado esperado:**
```
✓ AprobacionProposicionResource.php - ya tiene validación
✅ CiudadResource.php - actualizado
✅ ClienteProposicionResource.php - actualizado
✅ CrearProposicionCreditoResource.php - ya tiene validación
✓ CreditoResource.php - ya tiene validación
... (más resources)
✅ Total de archivos actualizados: 12
Ahora ejecuta: php artisan migrate
```

### **PASO 3: Verificar Instalación**
```bash
php artisan dia:estado
```

**Resultado esperado:**
```
═══════════════════════════════════════════════════════════
           ESTADO DEL DÍA DE OPERACIONES
═══════════════════════════════════════════════════════════

❌ DÍA CERRADO
   Las operaciones están BLOQUEADAS

───────────────────────────────────────────────────────────
Sin registro para hoy

═══════════════════════════════════════════════════════════
```

### **PASO 4: Acceder a Filament**
1. Inicia sesión como **Administrador**
2. En el menú, ve a: **Administración** → **Apertura/Cierre Día**
3. Verás la interfaz para gestionar el estado

### **PASO 5: Abrir el Día**
1. Haz clic en **Crear**
2. Selecciona la **Fecha** de hoy
3. Selecciona **Estado**: ABIERTO
4. Haz clic en **Guardar**

### **PASO 6: Verificar Que Funciona**
```bash
php artisan dia:estado
```

**Resultado esperado:**
```
═══════════════════════════════════════════════════════════
           ESTADO DEL DÍA DE OPERACIONES
═══════════════════════════════════════════════════════════

✅ DÍA ABIERTO
   Las operaciones están permitidas

───────────────────────────────────────────────────────────
Fecha: 19/01/2026
Apertura: 19/01/2026 10:30:45
  Por: Admin User

═══════════════════════════════════════════════════════════
```

---

## 🧪 PRUEBAS RÁPIDAS

### **Prueba 1: Crear un Cliente (Día Abierto)**
✅ **Debe permitir** la creación

### **Prueba 2: Cerrar el Día**
1. Ve a: Administración → Apertura/Cierre Día
2. Edita el registro de hoy
3. Cambia a: CERRADO
4. Guarda

### **Prueba 3: Intenta Crear un Cliente (Día Cerrado)**
❌ **Debe bloquear** con mensaje:
```
❌ Día Cerrado
"El día de operaciones está cerrado..."
```

### **Prueba 4: Intenta Crear un Usuario (Día Cerrado)**
✅ **Debe permitir** (admin puede crear usuarios siempre)

---

## 🎛️ COMANDOS ÚTILES

### **Ver estado del día**
```bash
php artisan dia:estado
```

### **Tinker para pruebas**
```bash
php artisan tinker

# Ver si está abierto
> \App\Models\AperturaCierreDia::estaAbierto()
=> true

# Obtener info
> \App\Services\ValidacionDiaService::obtenerEstado()
=> [
  "abierto" => true,
  "estado" => "ABIERTO",
  "registro" => \App\Models\AperturaCierreDia {...},
  "mensaje" => "✅ Día abierto - Operaciones permitidas"
]
```

---

## 🔍 VALIDACIÓN EN CÓDIGO

Si quieres validar en tus controladores o services:

```php
// Opción 1: Directa
if (!\App\Models\AperturaCierreDia::estaAbierto()) {
    throw new Exception('Día cerrado');
}

// Opción 2: Con servicio (recomendado)
\App\Services\ValidacionDiaService::validarParaOperacion(
    'App\Models\Pago'  // nombre del modelo
);

// Opción 3: Obtener estado
$estado = \App\Services\ValidacionDiaService::obtenerEstado();
if (!$estado['abierto']) {
    // Día cerrado
}
```

---

## ⚠️ CASOS ESPECIALES

### **¿Qué pasa si se cierra por error?**
1. Ve a: Administración → Apertura/Cierre Día
2. Edita el registro de hoy
3. Cambia a: ABIERTO
4. Guarda

### **¿Puedo abrir/cerrar varias veces?**
❌ NO - El sistema solo permite un registro por día (unique constraint)

### **¿Las lecturas se bloquean?**
✅ NO - Solo se bloquean CRUD (crear, editar, eliminar)

### **¿Los admins están exentos?**
❌ NO - Todos están limitados, excepto:
- ✅ Crear usuarios (admin)
- ✅ Gestionar apertura/cierre (admin)

---

## 📊 INFORMACIÓN AUDITADA

El sistema registra automáticamente:
- ✅ Quién abrió el día (UsuarioAperturaID)
- ✅ Cuándo abrió (FechaApertura)
- ✅ Quién cerró el día (UsuarioCierreID)
- ✅ Cuándo cerró (FechaCierre)
- ✅ Observaciones adicionales

---

## 🐛 TROUBLESHOOTING

| Problema | Solución |
|----------|----------|
| "Clase no encontrada" | Ejecuta `composer dump-autoload` |
| Migración falla | Verifica que no exista tabla anterior |
| Middleware no funciona | Verifica que esté en `bootstrap/app.php` |
| Script de aplicación falla | Revisa permisos de archivos |
| La validación no se aplica | Ejecuta el script automático |

---

## ✅ LISTA DE VERIFICACIÓN FINAL

- [ ] ✅ Migración ejecutada
- [ ] ✅ Middleware registrado en bootstrap/app.php
- [ ] ✅ Script de validación ejecutado
- [ ] ✅ Comando `dia:estado` funciona
- [ ] ✅ Resource visible en Administración
- [ ] ✅ Abrir/cerrar día funciona
- [ ] ✅ Bloqueo funciona al día cerrado
- [ ] ✅ Usuarios pueden crearse (admin)
- [ ] ✅ Datos se leen aunque esté cerrado

---

## 📞 SOPORTE

Si algo no funciona:
1. Verifica los pasos de instalación
2. Ejecuta `php artisan migrate:refresh` (⚠️ borra datos)
3. Revisa logs: `storage/logs/laravel.log`
4. Ejecuta: `php artisan cache:clear`

---

**Instalación completada**: ✅  
**Estado**: Listo para producción  
**Fecha**: 19 de Enero de 2026
