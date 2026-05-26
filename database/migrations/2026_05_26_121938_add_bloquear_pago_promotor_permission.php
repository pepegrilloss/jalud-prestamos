<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert(
                ['name' => 'bloquear_pago_promotor', 'guard_name' => 'web'],
                ['name' => 'bloquear_pago_promotor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            \Illuminate\Support\Facades\DB::table('permissions')
                ->where('name', 'bloquear_pago_promotor')
                ->where('guard_name', 'web')
                ->delete();
        }
    }
};
