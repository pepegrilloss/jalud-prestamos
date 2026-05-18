<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use App\Models\Negocio;
use App\Models\ProposicionCredito;
use App\Models\AprobacionProposicion;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Sede;
use App\Models\AperturaCierreDia;
use App\Models\TipoCredito;
use App\Models\Tasa;
use App\Models\Zona;
use App\Models\Ciudad;
use App\Models\Giro;
use App\Models\SubGiro;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class SeedTestingDataCommand extends Command
{
    protected $signature = 'app:seed-testing-data {--count=50}';
    protected $description = 'Seed the database with mass clients, proposiciones, creditos and pagos for QAS.';

    public function handle()
    {
        $count = (int) $this->option('count');

        // Context checks
        $sede = Sede::first();
        if (!$sede) {
            $this->error('No Sede setup found.');
            return;
        }

        $diaAbierto = AperturaCierreDia::where('EstadoDia', 'ABIERTO')->latest()->first();
        if (!$diaAbierto) {
            $this->error('No day currently opened (EstadoDia => ABIERTO) for generating quotes/payments. Open a day first.');
            return;
        }

        // Dictionaries
        $tiposCredito = TipoCredito::pluck('TipoCreditoID')->toArray();
        $tasas = Tasa::pluck('TasaID')->toArray();
        $zonas = Zona::pluck('ZonaID')->toArray();
        $ciudades = Ciudad::pluck('CiudadID')->toArray();
        $giros = Giro::pluck('GiroID')->toArray();
        $subGiros = SubGiro::pluck('SubGiroID')->toArray();
        $users = User::pluck('id')->toArray();
        $superUser = User::where('id', 1)->first() ?? User::first();

        if (empty($tiposCredito) || empty($tasas) || empty($zonas)) {
            $this->error('Missing parametric data (TipoCredito, Tasa, Zona) to generate valid credits.');
            return;
        }

        $faker = Faker::create('es_PE');
        $this->info("Starting the insertion of {$count} testing records... Please wait.");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        DB::beginTransaction();

        try {
            for ($i = 0; $i < $count; $i++) {
                
                $fechaBase = $diaAbierto->FechaApertura ? Carbon::parse($diaAbierto->FechaApertura) : now();
                $fechaActual = $fechaBase->copy()->setTime(now()->hour, now()->minute, now()->second);

                // 1. Create Cliente
                $sexo = $faker->randomElement(['M', 'F']);
                $cliente = Cliente::create([
                    'DNI' => $faker->unique()->numerify('########'),
                    'NombresApellidos' => strtoupper($faker->firstName($sexo == 'M' ? 'male' : 'female') . ' ' . $faker->lastName . ' ' . $faker->lastName),
                    'Sexo' => $sexo,
                    'FechaNacimiento' => Carbon::instance($faker->dateTimeBetween('-50 years', '-20 years')),
                    'Estado' => 'NO OBSERVADO',
                    'Domicilio' => strtoupper($faker->streetAddress),
                    'UsuarioRegistro' => $superUser->name,
                    'FechaRegistro' => $fechaActual->copy(),
                    'Activo' => 1,
                    'SedeID' => $sede->SedeID,
                ]);

                // 2. Create Negocio
                Negocio::create([
                    'ClienteID' => $cliente->ClienteID,
                    'CiudadID' => $faker->randomElement($ciudades) ?? null,
                    'ZonaID' => $faker->randomElement($zonas),
                    'DireccionNegocio' => strtoupper($faker->address),
                    'Antiguedad' => $faker->numberBetween(1, 10),
                    'GiroID' => $faker->randomElement($giros) ?? null,
                    'SubGiroID' => $faker->randomElement($subGiros) ?? null,
                    'Activo' => 1,
                    'SedeID' => $sede->SedeID,
                ]);

                // 3. Create Proposicion Credito
                $montoT = $faker->numberBetween(5, 50) * 100; // 500 to 5000
                $tasaId = $faker->randomElement($tasas);
                $tasaModel = Tasa::find($tasaId);
                $plazo = $faker->randomElement([15, 20, 24, 30]); // Días
                
                // Formulas simple para testing
                $tasaInteres = $tasaModel ? $tasaModel->Valor : 10;
                $montoInteres = round(($montoT * ($tasaInteres / 100)), 2);
                $montoTotalPagar = $montoT + $montoInteres;
                $montoCuota = round($montoTotalPagar / $plazo, 2);

                $codCredito = ProposicionCredito::generarCodigoCredito();

                $proposicion = ProposicionCredito::create([
                    'CodigoCredito' => $codCredito,
                    'ClienteID' => $cliente->ClienteID,
                    'CodigoCliente' => 'CLI-' . str_pad($cliente->ClienteID, 6, '0', STR_PAD_LEFT),
                    'TipoCreditoID' => $faker->randomElement($tiposCredito),
                    'MontoTotal' => $montoT,
                    'TasaID' => $tasaId,
                    'TasaInteres' => $tasaInteres,
                    'Plazo' => $plazo,
                    'NumeroCuotas' => $plazo,
                    'MontoCuota' => $montoCuota,
                    'MontoInteres' => $montoInteres,
                    'MontoTotalPagar' => $montoTotalPagar,
                    'SaldoPendiente' => $montoTotalPagar,
                    'TasaMora' => 1.00,
                    'ZonaID' => $faker->randomElement($zonas),
                    'UserProponenteID' => $faker->randomElement($users),
                    'FechaPropuesta' => $fechaActual->copy(),
                    'Estado' => 'NUEVO',
                    'Activo' => 1,
                    'EsRefinanciamiento' => 0,
                    'FueRefinanciada' => 0,
                    'SedeID' => $sede->SedeID,
                ]);
                $proposicion = ProposicionCredito::where('CodigoCredito', $codCredito)->first();
                $proposicionId = $proposicion->ProposicionCreditoID;
                
                // Approve manually bypassing normal route because we want rapid insertion
                ProposicionCredito::where('ProposicionCreditoID', $proposicionId)
                    ->update([
                        'Estado' => 'APROBADO',
                        'FechaAprobacion' => $fechaActual->copy(),
                        'UserAprobadorID' => $superUser->id
                    ]);

                // 4. Transform to Credito
                $credito = Credito::create([
                    'ProposicionCreditoID' => $proposicionId,
                    'TipoPagoID' => 1, // Assume 1 exists (Efectivo/Transferencia)
                    'UserGeneracionID' => $superUser->id,
                    'FechaGeneracion' => clone $fechaActual->subDays(rand(5, 15)), // Fake past date to generate past quotas
                    'ComentarioGeneracion' => 'Autogenerado QAS',
                    'Activo' => 1,
                    'SedeID' => $sede->SedeID,
                    'AperturaCierreDiaID' => $diaAbierto->AperturaCierreDiaID,
                ]);

                // The Observer automatically deployed quotas based on FechaGeneracion

                // 5. Pagos aleatorios a las cuotas
                $cuotas = $credito->cuotas()->whereNotIn('Estado', ['DOMINGO', 'FERIADO', 'PAGO_INICIAL'])->get();
                $numPagos = $faker->numberBetween(1, min(5, $cuotas->count()));
                $cuotasAPagar = $cuotas->take($numPagos);
                
                foreach ($cuotasAPagar as $cuota) {
                    Pago::create([
                        'CreditoID' => $credito->CreditoID,
                        'CuotaID' => $cuota->CuotaID,
                        'MontoPagado' => $cuota->MontoCuota,
                        'EsPagoAutomatico' => 0,
                        'EsMora' => 0,
                        'EsExonerado' => 0,
                        'MontoMoraExonerado' => 0,
                        'Comentario' => 'Pago de simulación',
                        'UserRegistroID' => $superUser->id,
                        'UsuarioRegistro' => $superUser->name,
                        'FechaPago' => clone $fechaActual->addHours(rand(1,6)),
                        'AperturaCierreDiaID' => $diaAbierto->AperturaCierreDiaID,
                        'Activo' => 1,
                        'SedeID' => $sede->SedeID,
                        'TipoConcepto' => 'N',
                        'MontoAmortizadoMora' => 0,
                        'MontoAmortizadoCuota' => $cuota->MontoCuota,
                    ]);
                    // Update cuota state
                    $cuota->Estado = 'PAGADA';
                    $cuota->save();
                }

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();
            $this->info("Successfully seeded $count realistic profiles!");

        } catch (\Throwable $e) {
            DB::rollBack();
            file_put_contents(base_path('seed-error.txt'), "Error seeding data: " . $e->getMessage() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString());
            $this->error("Error seeding data: " . $e->getMessage() . " on line " . $e->getLine());
        }
    }
}
