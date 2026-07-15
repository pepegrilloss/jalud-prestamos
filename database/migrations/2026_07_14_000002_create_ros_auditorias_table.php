<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ros_auditorias', function (Blueprint $table) {
            $table->bigIncrements('RosAuditoriaID');
            $table->unsignedBigInteger('RosCasoID')->index();
            $table->unsignedBigInteger('SedeID')->index();
            $table->unsignedBigInteger('UserID')->nullable()->index();
            $table->string('Accion', 20);
            $table->string('Modelo', 80);
            $table->unsignedBigInteger('ModeloID')->nullable();
            $table->string('IpAddress', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['RosCasoID', 'created_at'], 'idx_ros_auditoria_caso_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ros_auditorias');
    }
};
