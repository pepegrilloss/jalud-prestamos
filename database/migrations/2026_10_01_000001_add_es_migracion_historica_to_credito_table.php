<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Credito', 'EsMigracionHistorica')) {
            Schema::table('Credito', function (Blueprint $table) {
                $table->boolean('EsMigracionHistorica')
                    ->default(false)
                    ->comment('Credito historico pagado fuera de JALUD, sin pagos ni desembolso en caja');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('Credito', 'EsMigracionHistorica')) {
            return;
        }

        if (DB::table('Credito')->where('EsMigracionHistorica', true)->exists()) {
            throw new RuntimeException('No se puede quitar EsMigracionHistorica mientras existan creditos historicos.');
        }

        Schema::table('Credito', function (Blueprint $table) {
            $table->dropColumn('EsMigracionHistorica');
        });
    }
};
