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
        Schema::table('apertura_cierre_dia', function (Blueprint $table) {
            $table->boolean('pagos_promotor_bloqueados')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('apertura_cierre_dia', function (Blueprint $table) {
            $table->dropColumn('pagos_promotor_bloqueados');
        });
    }
};
