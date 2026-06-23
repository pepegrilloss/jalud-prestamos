<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pago_bloqueo_promotor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('SedeID');
            $table->unsignedBigInteger('ZonaID')->nullable();
            $table->unsignedBigInteger('PromotorCobradorID')->nullable();
            $table->boolean('Activo')->default(true);
            $table->unsignedBigInteger('UsuarioBloqueoID')->nullable();
            $table->unsignedBigInteger('UsuarioDesbloqueoID')->nullable();
            $table->timestamps();

            $table->index(['SedeID', 'Activo']);
            $table->index(['ZonaID', 'Activo']);
            $table->index(['PromotorCobradorID', 'Activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_bloqueo_promotor');
    }
};
