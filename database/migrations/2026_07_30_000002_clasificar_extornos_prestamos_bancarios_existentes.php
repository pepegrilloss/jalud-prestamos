<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tesoreria_prestamo_pagos')
            ->whereNotNull('PagoOriginalID')
            ->where('Tipo', 'PAGO_CUOTA')
            ->update(['Tipo' => 'EXTORNO_CUOTA']);
    }

    public function down(): void
    {
        DB::table('tesoreria_prestamo_pagos')
            ->where('Tipo', 'EXTORNO_CUOTA')
            ->update(['Tipo' => 'PAGO_CUOTA']);
    }
};
