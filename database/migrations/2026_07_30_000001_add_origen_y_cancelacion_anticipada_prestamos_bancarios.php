<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tesoreria_prestamo_pagos', function (Blueprint $table): void {
            $table->string('Tipo', 40)
                ->default('PAGO_CUOTA')
                ->index()
                ->after('PagoPrestamoBancarioID');
            $table->unsignedBigInteger('CuotaPrestamoBancarioID')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria_prestamo_pagos', function (Blueprint $table): void {
            $table->dropIndex(['Tipo']);
            $table->dropColumn('Tipo');
        });
    }
};
