<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excedentes', function (Blueprint $table) {
            $table->unsignedBigInteger('ClienteOrigenID')->nullable();
            $table->unsignedBigInteger('PagoOrigenID')->nullable();
            $table->string('EstadoResolucion', 20)->default('PENDIENTE');
        });
    }

    public function down(): void
    {
        Schema::table('excedentes', function (Blueprint $table) {
            $table->dropColumn(['ClienteOrigenID', 'PagoOrigenID', 'EstadoResolucion']);
        });
    }
};
