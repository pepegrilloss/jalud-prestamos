<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoExoneracion;
use App\Models\Sede;

class TipoExoneracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sedeId = Sede::first()->SedeID;

        TipoExoneracion::create([
            'Codigo' => 'P',
            'Nombre' => 'Pronto Pago',
            'Descripcion' => 'Descuento por pagos puntuales',
            'Activo' => true,
            'FechaCreacion' => now(),
            'SedeID' => $sedeId,
        ]);

        TipoExoneracion::create([
            'Codigo' => 'I',
            'Nombre' => 'Interés',
            'Descripcion' => 'Exoneración de intereses',
            'Activo' => true,
            'FechaCreacion' => now(),
            'SedeID' => $sedeId,
        ]);

        TipoExoneracion::create([
            'Codigo' => 'M',
            'Nombre' => 'Mora',
            'Descripcion' => 'Exoneración de mora acumulada',
            'Activo' => true,
            'FechaCreacion' => now(),
            'SedeID' => $sedeId,
        ]);
    }
}
