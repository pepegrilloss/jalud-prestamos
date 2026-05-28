<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_fondo', function (Blueprint $table) {
            $table->dateTime('FechaMovimiento')->nullable()->after('Observacion');
        });

        DB::statement('UPDATE movimientos_fondo SET FechaMovimiento = created_at WHERE FechaMovimiento IS NULL');
    }

    public function down(): void
    {
        Schema::table('movimientos_fondo', function (Blueprint $table) {
            $table->dropColumn('FechaMovimiento');
        });
    }
};
