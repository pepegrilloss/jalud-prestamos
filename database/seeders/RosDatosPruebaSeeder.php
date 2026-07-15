<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\RosCaso;
use App\Models\Sede;
use App\Models\Zona;
use Illuminate\Database\Seeder;

class RosDatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $sede = Sede::where('Activo', true)->orderBy('SedeID')->first();

        if (!$sede) {
            $this->command?->warn('No se crearon datos ROS de prueba: no existe una sede activa.');
            return;
        }

        $cliente = Cliente::withoutGlobalScope('sede')->where('SedeID', $sede->SedeID)->orderBy('ClienteID')->first();
        $credito = Credito::withoutGlobalScope('sede')->where('SedeID', $sede->SedeID)->orderBy('CreditoID')->first();
        $pago = Pago::withoutGlobalScope('sede')->where('SedeID', $sede->SedeID)->orderBy('PagoID')->first();
        $zona = Zona::withoutGlobalScope('sede')->where('SedeID', $sede->SedeID)->orderBy('ZonaID')->first();

        $caso = RosCaso::withoutGlobalScope('sede')->firstOrCreate(
            ['CodigoInterno' => 'ROS-PRUEBA-001'],
            [
                'SedeID' => $sede->SedeID,
                'ZonaID' => $zona?->ZonaID,
                'ClienteID' => $cliente?->ClienteID,
                'CreditoID' => $credito?->CreditoID,
                'PagoID' => $pago?->PagoID,
                'Estado' => RosCaso::ESTADO_EVALUACION,
                'ClaseReporte' => 'INICIAL',
                'FechaDeteccion' => now()->toDateString(),
                'FechaOperacionDesde' => now()->subDays(15)->toDateString(),
                'FechaOperacionHasta' => now()->toDateString(),
                'MontoTotal' => 2500.00,
                'Moneda' => 'PEN',
                'DelitoPrecedente' => 'No conoce el delito precedente',
                'Alcance' => 'NACIONAL',
                'SectorEconomico' => 'Comercio',
                'ActividadEconomica' => 'Comercio al por menor',
                'DescripcionHechos' => 'CASO DE PRUEBA. Registro creado para validar el flujo interno de Cumplimiento SBS. No corresponde a una operacion real ni debe ser reportado.',
                'ConclusionEvaluacion' => 'Pendiente de evaluacion del Oficial de Cumplimiento. Datos de prueba.',
                'EsDatosPrueba' => true,
            ]
        );

        $caso->personas()->updateOrCreate(
            ['NumeroDocumento' => '00000000'],
            [
                'SedeID' => $sede->SedeID,
                'ClienteID' => $cliente?->ClienteID,
                'TipoPersona' => 'NATURAL',
                'CondicionParticipacion' => 'TITULAR',
                'ApellidoPaterno' => 'PRUEBA',
                'ApellidoMaterno' => 'ROS',
                'Nombres' => 'CLIENTE',
                'TipoDocumento' => 'DNI',
                'EsPep' => false,
                'ProfesionOcupacion' => 'No aplica - datos de prueba',
            ]
        );

        $caso->operaciones()->updateOrCreate(
            ['NumeroOperacion' => 'OPERACION-PRUEBA-001'],
            [
                'SedeID' => $sede->SedeID,
                'ClienteID' => $cliente?->ClienteID,
                'CreditoID' => $credito?->CreditoID,
                'PagoID' => $pago?->PagoID,
                'ProductoServicio' => 'Credito de consumo',
                'CodigoProducto' => 'h',
                'Monto' => 2500.00,
                'Moneda' => 'PEN',
                'FechaOperacion' => now()->toDateString(),
                'Detalle' => 'Operacion ficticia de prueba.',
            ]
        );

        $caso->senalesAlerta()->updateOrCreate(
            ['Codigo' => 'I-1'],
            ['SedeID' => $sede->SedeID, 'Tipo' => 'REPORTADO', 'Descripcion' => 'Senal ficticia de prueba basada en el modelo ROS.']
        );

        $caso->tipologias()->updateOrCreate(
            ['Codigo' => 'PRUEBA-1'],
            ['SedeID' => $sede->SedeID, 'Descripcion' => 'Tipologia ficticia para validacion del modulo.']
        );

        $this->command?->info('Caso ROS de prueba creado o actualizado: ROS-PRUEBA-001');
    }
}
