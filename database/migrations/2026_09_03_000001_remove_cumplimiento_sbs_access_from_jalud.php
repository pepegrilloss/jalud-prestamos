<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ROLE = 'oficial_cumplimiento_sbs';

    private const PERMISSIONS = [
        'acceder_cumplimiento_sbs',
        'ver_todos_los_casos_sbs',
        'gestionar_catalogos_sbs',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where('name', self::ROLE)
            ->where('guard_name', 'web')
            ->pluck('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->pluck('id');

        if (Schema::hasTable('model_has_permissions') && $permissionIds->isNotEmpty()) {
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        if (Schema::hasTable('role_has_permissions') && $permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        if (Schema::hasTable('model_has_roles') && $roleIds->isNotEmpty()) {
            DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        }

        if (Schema::hasTable('role_has_permissions') && $roleIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('roles')->whereIn('id', $roleIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['name' => self::ROLE, 'guard_name' => 'web'],
            ['updated_at' => $now, 'created_at' => $now],
        );

        $roleId = DB::table('roles')->where('name', self::ROLE)->where('guard_name', 'web')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->pluck('id');

        if ($roleId && Schema::hasTable('role_has_permissions')) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
