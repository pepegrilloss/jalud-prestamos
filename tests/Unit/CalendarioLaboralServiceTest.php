<?php

namespace Tests\Unit;

use App\Models\CalendarioNoMoroso;
use App\Services\CalendarioLaboralService;
use App\Services\CreditoFechaService;
use ReflectionClass;
use Tests\TestCase;

class CalendarioLaboralServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setStaticProperty('feriadosNagerPorAnio', []);
        $this->setStaticProperty('reglasLocales', null);

        parent::tearDown();
    }

    public function test_laborable_forzado_gana_sobre_feriado_nacional(): void
    {
        $this->setStaticProperty('feriadosNagerPorAnio', [
            2026 => ['2026-07-23' => 'Feriado nacional intercambiado'],
        ]);

        $this->setStaticProperty('reglasLocales', [
            1 => [
                '2026-07-23' => [
                    'tipo' => CalendarioNoMoroso::TIPO_LABORABLE_FORZADO,
                    'descripcion' => 'Se trabaja por intercambio',
                ],
            ],
        ]);

        $this->assertTrue(CalendarioLaboralService::esLaborable('2026-07-23', 1));
        $this->assertNull(CalendarioLaboralService::motivoNoLaborable('2026-07-23', 1));
    }

    public function test_no_laborable_local_gana_sobre_dia_normal(): void
    {
        $this->setStaticProperty('feriadosNagerPorAnio', [
            2026 => [],
        ]);

        $this->setStaticProperty('reglasLocales', [
            1 => [
                '2026-07-27' => [
                    'tipo' => CalendarioNoMoroso::TIPO_NO_LABORABLE,
                    'descripcion' => 'Descanso por intercambio',
                ],
            ],
        ]);

        $this->assertFalse(CalendarioLaboralService::esLaborable('2026-07-27', 1));
        $this->assertSame('Descanso por intercambio', CalendarioLaboralService::motivoNoLaborable('2026-07-27', 1));
    }

    public function test_laborable_forzado_puede_hacer_habil_un_domingo(): void
    {
        $this->setStaticProperty('feriadosNagerPorAnio', [
            2026 => [],
        ]);

        $this->setStaticProperty('reglasLocales', [
            1 => [
                '2026-07-26' => [
                    'tipo' => CalendarioNoMoroso::TIPO_LABORABLE_FORZADO,
                    'descripcion' => 'Domingo laborable',
                ],
            ],
        ]);

        $this->assertTrue(CalendarioLaboralService::esLaborable('2026-07-26', 1));
    }

    public function test_fecha_de_credito_usa_numero_de_cuotas_laborables(): void
    {
        $this->setStaticProperty('feriadosNagerPorAnio', [
            2026 => [],
        ]);
        $this->setStaticProperty('reglasLocales', []);

        $rango = CreditoFechaService::calcularRangoPorCuotasLaborables('2026-07-09', 3, 1);

        $this->assertSame('2026-07-10', $rango['FechaInicio']->toDateString());
        $this->assertSame('2026-07-13', $rango['FechaVencimiento']->toDateString());
    }

    public function test_cronograma_cuenta_solo_cuotas_laborables(): void
    {
        $this->setStaticProperty('feriadosNagerPorAnio', [
            2026 => [
                '2026-06-29' => 'San Pedro y San Pablo',
                '2026-07-23' => 'Dia de la Fuerza Aerea del Peru',
                '2026-07-28' => 'Fiestas Patrias',
                '2026-07-29' => 'Fiestas Patrias',
                '2026-08-06' => 'Batalla de Junin',
            ],
        ]);
        $this->setStaticProperty('reglasLocales', [
            1 => [
                '2026-07-23' => [
                    'tipo' => CalendarioNoMoroso::TIPO_LABORABLE_FORZADO,
                    'descripcion' => 'Se trabaja por intercambio',
                ],
                '2026-07-27' => [
                    'tipo' => CalendarioNoMoroso::TIPO_NO_LABORABLE,
                    'descripcion' => 'Descanso por intercambio',
                ],
            ],
        ]);

        $cronograma = CreditoFechaService::generarCronogramaPorCuotasLaborables('2026-06-26', 38, 1);
        $cuotasNumeradas = collect($cronograma['filas'])->where('NumeroCuota', '>', 0);

        $this->assertCount(38, $cuotasNumeradas);
        $this->assertSame('2026-06-27', $cronograma['FechaInicio']->toDateString());
        $this->assertSame('2026-08-15', $cronograma['FechaVencimiento']->toDateString());
        $this->assertSame(38, $cuotasNumeradas->last()['NumeroCuota']);
        $this->assertSame('2026-08-15', $cuotasNumeradas->last()['FechaVencimiento']);
    }

    private function setStaticProperty(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(CalendarioLaboralService::class);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
