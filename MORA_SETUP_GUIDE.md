# Sistema de Mora Automática - Guía de Implementación

## 📋 Descripción

Sistema que calcula automáticamente la mora diaria para créditos vencidos, basándose en:
- **Fecha de vencimiento** del crédito
- **Saldo pendiente** actual
- **Porcentaje de mora** registrado en el cliente

### Ejemplo

```
Cliente: Juan Pérez
Tasa de Mora: 0.5% diario
Crédito vence: 14/02/2026
Saldo pendiente: 100 soles

14/02/2026 → Mora = 100 × 0.005 = 0.50 soles (Acumulada: 0.50)
15/02/2026 → Mora = 100 × 0.005 = 0.50 soles (Acumulada: 1.00)
16/02/2026 → Paga 50, Mora = 50 × 0.005 = 0.25 soles (Acumulada: 1.25)
17/02/2026 → Mora = 50 × 0.005 = 0.25 soles (Acumulada: 1.50)
```

---

## 🚀 Archivos Creados

### 1. **Database Migration**
```
database/migrations/2026_02_18_create_moras_table.php
```
- Crea tabla `mora`
- Columnas: CreditoID, FechaMora, SaldoPendiente, PorcentajeMora, MontoMora, MoraAcumulada
- Índices y restricción UNIQUE para evitar duplicados por día

### 2. **Model**
```
app/Models/Mora.php
```
- Relación `belongsTo` con Credito
- Método `getMoraActual()` para obtener la última mora registrada

### 3. **Job (Trabajo Programado)**
```
app/Jobs/CalcularMoraAutomatica.php
```
- Lógica principal de cálculo
- Validaciones:
  - Crédito vencido (FechaVencimiento ≤ hoy)
  - Saldo pendiente > 0
  - Cliente tiene tasa de mora configurada
  - No crear duplicado para el mismo día

### 4. **Scheduler (Configuración de Tareas)**
```
app/Console/Kernel.php
```
- Ejecuta el Job **diariamente a las 00:01 AM**
- Usa `onOneServer()` para evitar duplicados en múltiples servidores

### 5. **Artisan Command (Ejecución Manual)**
```
app/Console/Commands/CalcularMoraCommand.php
```
- Comando: `php artisan mora:calcular`
- Útil para testing y ejecuciones manuales

### 6. **Filament Resource (Panel Visual)**
```
app/Filament/Resources/MoraResource.php
app/Filament/Resources/MoraResource/Pages/ListMoras.php
```
- Panel de visualización en "Finanzas"
- Tabla de solo lectura
- Filtros por Crédito y períodoPara editar la estructura del Resource o agregar filtros adicionales

### 7. **SQL Documentation**
```
database/sql/setup_mora_automatica.sql
```
- SQL de la tabla
- Ejemplos de consultas
- Explicación del cálculo

---

## ⚙️ Instalación

### Paso 1: Ejecutar las Migraciones

```bash
php artisan migrate
```

Esto crea la tabla `mora` automáticamente.

### Paso 2: Configurar el Scheduler en tu Servidor

El Laravel Scheduler necesita ejecutarse cada minuto para que dispare los Jobs programados.

**En Linux/Mac (crontab):**

```bash
crontab -e
```

Agregar esta línea:

```
* * * * * cd /ruta/a/jalud-prestamos && php artisan schedule:run >> /dev/null 2>&1
```

**En Windows (Task Scheduler):**

1. Abrir "Programador de tareas"
2. Crear tarea que ejecute cada minuto:
   ```
   php artisan schedule:run
   ```
   Ubicación: `C:\xampp\htdocs\jalud-prestamos`

### Paso 3: Verificar que Cliente tiene TasaMora

**IMPORTANTE:** Cada cliente debe tener una **Tasa de Mora** asignada (campo obligatorio en ClienteResource).

Si usas clientes antiguos sin mora, asignarles una por defecto:

```sql
UPDATE cliente SET TasaMoraID = (SELECT TasaMoraID FROM tasaMora LIMIT 1) WHERE TasaMoraID IS NULL;
```

---

## 🧪 Testing

### Ejecutar Manualmente

```bash
php artisan mora:calcular
```

### Verificar Registros

```bash
SELECT * FROM mora ORDER BY created_at DESC LIMIT 10;
```

### Ver en Filament Panel

1. Inicia sesión
2. Ve a **Finanzas → Mora**
3. Visualiza los cálculos generados

---

## 📊 Consultas Útiles

### Mora acumulada por cliente hoy

```sql
SELECT 
    c.DNI,
    c.NombresApellidos,
    cr.CreditoID,
    m.MoraAcumulada,
    m.SaldoPendiente
FROM mora m
JOIN credito cr ON m.CreditoID = cr.CreditoID
JOIN proposicion_credito pc ON cr.ProposicionCreditoID = pc.ProposicionCreditoID
JOIN cliente c ON pc.ClienteID = c.ClienteID
WHERE m.FechaMora = CURDATE()
ORDER BY m.MoraAcumulada DESC;
```

### Total de mora por crédito

```sql
SELECT 
    CreditoID,
    MAX(FechaMora) as UltimaFecha,
    MAX(MoraAcumulada) as MoraTotal,
    COUNT(*) as DiasEnMora
FROM mora
GROUP BY CreditoID
ORDER BY MoraTotal DESC;
```

### Créditos sin mora (pagos al día)

```sql
SELECT cr.CreditoID, cr.FechaVencimiento
FROM credito cr
WHERE cr.Activo = 1
AND cr.FechaVencimiento <= CURDATE()
AND cr.CreditoID NOT IN (SELECT DISTINCT CreditoID FROM mora);
```

---

## 🔧 Configuración Avanzada

### Cambiar hora de ejecución

En `app/Console/Kernel.php`:

```php
// Cambiar a las 06:00 PM (18:00)
$schedule->job(new CalcularMoraAutomatica())
    ->dailyAt('18:00')
    ->name('calcular-mora-automatica')
    ->onOneServer();
```

### Ejecutar cada hora (mayor precisión)

```php
$schedule->job(new CalcularMoraAutomatica())
    ->hourly()
    ->name('calcular-mora-automatica-hourly');
```

### Ver logs de ejecución

```bash
tail -f storage/logs/laravel.log | grep "Mora calculada"
```

---

## 📁 Estructura de Tablas

### Tabla MORA

```sql
CREATE TABLE mora (
    MoraID INT AUTO_INCREMENT PRIMARY KEY,
    CreditoID INT NOT NULL (FK → Credito),
    FechaMora DATE NOT NULL,
    SaldoPendiente DECIMAL(12,2),           -- Saldo en el que se calculó
    PorcentajeMora DECIMAL(5,2),            -- % aplicado del cliente
    MontoMora DECIMAL(12,2),                -- Mora del día
    MoraAcumulada DECIMAL(12,2),            -- Suma total hasta esa fecha
    created_at TIMESTAMP,
    UNIQUE(CreditoID, FechaMora)            -- Evita duplicado por día
);
```

### Relaciones

```
Credito
  ├─ 1:M Mora
  ├─ 1:M Cuota
  └─ 1:N ProposicionCredito
       └─ 1:N Cliente
           └─ 1:N TasaMora
```

---

##❌ Troubleshooting

### "No se calcula nada"

1. ✅ Verificar que el scheduler está corriendo:
   ```bash
   php artisan schedule:list
   ```

2. ✅ Verificar que el cliente tiene TasaMora asignada:
   ```sql
   SELECT ClienteID, TasaMoraID FROM cliente WHERE TasaMoraID IS NULL;
   ```

3. ✅ Verificar logs:
   ```bash
   tail storage/logs/laravel.log
   ```

### "Valores duplicados para el mismo día"

La tabla tiene restricción UNIQUE en `(CreditoID, FechaMora)`, así que no debería ocurrir. Si ocurre, ejecutar:

```sql
DELETE FROM mora WHERE MoraID NOT IN (
    SELECT MIN(MoraID) FROM mora GROUP BY CreditoID, FechaMora
);
```

### "Las tasas de mora no se ven correctas"

Verificar que:
1. Cliente tiene TasaMora vigente (Activo = 1)
2. El porcentaje está en formato decimal (0.5 = 0.5%, no 50%)
3. El crédito realmente está vencido (FechaVencimiento ≤ hoy)

---

## 📞 Soporte

Si necesitas ajustes adicionales:
- Modificar el porcentaje cap máximo de mora
- Excluir ciertos clientes del cálculo
- Enviar notificaciones por mora pendiente
- Integrar con sistema de recaudación

Los archivos están organizados para facilitar extensiones.

---

**Última actualización:** 18/02/2026
**Version:** 1.0
