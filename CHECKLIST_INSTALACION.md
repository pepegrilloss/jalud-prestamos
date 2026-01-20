# ✅ CHECKLIST DE INSTALACIÓN - Sistema de Apertura/Cierre de Día

## 📋 LISTA DE VERIFICACIÓN PRE-INSTALACIÓN

Antes de comenzar, asegúrate de tener:

- [ ] Acceso a terminal/consola del proyecto
- [ ] Acceso a base de datos MySQL/PostgreSQL
- [ ] Permisos para ejecutar comandos artisan
- [ ] Backup reciente de base de datos
- [ ] Acceso como Administrador a Filament

---

## 🚀 PASOS DE INSTALACIÓN

### **Paso 1: Ejecutar Migración**
- [ ] Abre terminal en: `c:\xampp\htdocs\jalud-prestamos`
- [ ] Ejecuta: `php artisan migrate`
- [ ] Verifica: Tabla `apertura_cierre_dia` creada
- [ ] Resultado: Debe decir "Migrated: 2026_01_19_000001_create_apertura_cierre_dia_table"

### **Paso 2: Aplicar Validación a Todos los Resources**
- [ ] Abre: `php artisan tinker`
- [ ] Copia y pega:
  ```php
  include(base_path('scripts/aplicar-validacion-dia-todos-resources.php'));
  ```
- [ ] Espera a que termine
- [ ] Verifica: Debe mostrar archivos actualizados
- [ ] Ejecuta: `exit` para salir

### **Paso 3: Verificar Estado**
- [ ] Ejecuta: `php artisan dia:estado`
- [ ] Verifica: Mensaje "Sin registro para hoy" o mostrando estado
- [ ] Resultado: ✅ Sistema respondiendo correctamente

### **Paso 4: Acceder a Filament**
- [ ] Abre: `http://localhost/jalud-prestamos` (o tu URL)
- [ ] Inicia sesión como **Administrador**
- [ ] Navega a: **Administración** (menú izquierdo)
- [ ] Busca: **Apertura/Cierre Día**
- [ ] Verifica: Link clickeable

---

## 🧪 PRUEBAS FUNCIONALES

### **Prueba 1: Crear Apertura**
- [ ] Haz clic en **Apertura/Cierre Día**
- [ ] Haz clic en **Crear**
- [ ] Selecciona **Fecha**: Hoy (debe estar única)
- [ ] Selecciona **Estado**: ABIERTO
- [ ] Haz clic en **Guardar**
- [ ] Resultado: ✅ Registro creado

### **Prueba 2: Verificar Estado**
- [ ] Terminal: `php artisan dia:estado`
- [ ] Verifica: Dice "✅ DÍA ABIERTO"
- [ ] Verifica: Muestra tu usuario como apertura

### **Prueba 3: Crear Registro (Día Abierto)**
- [ ] Ve a: **Clientes** → **Crear**
- [ ] Llena los datos básicos
- [ ] Haz clic en **Guardar**
- [ ] Resultado: ✅ Cliente creado exitosamente

### **Prueba 4: Cerrar el Día**
- [ ] Navega a: **Administración** → **Apertura/Cierre Día**
- [ ] Haz clic en **Editar** (el registro de hoy)
- [ ] Cambia **Estado** a: CERRADO
- [ ] Haz clic en **Guardar**
- [ ] Resultado: ✅ Día cerrado

### **Prueba 5: Verificar Bloqueo**
- [ ] Terminal: `php artisan dia:estado`
- [ ] Verifica: Dice "❌ DÍA CERRADO"

### **Prueba 6: Intentar Crear Registro (Día Cerrado)**
- [ ] Ve a: **Clientes** → **Crear**
- [ ] Llena los datos básicos
- [ ] Haz clic en **Guardar**
- [ ] Resultado: ❌ Notificación "Día Cerrado"
- [ ] Verifica: Se retrocede automáticamente

### **Prueba 7: Crear Usuario (Día Cerrado)**
- [ ] Ve a: **Usuarios** → **Crear** (si tienes permisos)
- [ ] Llena datos de usuario
- [ ] Haz clic en **Guardar**
- [ ] Resultado: ✅ Usuario creado (excepción permitida)

### **Prueba 8: Tests Automáticos**
- [ ] Terminal: `php artisan test --filter=AperturaCierreDiaTest`
- [ ] Espera a que termine
- [ ] Resultado: Debe mostrar "12 passed"

---

## 📊 VALIDACIÓN DE DATOS

Después de instalación, verifica en base de datos:

### **Query 1: Verificar Tabla Existe**
```sql
DESCRIBE apertura_cierre_dia;
```
Debe mostrar 10 columnas.

### **Query 2: Ver Registros**
```sql
SELECT * FROM apertura_cierre_dia;
```

### **Query 3: Verificar Relaciones**
```sql
SELECT 
    acd.*,
    u1.name as usuario_apertura,
    u2.name as usuario_cierre
FROM apertura_cierre_dia acd
LEFT JOIN users u1 ON acd.UsuarioAperturaID = u1.id
LEFT JOIN users u2 ON acd.UsuarioCierreID = u2.id;
```

---

## 🔧 TROUBLESHOOTING

| Problema | Solución |
|----------|----------|
| "Clase no encontrada" | `composer dump-autoload` |
| Migración falla | Elimina tabla si existe, vuelve a migrar |
| Resource no aparece | Limpia cache: `php artisan cache:clear` |
| Middleware no funciona | Verifica bootstrap/app.php esté modificado |
| Tests fallan | `php artisan migrate:fresh --seed` |

---

## 📝 PUNTOS IMPORTANTES

✅ **Backup**: Haz un backup ANTES de instalar
✅ **Permisos**: Asegúrate de ser admin en Filament
✅ **Único**: Solo un registro por día (unique constraint)
✅ **Auditoría**: El sistema registra todo automáticamente
✅ **Excepciones**: Usuarios siempre pueden crearse
✅ **Lectura**: Las lecturas nunca se bloquean

---

## 🎯 VERIFICACIÓN FINAL

Marca cuando todo esté listo:

- [ ] Migración ejecutada
- [ ] Scripts aplicados
- [ ] Comando dia:estado funciona
- [ ] Resource visible en Filament
- [ ] Prueba 1-8 completadas
- [ ] Tests pasan
- [ ] Base de datos verificada
- [ ] Documentación leída

---

## ✅ INSTALACIÓN COMPLETA

Una vez todos los puntos estén marcados:

```
✅ Sistema listo para producción
✅ Todos los tests pasan
✅ Documentación disponible
✅ Admin entrenado
```

---

## 📞 SOPORTE RÁPIDO

### **¿Cómo abrir el día?**
1. Admin → Administración → Apertura/Cierre Día → Crear → ABIERTO

### **¿Cómo cerrar el día?**
1. Admin → Administración → Apertura/Cierre Día → Editar → CERRADO

### **¿Cómo ver el estado?**
1. Terminal: `php artisan dia:estado`

### **¿Qué pasa si cierro por error?**
1. Abre nuevamente (admin solo): Edita → ABIERTO

### **¿Quién puede crear usuarios con día cerrado?**
1. Solo Administrador puede

### **¿Todos los datos se bloquean?**
1. No, solo crear/editar/eliminar. Lectura siempre funciona.

---

## 📚 DOCUMENTACIÓN COMPLEMENTARIA

Para más información, consulta:
- `README_APERTURA_CIERRE.md` - Inicio rápido
- `INSTALACION_RAPIDA.md` - Pasos detallados
- `APERTURA_CIERRE_DIA_README.md` - Documentación completa
- `RESUMEN_IMPLEMENTACION.md` - Arquitectura técnica

---

**Checklist creado**: 19 de Enero de 2026  
**Versión**: 1.0  
**Estado**: ✅ LISTO PARA USAR

---

## 📋 NOTAS PERSONALES

Usa este espacio para anotar:
```
_________________________________________________________________

_________________________________________________________________

_________________________________________________________________

_________________________________________________________________

_________________________________________________________________
```

---

**¡ÉXITO CON LA INSTALACIÓN!** 🚀
