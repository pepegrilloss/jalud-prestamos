<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Expandir columnas para permitir datos encriptados (mucho más largos)
     */
    public function up(): void
    {
        // Tabla Cliente
        Schema::table('Cliente', function (Blueprint $table) {
            // Cambiar VARCHAR(50) o similar a TEXT para acomodar datos encriptados
            $table->longText('DNI')->nullable()->change();
            $table->longText('NombresApellidos')->nullable()->change();
            $table->longText('ConyugeDNI')->nullable()->change();
            $table->longText('ConyugeNombresApellidos')->nullable()->change();
            $table->longText('Domicilio')->nullable()->change();
        });

        // Tabla TelefonoNegocio
        Schema::table('TelefonoNegocio', function (Blueprint $table) {
            $table->longText('Telefono')->nullable()->change();
        });

        // Tabla DocumentoCliente
        Schema::table('DocumentoCliente', function (Blueprint $table) {
            $table->longText('NombreOriginal')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a VARCHAR original (CUIDADO: esto puede perder datos)
        Schema::table('Cliente', function (Blueprint $table) {
            $table->string('DNI', 50)->nullable()->change();
            $table->string('NombresApellidos', 150)->nullable()->change();
            $table->string('ConyugeDNI', 50)->nullable()->change();
            $table->string('ConyugeNombresApellidos', 150)->nullable()->change();
            $table->string('Domicilio', 500)->nullable()->change();
        });

        Schema::table('TelefonoNegocio', function (Blueprint $table) {
            $table->string('Telefono', 20)->nullable()->change();
        });

        Schema::table('DocumentoCliente', function (Blueprint $table) {
            $table->string('NombreOriginal', 255)->nullable()->change();
        });
    }
};
