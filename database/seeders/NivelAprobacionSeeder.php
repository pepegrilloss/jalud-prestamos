<?php

namespace Database\Seeders;

use App\Models\NivelAprobacion;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class NivelAprobacionSeeder extends Seeder
{
    public function run(): void
    {
        $niveles = [
            [
                'Nombre'      => 'Gerencia',
                'MontoMinimo' => 0,
                'MontoMaximo' => 99999999,
                'Orden'       => 1,
            ],
            [
                'Nombre'      => 'Supervisor Operativo',
                'MontoMinimo' => 0,
                'MontoMaximo' => 30000,
                'Orden'       => 2,
            ],
            [
                'Nombre'      => 'Jefe de Oficina Senior',
                'MontoMinimo' => 0,
                'MontoMaximo' => 5000,
                'Orden'       => 3,
            ],
            [
                'Nombre'      => 'Jefe de Oficina Junior',
                'MontoMinimo' => 0,
                'MontoMaximo' => 2000,
                'Orden'       => 4,
            ],
        ];

        $sedes = Sede::all();

        if ($sedes->isEmpty()) {
            $this->command->warn('No hay sedes registradas. No se crearon niveles de aprobación.');
            return;
        }

        foreach ($sedes as $sede) {
            foreach ($niveles as $nivel) {
                NivelAprobacion::updateOrCreate(
                    [
                        'Nombre' => $nivel['Nombre'],
                        'SedeID' => $sede->SedeID,
                    ],
                    [
                        'MontoMinimo'     => $nivel['MontoMinimo'],
                        'MontoMaximo'     => $nivel['MontoMaximo'],
                        'Orden'           => $nivel['Orden'],
                        'Activo'          => true,
                        'FechaCreacion'   => now(),
                        'FechaModificacion' => now(),
                    ]
                );
            }
        }

        $this->command->info('Niveles de aprobación creados/actualizados para ' . $sedes->count() . ' sede(s).');
    }
}
