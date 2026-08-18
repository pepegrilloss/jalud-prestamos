<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Models\Pago;
use App\Models\ProposicionCredito;
use App\Services\RefinanciamientoPagoService;
use Illuminate\Console\Command;

class CorregirPagoMayorRefinanciamiento extends Command
{
    protected $signature = 'refinanciamientos:corregir-pago-a-mayor
                            {codigo : Código del crédito anterior, por ejemplo C-006463}
                            {--fix : Registrar la diferencia faltante}';

    protected $description = 'Audita o corrige el pago a mayor faltante de un refinanciamiento';

    public function handle(RefinanciamientoPagoService $service): int
    {
        $codigo = strtoupper(trim((string) $this->argument('codigo')));
        $anterior = ProposicionCredito::withoutGlobalScopes()
            ->where('CodigoCredito', $codigo)
            ->first();

        if (! $anterior) {
            $this->error("No se encontró el crédito {$codigo}.");
            return self::FAILURE;
        }

        $nueva = ProposicionCredito::withoutGlobalScopes()
            ->where('ProposicionCreditoAnteriorID', $anterior->ProposicionCreditoID)
            ->where('EsRefinanciamiento', true)
            ->where('Activo', true)
            ->latest('ProposicionCreditoID')
            ->first();

        if (! $nueva) {
            $this->error("{$codigo} no tiene un refinanciamiento activo vinculado.");
            return self::FAILURE;
        }

        $creditoAnterior = Credito::withoutGlobalScopes()
            ->where('ProposicionCreditoID', $anterior->ProposicionCreditoID)
            ->where('Activo', true)
            ->firstOrFail();
        $creditoNuevo = Credito::withoutGlobalScopes()
            ->where('ProposicionCreditoID', $nueva->ProposicionCreditoID)
            ->where('Activo', true)
            ->first();

        if (! $creditoNuevo) {
            $this->error('La proposición de refinanciamiento todavía no tiene un crédito generado.');
            return self::FAILURE;
        }

        $pagos = Pago::withoutGlobalScopes()
            ->where('CreditoID', $creditoAnterior->CreditoID)
            ->where('Activo', true)
            ->where('EsPagoAutomatico', true)
            ->where('Comentario', 'like', '%Proposición #'.$nueva->ProposicionCreditoID.'%')
            ->get();
        $saldoAplicado = round((float) $pagos->where('EsPagoAMayor', false)->sum('MontoPagado'), 2);
        $registradoMayor = round((float) $pagos->where('EsPagoAMayor', true)->sum('MontoPagado'), 2);
        $esperadoMayor = round(max(0, (float) $nueva->MontoTotal - $saldoAplicado), 2);
        $faltante = round(max(0, $esperadoMayor - $registradoMayor), 2);

        $this->table(
            ['Crédito anterior', 'Crédito nuevo', 'Refinanciado', 'Saldo aplicado', 'A mayor registrado', 'Faltante'],
            [[
                $anterior->CodigoCredito,
                $nueva->CodigoCredito,
                'S/ '.number_format((float) $nueva->MontoTotal, 2),
                'S/ '.number_format($saldoAplicado, 2),
                'S/ '.number_format($registradoMayor, 2),
                'S/ '.number_format($faltante, 2),
            ]]
        );

        if ($faltante <= 0.009) {
            $this->info('El refinanciamiento ya está consistente. No se realizó ningún cambio.');
            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->warn('Modo auditoría: agregue --fix para registrar la diferencia.');
            return self::SUCCESS;
        }

        $resultado = $service->registrar($nueva, $creditoNuevo);
        $this->info('Pago a mayor registrado: S/ '.number_format($resultado['pago_a_mayor_creado'], 2));
        $this->info('PagoID: '.($resultado['pago_a_mayor_id'] ?? 'sin cambios'));

        return self::SUCCESS;
    }
}
