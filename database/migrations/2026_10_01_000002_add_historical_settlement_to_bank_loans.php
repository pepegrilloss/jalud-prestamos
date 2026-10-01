<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table): void {
            $table->boolean('EsSaldadoHistorico')->default(false)->after('Estado');
            $table->date('FechaSaldamientoHistorico')->nullable()->after('EsSaldadoHistorico');
        });
    }

    public function down(): void
    {
        if (DB::table('tesoreria_prestamos_bancarios')->where('EsSaldadoHistorico', true)->exists()) {
            throw new RuntimeException('No se puede quitar el historial mientras existan préstamos saldados fuera del sistema.');
        }

        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table): void {
            $table->dropColumn(['EsSaldadoHistorico', 'FechaSaldamientoHistorico']);
        });
    }
};
