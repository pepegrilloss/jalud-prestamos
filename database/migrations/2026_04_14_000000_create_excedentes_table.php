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
        Schema::create('excedentes', function (Blueprint $table) {
            $table->id('ExcedenteID');
            $table->enum('TipoExcedente', ['YAPE_TRANSFERENCIA', 'SOBRANTE_PROMOTOR', 'SOBRANTE_CAJERO']);
            $table->string('NroOperacion', 50)->nullable();
            $table->decimal('Monto', 12, 2);
            $table->date('Fecha');
            $table->time('Hora');
            $table->text('Observaciones')->nullable();
            
            $table->boolean('Activo')->default(true);
            $table->integer('ZonaID');
            $table->integer('SedeID')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excedentes');
    }
};
