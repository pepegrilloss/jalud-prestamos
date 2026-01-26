<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Primero, cerrar todos los días abiertos excepto el primero
        $diaAbierto = DB::table('apertura_cierre_dia')
            ->where('EstadoDia', 'ABIERTO')
            ->first();

        if ($diaAbierto) {
            DB::table('apertura_cierre_dia')
                ->where('EstadoDia', 'ABIERTO')
                ->where('AperturaCierreDiaID', '!=', $diaAbierto->AperturaCierreDiaID)
                ->update([
                    'EstadoDia' => 'CERRADO',
                    'FechaCierre' => now(),
                    'UsuarioCierreID' => 1, // o null si lo prefieres
                ]);
        }

        // Crear tabla temporal con constraint único
        Schema::create('apertura_cierre_dia_new', function (Blueprint $table) {
            $table->id('AperturaCierreDiaID');
            $table->date('Fecha')->unique();
            $table->string('EstadoDia', 20)->default('CERRADO');
            $table->dateTime('FechaApertura')->nullable();
            $table->dateTime('FechaCierre')->nullable();
            $table->foreignId('UsuarioAperturaID')->nullable()->constrained('users', 'id');
            $table->foreignId('UsuarioCierreID')->nullable()->constrained('users', 'id');
            $table->text('Observaciones')->nullable();
            $table->timestamps();
            
            // Índice único para asegurar que solo haya 1 día ABIERTO
            DB::statement("ALTER TABLE apertura_cierre_dia_new ADD CONSTRAINT unique_dia_abierto UNIQUE (EstadoDia) WHERE EstadoDia = 'ABIERTO'");
        });

        // Copiar datos de la tabla original a la nueva
        DB::table('apertura_cierre_dia_new')->insertUsing(
            ['AperturaCierreDiaID', 'Fecha', 'EstadoDia', 'FechaApertura', 'FechaCierre', 'UsuarioAperturaID', 'UsuarioCierreID', 'Observaciones', 'created_at', 'updated_at'],
            DB::table('apertura_cierre_dia')->select(['AperturaCierreDiaID', 'Fecha', 'EstadoDia', 'FechaApertura', 'FechaCierre', 'UsuarioAperturaID', 'UsuarioCierreID', 'Observaciones', 'created_at', 'updated_at'])
        );

        // Eliminar tabla original
        Schema::dropIfExists('apertura_cierre_dia');

        // Renombrar la nueva tabla
        Schema::rename('apertura_cierre_dia_new', 'apertura_cierre_dia');
    }

    public function down(): void
    {
        // Revertir si es necesario
        Schema::dropIfExists('apertura_cierre_dia_new');
    }
};
