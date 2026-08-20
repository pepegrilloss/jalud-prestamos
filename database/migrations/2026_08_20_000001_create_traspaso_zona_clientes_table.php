<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traspaso_zona_clientes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ClienteID');
            $table->unsignedBigInteger('ZonaAnteriorID');
            $table->unsignedBigInteger('ZonaNuevaID');
            $table->unsignedBigInteger('PromotorAnteriorID')->nullable();
            $table->unsignedBigInteger('PromotorNuevoID')->nullable();
            $table->text('MotivoTraspaso');
            $table->unsignedBigInteger('UserSolicitaID');
            $table->unsignedBigInteger('SedeID');
            $table->datetime('FechaTraspaso');
            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index('ClienteID');
            $table->index('ZonaAnteriorID');
            $table->index('ZonaNuevaID');
            $table->index('FechaTraspaso');
            $table->index('SedeID');
        });

        // Crear permiso Spatie para controlar acceso desde Filament Shield
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => 'traspasar_zona_clientes', 'guard_name' => 'web']
        );

        // También crear permisos para el resource de historial
        foreach (['view_any_traspaso::zona::cliente', 'view_traspaso::zona::cliente'] as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web']
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traspaso_zona_clientes');

        \Spatie\Permission\Models\Permission::where('name', 'traspasar_zona_clientes')->delete();
        \Spatie\Permission\Models\Permission::where('name', 'like', '%traspaso%zona%cliente%')->delete();
    }
};
