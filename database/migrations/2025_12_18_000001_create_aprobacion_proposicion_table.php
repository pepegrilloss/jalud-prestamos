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
        Schema::create('AprobacionProposicion', function (Blueprint $table) {
            $table->id('AprobacionProposicionID');
            $table->unsignedInteger('ProposicionCreditoID');
            $table->unsignedInteger('NivelAprobacionID');
            $table->unsignedBigInteger('UserAprobadorID')->nullable();
            $table->enum('Estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO'])->default('PENDIENTE');
            $table->text('Comentario')->nullable();
            $table->timestamp('FechaAprobacion')->nullable();
            $table->timestamp('FechaCreacion')->useCurrent();
            
            $table->foreign('ProposicionCreditoID')
                ->references('ProposicionCreditoID')
                ->on('ProposicionCredito')
                ->onDelete('cascade');
            
            $table->foreign('NivelAprobacionID')
                ->references('NivelAprobacionID')
                ->on('NivelAprobacion');
            
            $table->foreign('UserAprobadorID')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            
            // Índices
            $table->index('ProposicionCreditoID');
            $table->index('NivelAprobacionID');
            $table->index('Estado');
            $table->unique(['ProposicionCreditoID', 'NivelAprobacionID']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('AprobacionProposicion');
    }
};
