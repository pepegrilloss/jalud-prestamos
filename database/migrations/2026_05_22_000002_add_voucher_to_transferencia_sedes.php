<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->string('VoucherImagen', 500)->nullable()->after('Observacion');
        });
    }

    public function down(): void
    {
        Schema::table('transferencia_sedes', function (Blueprint $table) {
            $table->dropColumn('VoucherImagen');
        });
    }
};
