<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('permission.table_names.permissions', 'permissions');

        if (!Schema::hasTable($tableName)) {
            return;
        }

        DB::table($tableName)->insertOrIgnore([
            ['name' => 'reporte_creditos', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission.table_names.permissions', 'permissions');

        if (!Schema::hasTable($tableName)) {
            return;
        }

        DB::table($tableName)
            ->where('name', 'reporte_creditos')
            ->where('guard_name', 'web')
            ->delete();
    }
};
