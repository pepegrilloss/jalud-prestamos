<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class CleanupCustomPermissions extends Command
{
    protected $signature = 'permissions:cleanup {--list : Solo listar todos los permisos sin eliminar}';
    protected $description = 'Eliminar permisos personalizados innecesarios';

    public function handle()
    {
        if ($this->option('list')) {
            $this->info("=== TODOS los permisos en la BD ===");
            $all = Permission::orderBy('name')->pluck('name')->toArray();
            foreach ($all as $i => $p) {
                $this->line(($i + 1) . ". {$p}");
            }
            $this->newLine();
            $this->info("Total: " . count($all));
            return;
        }

        // Permisos a CONSERVAR (buscar por coincidencia parcial)
        $conservar = ['abrir_dia_apertura', 'cerrar_dia_apertura', 'ver_todas_las_sedes',
                       'Abrir Dia Apertura', 'Cerrar Dia Apertura', 'Ver Todas Las Sedes'];

        // Obtener TODOS los permisos que NO son de recursos estándar de Shield
        // Los permisos estándar de Shield siguen el patrón: accion_recurso (view_any_user, create_role, etc.)
        $standardPrefixes = ['view_any_', 'view_', 'create_', 'update_', 'delete_any_', 'delete_', 
                             'export_', 'force_delete_', 'force_delete_any_', 'replicate_', 'reorder_', 'restore_', 'restore_any_',
                             'page_', 'widget_'];

        $allPermissions = Permission::all();
        
        $this->info("=== Permisos que serían ELIMINADOS ===");
        $toDelete = [];
        foreach ($allPermissions as $perm) {
            $name = $perm->name;
            // Verificar si es uno de los que queremos conservar
            $isConservar = false;
            foreach ($conservar as $c) {
                if (strcasecmp($name, $c) === 0 || str_contains(strtolower($name), strtolower(str_replace(' ', '_', $c)))) {
                    $isConservar = true;
                    break;
                }
            }

            // Verificar si es un permiso estándar de Shield (recursos normales)
            $isStandard = false;
            foreach ($standardPrefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $isStandard = true;
                    break;
                }
            }

            // Si NO es estándar y NO es de los que conservamos, marcarlo para eliminar
            if (!$isStandard && !$isConservar) {
                $toDelete[] = $perm;
                $this->warn("  🗑️  {$name}");
            }
        }

        if (empty($toDelete)) {
            $this->info("No hay permisos para eliminar.");
        } else {
            $this->newLine();
            foreach ($toDelete as $perm) {
                $perm->roles()->detach();
                $perm->delete();
                $this->info("✅ Eliminado: {$perm->name}");
            }
        }

        $this->newLine();
        $this->info("Total eliminados: " . count($toDelete));

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info("✅ Cache de permisos limpiado.");
    }
}
