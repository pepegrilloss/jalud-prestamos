<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permisosRos = [
            'view_ros::caso',
            'view_any_ros::caso',
            'create_ros::caso',
            'update_ros::caso',
            'delete_ros::caso',
            'delete_any_ros::caso',
            'force_delete_ros::caso',
            'force_delete_any_ros::caso',
            'restore_ros::caso',
            'restore_any_ros::caso',
            'replicate_ros::caso',
            'reorder_ros::caso',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permisosRos)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            return;
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
