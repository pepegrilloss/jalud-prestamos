<?php

namespace Tests\Feature;

use App\Models\ProposicionCredito;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Cuota;
use Tests\TestCase;

class RefinanciamientoTest extends TestCase
{
    public function test_obtener_cuentas_activas_con_saldo()
    {
        // Crear cliente
        $cliente = Cliente::factory()->create();
        
        // Crear proposición original
        $proposicionOriginal = ProposicionCredito::create([
            'ClienteID' => $cliente->ClienteID,
            'CodigoCredito' => 'C-000001',
            'TipoCreditoID' => 1,
            'MontoTotal' => 5000.00,
            'TasaID' => 1,
            'TasaInteres' => 5.00,
            'Plazo' => 30,
            'NumeroCuotas' => 10,
            'MontoCuota' => 525.00,
            'MontoInteres' => 250.00,
            'TasaMora' => 0.50,
            'ZonaID' => 1,
            'UserProponenteID' => 1,
            'Estado' => 'APROBADO',
            'Activo' => true,
        ]);

        // Crear crédito asociado
        $credito = Credito::create([
            'ProposicionCreditoID' => $proposicionOriginal->ProposicionCreditoID,
            'TipoPagoID' => 1,
            'UserGeneracionID' => 1,
            'Activo' => true,
        ]);

        // Crear cuotas pendientes
        for ($i = 1; $i <= 10; $i++) {
            Cuota::create([
                'CreditoID' => $credito->CreditoID,
                'NumeroCuota' => $i,
                'FechaVencimiento' => now()->addDays($i * 3)->format('Y-m-d'),
                'MontoCuota' => 525.00,
                'MontoCapital' => 500.00,
                'MontoInteres' => 25.00,
                'MontoPagado' => 0.00,
                'SaldoPendiente' => 525.00,
                'Estado' => 'PENDIENTE',
                'Activo' => 1,
            ]);
        }

        // Obtener cuentas disponibles
        $cuentasDisponibles = $proposicionOriginal->obtenerCuentasActivasConSaldo();

        // Aserciones
        $this->assertNotEmpty($cuentasDisponibles);
        $this->assertCount(1, $cuentasDisponibles);
        
        $cuenta = $cuentasDisponibles[0];
        $this->assertEquals($proposicionOriginal->ProposicionCreditoID, $cuenta->ProposicionCreditoID);
        $this->assertEquals('C-000001', $cuenta->CodigoCredito);
        $this->assertEquals(5250.00, $cuenta->SaldoPendiente); // 10 cuotas * 525
    }

    public function test_crear_refinanciamiento()
    {
        // Setup: crear proposición original con cuotas
        $cliente = Cliente::factory()->create();
        
        $proposicionOriginal = ProposicionCredito::create([
            'ClienteID' => $cliente->ClienteID,
            'CodigoCredito' => 'C-000001',
            'TipoCreditoID' => 1,
            'MontoTotal' => 5000.00,
            'TasaID' => 1,
            'TasaInteres' => 5.00,
            'Plazo' => 30,
            'NumeroCuotas' => 10,
            'MontoCuota' => 525.00,
            'MontoInteres' => 250.00,
            'TasaMora' => 0.50,
            'ZonaID' => 1,
            'UserProponenteID' => 1,
            'Estado' => 'APROBADO',
            'Activo' => true,
        ]);

        $credito = Credito::create([
            'ProposicionCreditoID' => $proposicionOriginal->ProposicionCreditoID,
            'TipoPagoID' => 1,
            'UserGeneracionID' => 1,
            'Activo' => true,
        ]);

        // Crear 5 cuotas pendientes
        for ($i = 1; $i <= 5; $i++) {
            Cuota::create([
                'CreditoID' => $credito->CreditoID,
                'NumeroCuota' => $i,
                'FechaVencimiento' => now()->addDays($i * 3)->format('Y-m-d'),
                'MontoCuota' => 525.00,
                'MontoCapital' => 500.00,
                'MontoInteres' => 25.00,
                'MontoPagado' => 0.00,
                'SaldoPendiente' => 525.00,
                'Estado' => 'PENDIENTE',
                'Activo' => 1,
            ]);
        }

        // Crear nueva proposición de refinanciamiento
        $saldoTotal = 525 * 5; // 2625

        $proposicionRefinanciada = ProposicionCredito::create([
            'ClienteID' => $cliente->ClienteID,
            'CodigoCredito' => 'C-000002',
            'TipoCreditoID' => 2, // Refinanciamiento
            'MontoTotal' => $saldoTotal,
            'TasaID' => 1,
            'TasaInteres' => 4.50,
            'Plazo' => 45,
            'NumeroCuotas' => 8,
            'MontoCuota' => round($saldoTotal / 8, 2),
            'MontoInteres' => round($saldoTotal * 0.045, 2),
            'TasaMora' => 0.50,
            'ZonaID' => 1,
            'UserProponenteID' => 1,
            'Estado' => 'PENDIENTE',
            'Activo' => true,
            'EsRefinanciamiento' => true,
            'ProposicionCreditoRefinanciadaID' => $proposicionOriginal->ProposicionCreditoID,
        ]);

        // Marcar cuotas como pagadas
        $cuotasPendientes = Cuota::where('CreditoID', $credito->CreditoID)
            ->where('Estado', '!=', 'PAGADA')
            ->get();

        foreach ($cuotasPendientes as $cuota) {
            $cuota->update([
                'Estado' => 'PAGADA',
                'MontoPagado' => $cuota->SaldoPendiente,
                'SaldoPendiente' => 0,
                'FechaPago' => now(),
            ]);
        }

        // Cambiar estado proposición anterior a CANCELADO
        $proposicionOriginal->update([
            'Estado' => 'CANCELADO',
        ]);

        // Aserciones
        $this->assertTrue($proposicionRefinanciada->EsRefinanciamiento);
        $this->assertEquals($proposicionOriginal->ProposicionCreditoID, $proposicionRefinanciada->ProposicionCreditoRefinanciadaID);
        
        $proposicionOriginalActualizada = ProposicionCredito::find($proposicionOriginal->ProposicionCreditoID);
        $this->assertEquals('CANCELADO', $proposicionOriginalActualizada->Estado);

        $cuotasActualizadas = Cuota::where('CreditoID', $credito->CreditoID)
            ->where('Estado', 'PAGADA')
            ->count();

        $this->assertEquals(5, $cuotasActualizadas);
    }
}
