<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Proveedor', function (Blueprint $table) {
            $table->id('ProveedorID');
            $table->string('Codigo', 20);
            $table->string('Nombre', 400);
            $table->string('RUC', 20);
            $table->string('Direccion', 400);
            $table->string('Telefono', 20)->nullable();
            $table->boolean('Activo')->default(true);
            $table->integer('SedeID');
            $table->timestamp('FechaCreacion')->nullable();
            $table->timestamp('FechaModificacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Proveedor');
    }
};
