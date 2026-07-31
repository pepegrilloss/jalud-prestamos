<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('permissions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('role_has_permissions')
        ) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'eficiencia_cobranza', 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $eficienciaPermissionId = DB::table('permissions')
            ->where('name', 'eficiencia_cobranza')
            ->where('guard_name', 'web')
            ->value('id');

        $balancePermissionId = DB::table('permissions')
            ->where('name', 'balance_diario')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $eficienciaPermissionId || ! $balancePermissionId) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $balancePermissionId)
            ->pluck('role_id')
            ->merge(
                DB::table('roles')
                    ->whereIn('name', ['super_admin', 'Administrador General'])
                    ->pluck('id')
            )
            ->unique()
            ->values();

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $eficienciaPermissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $eficienciaPermissionId = DB::table('permissions')
            ->where('name', 'eficiencia_cobranza')
            ->where('guard_name', 'web')
            ->value('id');

        if ($eficienciaPermissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $eficienciaPermissionId)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
