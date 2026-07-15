<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tesoreria_prestamo_cuotas', function (Blueprint $table) {
            $table->index(['Estado', 'FechaVencimiento'], 'idx_cuota_prestamo_alertas');
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria_prestamo_cuotas', function (Blueprint $table) {
            $table->dropIndex('idx_cuota_prestamo_alertas');
        });
    }
};
