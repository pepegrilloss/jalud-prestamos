<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pago', 'ExcluirBalanceDiario')) {
            Schema::table('pago', function (Blueprint $table) {
                $table->boolean('ExcluirBalanceDiario')->default(false)->after('EsPagoInicial');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pago', 'ExcluirBalanceDiario')) {
            Schema::table('pago', function (Blueprint $table) {
                $table->dropColumn('ExcluirBalanceDiario');
            });
        }
    }
};
