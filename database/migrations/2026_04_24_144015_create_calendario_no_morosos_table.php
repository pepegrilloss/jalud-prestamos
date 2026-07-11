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
        Schema::dropIfExists('calendario_no_morosos');

        Schema::create('calendario_no_morosos', function (Blueprint $table) {
            $table->id('CalendarioNoMorosoID');
            $table->date('Fecha')->notNullable();
            $table->string('Descripcion', 255)->nullable();
            $table->string('Tipo', 30)->default('NO_LABORABLE');
            $table->boolean('Activo')->default(true);
            $table->datetime('FechaCreacion')->default(now());
            $table->datetime('FechaModificacion')->nullable();
            $table->integer('SedeID')->nullable();
            $table->foreign('SedeID')->references('SedeID')->on('Sede');
            $table->unique(['SedeID', 'Fecha'], 'UQ_CalendarioNoMoroso_Sede_Fecha');
            $table->index('Fecha', 'IDX_Fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendario_no_morosos');
    }
};
