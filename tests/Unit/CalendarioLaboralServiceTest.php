<?php

namespace Tests\Unit;

use App\Models\CalendarioNoMoroso;
use App\Services\CalendarioLaboralService;
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

    private function setStaticProperty(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(CalendarioLaboralService::class);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
