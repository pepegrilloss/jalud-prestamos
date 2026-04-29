<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class CleanupCustomPermissions extends Command
{
    protected $signature = 'permissions:cleanup';
    protected $description = 'Eliminar permisos personalizados innecesarios';

    public function handle()
    {
        $eliminar = [
            'Create Crear::proposicion::credito',
            'Delete Crear::proposicion::credito',
            'Update Crear::proposicion::credito',
            'View Any Crear::proposicion::credito',
            'View Crear::proposicion::credito',
            'Widget Apertura Cierre Dia Widget',
            'Widget Cliente Proposicion Stats',
            'Widget Cobranza Stats',
            'Widget Exoneraciones Pendientes Widget',
            'Widget Exoneraciones Stats Widget',
            'Widget Pagos Stats',
            'Widget Proposiciones Stats',
        ];

        $deleted = 0;
        foreach ($eliminar as $permiso) {
            $p = Permission::where('name', $permiso)->first();
            if ($p) {
                $p->roles()->detach();
                $p->delete();
                $this->info("✅ Eliminado: {$permiso}");
                $deleted++;
            } else {
                $this->warn("⚠️  No encontrado: {$permiso}");
            }
        }

        $this->newLine();
        $this->info("Total eliminados: {$deleted}");

        // Verificar restantes
        $mantener = ['Abrir Dia Apertura', 'Cerrar Dia Apertura', 'Ver Todas Las Sedes'];
        $this->newLine();
        $this->info("=== Permisos Personalizados Restantes ===");
        foreach ($mantener as $permiso) {
            $exists = Permission::where('name', $permiso)->exists();
            $this->line(($exists ? '✅' : '❌') . " {$permiso}");
        }

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->newLine();
        $this->info("✅ Cache de permisos limpiado.");
    }
}
