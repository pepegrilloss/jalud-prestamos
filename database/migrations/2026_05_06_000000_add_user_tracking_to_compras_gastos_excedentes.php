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
        Schema::table('Compra', function (Blueprint $table) {
            $table->unsignedBigInteger('UsuarioRegistro')->nullable();
            $table->unsignedBigInteger('UsuarioModificacion')->nullable();
        });

        Schema::table('Gasto', function (Blueprint $table) {
            $table->unsignedBigInteger('UsuarioRegistro')->nullable();
            $table->unsignedBigInteger('UsuarioModificacion')->nullable();
        });

        Schema::table('excedentes', function (Blueprint $table) {
            $table->unsignedBigInteger('UsuarioRegistro')->nullable();
            $table->unsignedBigInteger('UsuarioModificacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Compra', function (Blueprint $table) {
            $table->dropColumn(['UsuarioRegistro', 'UsuarioModificacion']);
        });

        Schema::table('Gasto', function (Blueprint $table) {
            $table->dropColumn(['UsuarioRegistro', 'UsuarioModificacion']);
        });

        Schema::table('excedentes', function (Blueprint $table) {
            $table->dropColumn(['UsuarioRegistro', 'UsuarioModificacion']);
        });
    }
};
