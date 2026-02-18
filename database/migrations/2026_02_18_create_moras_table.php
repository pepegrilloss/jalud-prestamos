<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mora', function (Blueprint $table) {
            $table->increments('MoraID');
            $table->integer('CreditoID');
            $table->date('FechaMora');
            $table->decimal('SaldoPendiente', 12, 2); // Saldo sobre el que se calculó
            $table->decimal('PorcentajeMora', 5, 2); // Porcentaje aplicado
            $table->decimal('MontoMora', 12, 2); // Monto de mora calculado
            $table->decimal('MoraAcumulada', 12, 2)->default(0); // Mora total acumulada hasta esa fecha
            $table->timestamps();

            // Foreign keys
            $table->foreign('CreditoID')->references('CreditoID')->on('credito')->onDelete('cascade');
            
            // Índices
            $table->index('CreditoID');
            $table->index('FechaMora');
            $table->unique(['CreditoID', 'FechaMora']); // No registrar mora duplicada por día
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mora');
    }
};
