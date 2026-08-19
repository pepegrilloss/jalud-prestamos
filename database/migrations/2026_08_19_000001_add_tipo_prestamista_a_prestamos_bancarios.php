<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table): void {
            $table->string('TipoPrestamista', 20)
                ->default('BANCO')
                ->index()
                ->after('CuentaTesoreriaID');
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table): void {
            $table->dropIndex(['TipoPrestamista']);
            $table->dropColumn('TipoPrestamista');
        });
    }
};
