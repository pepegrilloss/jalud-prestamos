<?php
/**
 * Script para agregar el filtro de Sede a todos los Filament Resources
 * 
 * Ejecutar: php add_sede_filter_to_resources.php
 * 
 * Este script agrega:
 * 1. `use App\Models\Sede;` import
 * 2. Un SelectFilter de Sede en el método table() (visible solo para super_admin)
 */

$resourceDir = __DIR__ . '/app/Filament/Resources';

// Resources que ya tienen el filtro o no lo necesitan
$skipResources = [
    'UserResource.php',      // Ya actualizado manualmente
    'SedeResource.php',      // Es el resource de Sede mismo
];

$resourceFiles = glob($resourceDir . '/*.php');
$modified = 0;
$skipped = 0;
$errors = [];

foreach ($resourceFiles as $file) {
    $basename = basename($file);

    if (in_array($basename, $skipResources)) {
        echo "SKIP: $basename (en lista de exclusión)\n";
        $skipped++;
        continue;
    }

    $content = file_get_contents($file);

    // Verificar si ya tiene el filtro de Sede
    if (strpos($content, "SedeID") !== false) {
        echo "SKIP: $basename (ya tiene filtro de Sede)\n";
        $skipped++;
        continue;
    }

    // Verificar si tiene método table()
    if (strpos($content, 'public static function table(') === false) {
        echo "SKIP: $basename (no tiene método table())\n";
        $skipped++;
        continue;
    }

    $originalContent = $content;

    // 1. Agregar import de Sede si no existe
    if (strpos($content, 'use App\\Models\\Sede;') === false) {
        // Buscar el último use statement antes de la clase
        if (preg_match('/^(use [^;]+;\s*\n)(?=\s*class\b)/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1] + strlen($matches[0][0]);
            $content = substr($content, 0, $insertPos) . "use App\\Models\\Sede;\n" . substr($content, $insertPos);
        } else {
            // Fallback: agregar después del último use statement
            $lastUsePos = strrpos($content, "use ");
            if ($lastUsePos !== false) {
                $endOfLinePos = strpos($content, "\n", $lastUsePos);
                $content = substr($content, 0, $endOfLinePos + 1) . "use App\\Models\\Sede;\n" . substr($content, $endOfLinePos + 1);
            }
        }
    }

    // 2. Agregar filtro de Sede en el método filters()
    $sedeFilter = "                Tables\\Filters\\SelectFilter::make('SedeID')\n" .
        "                    ->label('Sede')\n" .
        "                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))\n" .
        "                    ->visible(fn () => auth()->user()->hasRole('super_admin')),\n";

    // Buscar ->filters([ y agregar el filtro
    if (preg_match('/->filters\(\[\s*\n/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $insertPos = $matches[0][1] + strlen($matches[0][0]);
        $content = substr($content, 0, $insertPos) . $sedeFilter . substr($content, $insertPos);
    } elseif (strpos($content, '->filters([') !== false) {
        // Formato en una sola línea: ->filters([
        $content = str_replace('->filters([', "->filters([\n$sedeFilter", $content);
    } else {
        // No tiene ->filters(), buscamos antes de ->actions() para agregar
        if (preg_match('/(\s*)->actions\(\[/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $insertPos = $matches[0][1];
            $indent = $matches[1][0];
            $filtersBlock = "$indent->filters([\n$sedeFilter$indent])\n";
            $content = substr($content, 0, $insertPos) . $filtersBlock . substr($content, $insertPos);
        } else {
            $errors[] = "$basename: No se encontró ->filters() ni ->actions()";
            continue;
        }
    }

    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "OK: $basename (filtro de sede agregado)\n";
        $modified++;
    } else {
        echo "SKIP: $basename (sin cambios)\n";
        $skipped++;
    }
}

echo "\n===========================\n";
echo "Resultados:\n";
echo "  Modificados: $modified\n";
echo "  Omitidos: $skipped\n";
echo "  Errores: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrores:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
