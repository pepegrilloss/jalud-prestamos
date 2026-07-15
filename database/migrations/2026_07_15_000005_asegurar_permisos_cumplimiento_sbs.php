<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        $now = now();

        $permisos = [
            'acceder_cumplimiento_sbs',
            'ver_todos_los_casos_sbs',
            'gestionar_catalogos_sbs',
        ];

        foreach ($permisos as $permiso) {
            $existe = DB::table('permissions')
                ->where('name', $permiso)
                ->where('guard_name', 'web')
                ->exists();

            if ($existe) {
                DB::table('permissions')
                    ->where('name', $permiso)
                    ->where('guard_name', 'web')
                    ->update(['updated_at' => $now]);

                continue;
            }

            DB::table('permissions')->insert([
                'name' => $permiso,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rolExiste = DB::table('roles')
            ->where('name', 'oficial_cumplimiento_sbs')
            ->where('guard_name', 'web')
            ->exists();

        if ($rolExiste) {
            DB::table('roles')
                ->where('name', 'oficial_cumplimiento_sbs')
                ->where('guard_name', 'web')
                ->update(['updated_at' => $now]);
        } else {
            DB::table('roles')->insert([
                'name' => 'oficial_cumplimiento_sbs',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!Schema::hasTable('role_has_permissions')) {
            return;
        }

        $rolId = DB::table('roles')
            ->where('name', 'oficial_cumplimiento_sbs')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$rolId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permisos)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $rolId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $rolId = DB::table('roles')
            ->where('name', 'oficial_cumplimiento_sbs')
            ->where('guard_name', 'web')
            ->value('id');

        if ($rolId && Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('role_id', $rolId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
