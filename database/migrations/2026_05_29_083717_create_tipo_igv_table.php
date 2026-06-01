<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_igv', function (Blueprint $table) {
            $table->tinyIncrements('TipoIgvID');
            $table->string('Codigo', 30)->unique();
            $table->string('Nombre', 100);
            $table->decimal('Porcentaje', 5, 2);
            $table->boolean('Activo')->default(true);
        });

        DB::table('tipo_igv')->insert([
            ['Codigo' => 'GRAVADO', 'Nombre' => 'IGV Normal', 'Porcentaje' => 18.00, 'Activo' => true],
            ['Codigo' => 'MYPE', 'Nombre' => 'IGV Restaurantes/Hoteles (MYPE)', 'Porcentaje' => 10.50, 'Activo' => true],
            ['Codigo' => 'EXONERADO', 'Nombre' => 'IGV Exonerado', 'Porcentaje' => 0.00, 'Activo' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_igv');
    }
};
