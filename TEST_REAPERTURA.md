# Test de Reapertura de Día

## Cambio realizado:
Se agregó la llamada a `reabrirDia()` en el action 'abrirFecha' del recurso `AperturaCierreDiaResource.php`.

**Ubicación**: Lines 195-197
```php
// Limpiar FechaCierre de todos los registros del día
$record->reabrirDia();
```

## ¿Qué pasaba antes?
El botón "Abrir Fecha" solo actualizaba el estado de `AperturaCierreDia` a ABIERTO, pero NO limpiaba los `FechaCierre` de los registros asociados (Clientes, Pagos, Cuotas, Análisis, Evaluaciones).

## ¿Qué pasa ahora?
1. Actualiza el estado a ABIERTO en `AperturaCierreDia`
2. Llama a `reabrirDia()` que:
   - Limpia FechaCierre de todos los Clientes del día
   - Limpia FechaCierre de todas las Proposiciones del día
   - Limpia FechaCierre de todos los Créditos del día
   - Limpia FechaCierre de todos los **Pagos** del día (esto era lo que no funcionaba)
   - Limpia FechaCierre de todas las Cuotas del día
   - Limpia FechaCierre de todos los Análisis Económicos del día
   - Limpia FechaCierre de todas las Evaluaciones del día
3. Genera logging detallado en `storage/logs/reopening-debug.log`

## Cómo testear:
1. Abre un día cerrado clicando el botón "Abrir Fecha"
2. Verifica que se crea el archivo `storage/logs/reopening-debug.log`
3. Abre ese archivo y verifica que muestre los conteos de registros actualizados
4. Intenta editar/eliminar un pago de ese día - debería funcionar ahora

## Archivos modificados:
- `app/Filament/Resources/AperturaCierreDiaResource.php` (Line 195 agregada)
