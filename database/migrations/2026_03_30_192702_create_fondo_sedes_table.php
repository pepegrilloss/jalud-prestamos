<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fondo_sedes', function (Blueprint $table) {
            $table->id('FondoSedeID');
            $table->unsignedInteger('SedeID')->unique();
            $table->decimal('Saldo', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fondo_sedes');
    }
};
