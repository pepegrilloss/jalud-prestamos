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

        // Permisos personalizados a ELIMINAR (nombres reales de la BD)
        $eliminar = [
            'create_crear::proposicion::credito',
            'delete_crear::proposicion::credito',
            'update_crear::proposicion::credito',
            'view_any_crear::proposicion::credito',
            'view_crear::proposicion::credito',
            'widget_AperturaCierreDiaWidget',
            'widget_ClienteProposicionStats',
            'widget_CobranzaStats',
            'widget_ExoneracionesPendientesWidget',
            'widget_ExoneracionesStatsWidget',
            'widget_PagosStats',
            'widget_ProposicionesStats',
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
        $mantener = ['abrir_dia_apertura', 'cerrar_dia_apertura', 'ver_todas_las_sedes'];
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
