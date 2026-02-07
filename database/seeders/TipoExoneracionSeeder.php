<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoExoneracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('TipoExoneracion')->insert([
            [
                'Codigo' => 'P',
                'Nombre' => 'Pronto Pago',
                'Descripcion' => 'Descuento por pagos puntuales',
                'Activo' => true,
                'FechaCreacion' => now(),
            ],
            [
                'Codigo' => 'I',
                'Nombre' => 'Interés',
                'Descripcion' => 'Exoneración de intereses',
                'Activo' => true,
                'FechaCreacion' => now(),
            ],
            [
                'Codigo' => 'M',
                'Nombre' => 'Mora',
                'Descripcion' => 'Exoneración de mora acumulada',
                'Activo' => true,
                'FechaCreacion' => now(),
            ],
        ]);
    }
}
