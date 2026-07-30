<?php

namespace App\Console\Commands;

use App\Models\Log;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditarCorregirPlazosEditados extends Command
{
    protected $signature = 'creditos:auditar-plazos-editados
        {--fix : Corrige los plazos detectados}
        {--codigo= : Audita/corrige solo un codigo de credito}
        {--sede-id= : Audita/corrige solo una sede}
        {--json : Muestra el resultado en JSON}
        {--limit=500 : Maximo de registros a revisar}
        {--usuario-id=0 : Usuario que quedara registrado en logs al corregir}
        {--all-candidates : Incluye tambien registros sin log EDITAR_CAPITAL_TASA}';

    protected $description = 'Audita y corrige creditos cuyo Plazo fue pisado con NumeroCuotas desde Editar Capital / Tasa.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $fix = (bool) $this->option('fix');
        $usuarioId = (int) $this->option('usuario-id');

        $query = DB::table('ProposicionCredito as pc')
            ->join('Credito as c', 'c.ProposicionCreditoID', '=', 'pc.ProposicionCreditoID')
            ->join('Tasa as t', 't.TasaID', '=', 'pc.TasaID')
            ->leftJoin('Cliente as cl', 'cl.ClienteID', '=', 'pc.ClienteID')
            ->leftJoin('Sede as s', 's.SedeID', '=', 'pc.SedeID')
            ->whereNotNull('pc.TasaID')
            ->whereNotNull('pc.Plazo')
            ->whereNotNull('pc.NumeroCuotas')
            ->whereNotNull('t.Dias')
            ->whereColumn('pc.Plazo', 'pc.NumeroCuotas')
            ->whereColumn('pc.Plazo', '<>', 't.Dias')
            ->when(! $this->option('all-candidates'), function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->from('logs as l')
                        ->selectRaw('1')
                        ->where('l.accion', 'EDITAR_CAPITAL_TASA')
                        ->where('l.modelo', 'Credito')
                        ->whereColumn('l.modelo_id', 'c.CreditoID');
                });
            })
            ->when($this->option('codigo'), fn ($q, $codigo) => $q->where('pc.CodigoCredito', trim((string) $codigo)))
            ->when($this->option('sede-id'), fn ($q, $sedeId) => $q->where('pc.SedeID', (int) $sedeId))
            ->select([
                'pc.ProposicionCreditoID',
                'c.CreditoID',
                'pc.CodigoCredito',
                'cl.NombresApellidos as Cliente',
                's.Nombre as Sede',
                'pc.SedeID',
                'pc.Plazo as PlazoActual',
                'pc.NumeroCuotas',
                'pc.TasaID',
                't.Nombre as TasaNombre',
                't.Valor as TasaValor',
                't.Dias as PlazoCorrecto',
                'c.FechaVencimiento',
                'c.EstatusCreditoFinal',
            ])
            ->selectRaw("(SELECT MAX(l.created_at) FROM logs l WHERE l.accion = 'EDITAR_CAPITAL_TASA' AND l.modelo = 'Credito' AND l.modelo_id = c.CreditoID) as UltimaEdicionCapitalTasa")
            ->orderByDesc('pc.ProposicionCreditoID')
            ->limit($limit);

        $candidatos = $query->get();

        $resultado = [
            'total' => $candidatos->count(),
            'fix' => $fix,
            'registros' => $candidatos->map(fn ($row) => [
                'CodigoCredito' => $row->CodigoCredito,
                'CreditoID' => $row->CreditoID,
                'ProposicionCreditoID' => $row->ProposicionCreditoID,
                'Cliente' => $row->Cliente,
                'Sede' => $row->Sede,
                'PlazoActual' => (int) $row->PlazoActual,
                'NumeroCuotas' => (int) $row->NumeroCuotas,
                'PlazoCorrecto' => (int) $row->PlazoCorrecto,
                'TasaID' => $row->TasaID,
                'TasaNombre' => $row->TasaNombre,
                'TasaValor' => (float) $row->TasaValor,
                'FechaVencimiento' => $row->FechaVencimiento,
                'EstatusCreditoFinal' => $row->EstatusCreditoFinal,
                'UltimaEdicionCapitalTasa' => $row->UltimaEdicionCapitalTasa,
            ])->values()->all(),
        ];

        if (! $fix) {
            $this->mostrarResultado($resultado);

            return self::SUCCESS;
        }

        $corregidos = [];

        DB::transaction(function () use ($candidatos, $usuarioId, &$corregidos) {
            foreach ($candidatos as $row) {
                $actualizado = DB::table('ProposicionCredito')
                    ->where('ProposicionCreditoID', $row->ProposicionCreditoID)
                    ->where('Plazo', $row->PlazoActual)
                    ->where('NumeroCuotas', $row->NumeroCuotas)
                    ->update([
                        'Plazo' => (int) $row->PlazoCorrecto,
                        'FechaModificacion' => now(),
                    ]);

                if (! $actualizado) {
                    continue;
                }

                Log::registrar(
                    'CORREGIR_PLAZO',
                    'ProposicionCredito',
                    (int) $row->ProposicionCreditoID,
                    [
                        'CodigoCredito' => $row->CodigoCredito,
                        'Plazo' => (int) $row->PlazoActual,
                        'NumeroCuotas' => (int) $row->NumeroCuotas,
                        'TasaID' => (int) $row->TasaID,
                    ],
                    [
                        'CodigoCredito' => $row->CodigoCredito,
                        'Plazo' => (int) $row->PlazoCorrecto,
                        'NumeroCuotas' => (int) $row->NumeroCuotas,
                        'TasaID' => (int) $row->TasaID,
                        'Motivo' => 'Correccion por bug en Editar Capital / Tasa: Plazo fue guardado usando NumeroCuotas.',
                    ],
                    (int) $row->SedeID,
                    $usuarioId
                );

                $corregidos[] = $row->CodigoCredito;
            }
        });

        $resultado['corregidos'] = $corregidos;
        $resultado['total_corregidos'] = count($corregidos);

        $this->mostrarResultado($resultado);

        return self::SUCCESS;
    }

    private function mostrarResultado(array $resultado): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->info('Creditos candidatos: '.$resultado['total']);

        if (! empty($resultado['registros'])) {
            $this->table(
                ['Credito', 'Cliente', 'Sede', 'Plazo actual', 'Cuotas', 'Plazo correcto', 'Tasa', 'Ultima edicion'],
                array_map(fn ($row) => [
                    $row['CodigoCredito'],
                    $row['Cliente'],
                    $row['Sede'],
                    $row['PlazoActual'],
                    $row['NumeroCuotas'],
                    $row['PlazoCorrecto'],
                    $row['TasaNombre'],
                    $row['UltimaEdicionCapitalTasa'],
                ], $resultado['registros'])
            );
        }

        if ($resultado['fix'] ?? false) {
            $this->info('Creditos corregidos: '.($resultado['total_corregidos'] ?? 0));
        } else {
            $this->warn('No se hicieron cambios. Ejecute con --fix para corregir.');
        }
    }
}
