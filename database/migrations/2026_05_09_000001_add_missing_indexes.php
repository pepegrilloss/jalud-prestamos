<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuota', function (Blueprint $table) {
            $table->index('FechaVencimiento', 'IDX_Cuota_FechaVencimiento');
            $table->index('Estado', 'IDX_Cuota_Estado');
        });

        Schema::table('Cliente', function (Blueprint $table) {
            $table->index('DNI', 'IDX_Cliente_DNI');
        });
    }

    public function down(): void
    {
        Schema::table('cuota', function (Blueprint $table) {
            $table->dropIndex('IDX_Cuota_FechaVencimiento');
            $table->dropIndex('IDX_Cuota_Estado');
        });

        Schema::table('Cliente', function (Blueprint $table) {
            $table->dropIndex('IDX_Cliente_DNI');
        });
    }
};
