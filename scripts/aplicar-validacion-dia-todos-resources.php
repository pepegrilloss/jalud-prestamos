<?php
/**
 * Script para aplicar la validación de día aperturado a TODOS los Resources
 * 
 * Uso: Ejecutar este archivo desde la consola de Laravel
 * php artisan tinker
 * > include(base_path('scripts/aplicar-validacion-dia-todos-resources.php'));
 */

$resourcesDir = base_path('app/Filament/Resources');
$excludidos = ['AperturaCierreDiaResource.php', 'UserResource.php'];

$files = array_filter(
    glob($resourcesDir . '/*.php'),
    fn($file) => !in_array(basename($file), $excludidos) && is_file($file)
);

$validacionMethods = <<<'PHP'

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
PHP;

$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Saltar si ya tiene los métodos
    if (strpos($content, 'canCreate(): bool') !== false) {
        echo "✓ " . basename($file) . " - ya tiene validación\n";
        continue;
    }
    
    // Reemplazar el último } por los métodos + }
    $newContent = preg_replace('/\n}$/', "\n" . $validacionMethods . "\n}\n", $content);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "✅ " . basename($file) . " - actualizado\n";
        $count++;
    }
}

echo "\n✅ Total de archivos actualizados: $count\n";
echo "Ahora ejecuta: php artisan migrate\n";
