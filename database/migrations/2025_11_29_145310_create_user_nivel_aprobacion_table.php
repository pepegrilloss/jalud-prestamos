<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UserNivelAprobacion', function (Blueprint $table) {
            $table->id('UserNivelAprobacionID');
            $table->unsignedBigInteger('UserID');
            $table->unsignedInteger('NivelAprobacionID');
            $table->dateTime('FechaAsignacion')->default(now());
            $table->boolean('Activo')->default(true);
            
            $table->foreign('UserID')->references('id')->on('users');
            $table->foreign('NivelAprobacionID')->references('NivelAprobacionID')->on('NivelAprobacion');
            $table->unique('UserID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UserNivelAprobacion');
    }
};