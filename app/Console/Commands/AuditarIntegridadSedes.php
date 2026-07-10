<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditarIntegridadSedes extends Command
{
    protected $signature = 'sedes:auditar-integridad {--json : Mostrar resultado en JSON} {--fail-on-issues : Retornar codigo 1 si hay hallazgos}';

    protected $description = 'Audita cruces de SedeID en relaciones financieras criticas.';

    public function handle(): int
    {
        $checks = [
            'proposiciones_cliente' => DB::table('ProposicionCredito as pc')
                ->join('Cliente as c', 'c.ClienteID', '=', 'pc.ClienteID')
                ->where($this->sedeMismatch('pc.SedeID', 'c.SedeID'))
                ->selectRaw("'ProposicionCredito.ClienteID' as check_name, pc.ProposicionCreditoID as id, pc.SedeID as sede_registro, c.SedeID as sede_relacionada"),

            'creditos_proposicion' => DB::table('Credito as cr')
                ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'cr.ProposicionCreditoID')
                ->where($this->sedeMismatch('cr.SedeID', 'pc.SedeID'))
                ->selectRaw("'Credito.ProposicionCreditoID' as check_name, cr.CreditoID as id, cr.SedeID as sede_registro, pc.SedeID as sede_relacionada"),

            'cuotas_credito' => DB::table('cuota as cu')
                ->join('Credito as cr', 'cr.CreditoID', '=', 'cu.CreditoID')
                ->where($this->sedeMismatch('cu.SedeID', 'cr.SedeID'))
                ->selectRaw("'cuota.CreditoID' as check_name, cu.CuotaID as id, cu.SedeID as sede_registro, cr.SedeID as sede_relacionada"),

            'pagos_credito' => DB::table('pago as p')
                ->join('Credito as cr', 'cr.CreditoID', '=', 'p.CreditoID')
                ->where($this->sedeMismatch('p.SedeID', 'cr.SedeID'))
                ->selectRaw("'pago.CreditoID' as check_name, p.PagoID as id, p.SedeID as sede_registro, cr.SedeID as sede_relacionada"),

            'moras_credito' => DB::table('mora as m')
                ->join('Credito as cr', 'cr.CreditoID', '=', 'm.CreditoID')
                ->where($this->sedeMismatch('m.SedeID', 'cr.SedeID'))
                ->selectRaw("'mora.CreditoID' as check_name, m.MoraID as id, m.SedeID as sede_registro, cr.SedeID as sede_relacionada"),

            'aprobaciones_proposicion' => DB::table('AprobacionProposicion as ap')
                ->join('ProposicionCredito as pc', 'pc.ProposicionCreditoID', '=', 'ap.ProposicionCreditoID')
                ->where($this->sedeMismatch('ap.SedeID', 'pc.SedeID'))
                ->selectRaw("'AprobacionProposicion.ProposicionCreditoID' as check_name, ap.AprobacionProposicionID as id, ap.SedeID as sede_registro, pc.SedeID as sede_relacionada"),

            'solicitudes_exoneracion_credito' => DB::table('SolicitudExoneracion as se')
                ->join('Credito as cr', 'cr.CreditoID', '=', 'se.CreditoID')
                ->where($this->sedeMismatch('se.SedeID', 'cr.SedeID'))
                ->selectRaw("'SolicitudExoneracion.CreditoID' as check_name, se.SolicitudExoneracionID as id, se.SedeID as sede_registro, cr.SedeID as sede_relacionada"),

            'solicitudes_exoneracion_pago' => DB::table('SolicitudExoneracion as se')
                ->join('pago as p', 'p.PagoID', '=', 'se.PagoGeneradoID')
                ->whereNotNull('se.PagoGeneradoID')
                ->where($this->sedeMismatch('se.SedeID', 'p.SedeID'))
                ->selectRaw("'SolicitudExoneracion.PagoGeneradoID' as check_name, se.SolicitudExoneracionID as id, se.SedeID as sede_registro, p.SedeID as sede_relacionada"),

            'aprobaciones_exoneracion' => DB::table('AprobacionExoneracion as ae')
                ->join('SolicitudExoneracion as se', 'se.SolicitudExoneracionID', '=', 'ae.SolicitudExoneracionID')
                ->where($this->sedeMismatch('ae.SedeID', 'se.SedeID'))
                ->selectRaw("'AprobacionExoneracion.SolicitudExoneracionID' as check_name, ae.AprobacionExoneracionID as id, ae.SedeID as sede_registro, se.SedeID as sede_relacionada"),

            'excedentes_cliente_origen' => DB::table('excedentes as e')
                ->join('Cliente as c', 'c.ClienteID', '=', 'e.ClienteOrigenID')
                ->whereNotNull('e.ClienteOrigenID')
                ->where($this->sedeMismatch('e.SedeID', 'c.SedeID'))
                ->selectRaw("'excedentes.ClienteOrigenID' as check_name, e.ExcedenteID as id, e.SedeID as sede_registro, c.SedeID as sede_relacionada"),

            'excedentes_pago_origen' => DB::table('excedentes as e')
                ->join('pago as p', 'p.PagoID', '=', 'e.PagoOrigenID')
                ->whereNotNull('e.PagoOrigenID')
                ->where($this->sedeMismatch('e.SedeID', 'p.SedeID'))
                ->selectRaw("'excedentes.PagoOrigenID' as check_name, e.ExcedenteID as id, e.SedeID as sede_registro, p.SedeID as sede_relacionada"),

            'resoluciones_excedente' => DB::table('solicitudes_resolucion_excedente as s')
                ->join('excedentes as e', 'e.ExcedenteID', '=', 's.ExcedenteID')
                ->whereNotNull('s.ExcedenteID')
                ->where($this->sedeMismatch('s.SedeID', 'e.SedeID'))
                ->selectRaw("'solicitudes_resolucion_excedente.ExcedenteID' as check_name, s.SolicitudID as id, s.SedeID as sede_registro, e.SedeID as sede_relacionada"),

            'resoluciones_cliente_origen' => DB::table('solicitudes_resolucion_excedente as s')
                ->join('Cliente as c', 'c.ClienteID', '=', 's.ClienteOrigenID')
                ->whereNotNull('s.ClienteOrigenID')
                ->where($this->sedeMismatch('s.SedeID', 'c.SedeID'))
                ->selectRaw("'solicitudes_resolucion_excedente.ClienteOrigenID' as check_name, s.SolicitudID as id, s.SedeID as sede_registro, c.SedeID as sede_relacionada"),

            'resoluciones_cliente_destino' => DB::table('solicitudes_resolucion_excedente as s')
                ->join('Cliente as c', 'c.ClienteID', '=', 's.ClienteDestinoID')
                ->whereNotNull('s.ClienteDestinoID')
                ->where($this->sedeMismatch('s.SedeID', 'c.SedeID'))
                ->selectRaw("'solicitudes_resolucion_excedente.ClienteDestinoID' as check_name, s.SolicitudID as id, s.SedeID as sede_registro, c.SedeID as sede_relacionada"),

            'resoluciones_credito_origen' => DB::table('solicitudes_resolucion_excedente as s')
                ->join('Credito as cr', 'cr.CreditoID', '=', 's.CreditoOrigenID')
                ->whereNotNull('s.CreditoOrigenID')
                ->where($this->sedeMismatch('s.SedeID', 'cr.SedeID'))
                ->selectRaw("'solicitudes_resolucion_excedente.CreditoOrigenID' as check_name, s.SolicitudID as id, s.SedeID as sede_registro, cr.SedeID as sede_relacionada"),

            'resoluciones_credito_destino' => DB::table('solicitudes_resolucion_excedente as s')
                ->join('Credito as cr', 'cr.CreditoID', '=', 's.CreditoDestinoID')
                ->whereNotNull('s.CreditoDestinoID')
                ->where($this->sedeMismatch('s.SedeID', 'cr.SedeID'))
                ->selectRaw("'solicitudes_resolucion_excedente.CreditoDestinoID' as check_name, s.SolicitudID as id, s.SedeID as sede_registro, cr.SedeID as sede_relacionada"),

            'resoluciones_pago_origen' => DB::table('solicitudes_resolucion_excedente as s')
                ->join('pago as p', 'p.PagoID', '=', 's.PagoOrigenID')
                ->whereNotNull('s.PagoOrigenID')
                ->where($this->sedeMismatch('s.SedeID', 'p.SedeID'))
                ->selectRaw("'solicitudes_resolucion_excedente.PagoOrigenID' as check_name, s.SolicitudID as id, s.SedeID as sede_registro, p.SedeID as sede_relacionada"),
        ];

        $issues = [];

        foreach ($checks as $name => $query) {
            $rows = $query->limit(200)->get();
            foreach ($rows as $row) {
                $issues[] = [
                    'check' => $name,
                    'detalle' => $row->check_name,
                    'id' => $row->id,
                    'sede_registro' => $row->sede_registro,
                    'sede_relacionada' => $row->sede_relacionada,
                    'valor_registro' => $row->sede_registro,
                    'valor_relacionado' => $row->sede_relacionada,
                ];
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'total' => count($issues),
                'issues' => $issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif (empty($issues)) {
            $this->info('No se encontraron cruces de sede en las relaciones auditadas.');
        } else {
            $this->error('Se encontraron cruces de sede: ' . count($issues));
            $this->table(['check', 'detalle', 'id', 'sede_registro', 'sede_relacionada'], $issues);
        }

        return !empty($issues) && $this->option('fail-on-issues') ? self::FAILURE : self::SUCCESS;
    }

    private function sedeMismatch(string $left, string $right): callable
    {
        return function (Builder $query) use ($left, $right) {
            $query->whereColumn($left, '<>', $right)
                ->orWhereNull($left)
                ->orWhereNull($right);
        };
    }
}
