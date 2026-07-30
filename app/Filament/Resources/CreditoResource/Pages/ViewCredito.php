<?php

namespace App\Filament\Resources\CreditoResource\Pages;

use App\Filament\Resources\CreditoResource;
use App\Models\Tasa;
use App\Models\Zona;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewCredito extends ViewRecord
{
    protected static string $resource = CreditoResource::class;

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $url = static::getResource()::getUrl('index');

        return new \Illuminate\Support\HtmlString("
            <div class='flex items-center gap-x-3'>
                <a href='{$url}' class='flex items-center justify-center rounded-full p-2 hover:bg-gray-500/5 focus:outline-none focus:ring-2 focus:ring-primary-500/70 transition'>
                    <svg class='w-5 h-5 text-gray-500 dark:text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 19l-7-7m0 0l7-7m-7 7h18' />
                    </svg>
                </a>
                <span>Ver Crédito</span>
            </div>
        ");
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editar_capital_tasa')
                ->label('Editar Capital / Tasa')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn () => auth()->user()?->can('editar_capital_tasa') ?? false)
                ->modalHeading('Editar Capital y Tasa de Interés')
                ->modalDescription('Modificar el capital solicitado y la tasa de interés. Se recalcularán los montos automáticamente.')
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('MontoTotal')
                                ->label('Capital Solicitado')
                                ->numeric()
                                ->required()
                                ->minValue(0.01)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, Get $get) {
                                    self::recalcularTotales($set, $get);
                                }),
                            Forms\Components\Select::make('TasaID')
                                ->label('Tasa de Interés')
                                ->options(function () {
                                    return Tasa::where('Activo', true)->pluck('Nombre', 'TasaID');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                    if ($state && $tasa = Tasa::find($state)) {
                                        $set('TasaInteres', (float) $tasa->Valor);
                                        $set('Plazo', (int) $tasa->Dias);
                                        $set('NumeroCuotas', (int) $tasa->Cuotas);
                                        self::recalcularTotales($set, $get);
                                    }
                                }),
                        ]),
                    Forms\Components\Select::make('ZonaID')
                        ->label('Zona')
                        ->options(function () {
                            $sedeId = $this->record->proposicion?->SedeID;

                            return Zona::where('Activo', true)
                                ->when($sedeId, fn ($query) => $query->where('SedeID', $sedeId))
                                ->orderBy('Nombre')
                                ->pluck('Nombre', 'ZonaID');
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Grid::make(3)
                        ->schema([
                            Forms\Components\TextInput::make('Plazo')
                                ->label('Plazo (dias)')
                                ->numeric()
                                ->integer()
                                ->required()
                                ->minValue(1),
                            Forms\Components\TextInput::make('NumeroCuotas')
                                ->label('N° Cuotas')
                                ->numeric()
                                ->integer()
                                ->required()
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Set $set, Get $get) {
                                    self::recalcularTotales($set, $get);
                                }),
                            Forms\Components\Placeholder::make('info_recalculo')
                                ->label('Valores Recalculados')
                                ->content(function (Get $get) {
                                    $monto = self::normalizarNumero($get('MontoTotal'));
                                    $tasa = self::normalizarNumero($get('TasaInteres'));
                                    $cuotas = (int) $get('NumeroCuotas');
                                    if ($monto <= 0 || $tasa <= 0 || $cuotas <= 0) {
                                        return 'Ingrese valores válidos';
                                    }

                                    $interes = round($monto * ($tasa / 100), 2);
                                    $total = round($monto + $interes, 2);
                                    $cuota = round($total / $cuotas, 2);

                                    return new \Illuminate\Support\HtmlString("
                                        <b>Interés:</b> S/ {$interes}<br>
                                        <b>Total a Pagar:</b> S/ {$total}<br>
                                        <b>Cuota:</b> S/ {$cuota}
                                    ");
                                }),
                        ]),
                    Forms\Components\Hidden::make('TasaInteres'),
                ])
                ->fillForm(function () {
                    $prop = $this->record->proposicion;

                    return [
                        'MontoTotal' => (float) ($prop->MontoTotal ?? 0),
                        'TasaID' => (int) ($prop->TasaID ?? 0),
                        'TasaInteres' => (float) ($prop->TasaInteres ?? 0),
                        'Plazo' => (int) ($prop->Plazo ?? 1),
                        'NumeroCuotas' => (int) ($prop->NumeroCuotas ?? 1),
                        'ZonaID' => (int) ($prop->ZonaID ?? 0),
                    ];
                })
                ->action(function (array $data) {
                    $prop = $this->record->proposicion;
                    if (! $prop) {
                        Notification::make()->danger()->title('Error')->body('No se encontró la proposición de crédito.')->send();

                        return;
                    }

                    $monto = self::normalizarNumero($data['MontoTotal'] ?? 0);
                    $tasa = self::normalizarNumero($data['TasaInteres'] ?? 0);
                    $tasaID = (int) ($data['TasaID'] ?? $prop->TasaID);
                    $plazo = (int) ($data['Plazo'] ?? $prop->Plazo);
                    $cuotas = (int) $data['NumeroCuotas'];
                    $zonaID = (int) ($data['ZonaID'] ?? $prop->ZonaID);
                    $interes = round($monto * ($tasa / 100), 2);
                    $totalPagar = round($monto + $interes, 2);
                    $montoCuota = round($totalPagar / $cuotas, 2);

                    $creditoID = $this->record->CreditoID;
                    $rangoFechas = \App\Services\CreditoFechaService::calcularRangoPorCuotasLaborables(
                        $this->record->FechaGeneracion,
                        $cuotas,
                        $this->record->SedeID
                    );
                    $valoresAnteriores = [
                        'MontoTotal' => (float) $prop->MontoTotal,
                        'TasaID' => (int) $prop->TasaID,
                        'TasaInteres' => (float) $prop->TasaInteres,
                        'ZonaID' => (int) $prop->ZonaID,
                        'Plazo' => (int) $prop->Plazo,
                        'NumeroCuotas' => (int) $prop->NumeroCuotas,
                        'SaldoPendiente' => (float) $prop->SaldoPendiente,
                        'FechaInicio' => $this->record->FechaInicio?->toDateString(),
                        'FechaVencimiento' => $this->record->FechaVencimiento?->toDateString(),
                    ];

                    $zona = Zona::withoutGlobalScope('sede')->find($zonaID);
                    if (! $zona || (int) $zona->SedeID !== (int) $prop->SedeID) {
                        Notification::make()->danger()->title('Zona invÃ¡lida')->body('La zona seleccionada no pertenece a la sede del crÃ©dito.')->send();

                        return;
                    }

                    $nuevoSaldo = DB::transaction(function () use ($prop, $monto, $tasa, $tasaID, $plazo, $cuotas, $zonaID, $interes, $totalPagar, $montoCuota, $creditoID, $rangoFechas) {
                        // 1. Actualizar ProposicionCredito
                        DB::table('ProposicionCredito')
                            ->where('ProposicionCreditoID', $prop->ProposicionCreditoID)
                            ->update([
                                'MontoTotal' => $monto,
                                'TasaID' => $tasaID,
                                'TasaInteres' => $tasa,
                                'ZonaID' => $zonaID,
                                'Plazo' => $plazo,
                                'NumeroCuotas' => $cuotas,
                                'MontoInteres' => $interes,
                                'MontoTotalPagar' => $totalPagar,
                                'MontoCuota' => $montoCuota,
                            ]);

                        DB::table('Credito')
                            ->where('CreditoID', $creditoID)
                            ->update([
                                'FechaInicio' => $rangoFechas['FechaInicio']->toDateString(),
                                'FechaVencimiento' => $rangoFechas['FechaVencimiento']->toDateString(),
                            ]);

                        // 2. Recalcular SaldoPendiente con la regla central del sistema
                        $nuevoSaldo = \App\Services\SaldoPendienteService::recalcular($prop->ProposicionCreditoID);

                        $tienePagos = DB::table('pago')
                            ->where('CreditoID', $creditoID)
                            ->where('Activo', 1)
                            ->where('EsMora', 0)
                            ->exists();

                        // 3. Actualizar cuotas existentes
                        $cuotasExistentes = DB::table('cuota')
                            ->where('CreditoID', $creditoID)
                            ->orderBy('NumeroCuota')
                            ->get();

                        if ($cuotasExistentes->count() >= $cuotas) {
                            $idsMantener = $cuotasExistentes->take($cuotas)->pluck('CuotaID');
                            DB::table('cuota')
                                ->where('CreditoID', $creditoID)
                                ->whereNotIn('CuotaID', $idsMantener)
                                ->delete();

                            DB::table('cuota')
                                ->whereIn('CuotaID', $idsMantener)
                                ->update(['MontoCuota' => $montoCuota, 'FechaModificacion' => now()]);
                        } else {
                            DB::table('cuota')
                                ->where('CreditoID', $creditoID)
                                ->update(['MontoCuota' => $montoCuota, 'FechaModificacion' => now()]);

                            $fechaBase = Carbon::parse($this->record->FechaInicio ?? now());
                            for ($i = $cuotasExistentes->count() + 1; $i <= $cuotas; $i++) {
                                DB::table('cuota')->insert([
                                    'CreditoID' => $creditoID,
                                    'NumeroCuota' => $i,
                                    'MontoCuota' => $montoCuota,
                                    'FechaVencimiento' => $fechaBase->copy()->addDays($i),
                                    'Estado' => 'PENDIENTE',
                                    'Activo' => 1,
                                    'SedeID' => $prop->SedeID,
                                    'FechaCreacion' => now(),
                                ]);
                            }
                        }

                        // 4. Si el nuevo saldo es 0, marcar como SALDADO
                        if ($nuevoSaldo <= 0 && $tienePagos) {
                            DB::table('Credito')
                                ->where('CreditoID', $creditoID)
                                ->update([
                                    'EstatusCreditoFinal' => 'SALDADO',
                                    'FechaSaldamiento' => now(),
                                ]);
                            DB::table('cuota')
                                ->where('CreditoID', $creditoID)
                                ->whereIn('Estado', ['PENDIENTE', 'VENCIDA', 'MORA'])
                                ->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);
                        } elseif ($nuevoSaldo > 0 && $this->record->EstatusCreditoFinal === 'SALDADO') {
                            DB::table('Credito')
                                ->where('CreditoID', $creditoID)
                                ->update([
                                    'EstatusCreditoFinal' => 'ACTIVO',
                                    'FechaSaldamiento' => null,
                                ]);
                        }

                        return $nuevoSaldo;
                    });

                    \App\Models\Log::registrar(
                        'EDITAR_CAPITAL_TASA',
                        'Credito',
                        $creditoID,
                        $valoresAnteriores,
                        [
                            'MontoTotal' => $monto,
                            'TasaID' => $tasaID,
                            'TasaInteres' => $tasa,
                            'ZonaID' => $zonaID,
                            'Plazo' => $plazo,
                            'NumeroCuotas' => $cuotas,
                            'MontoTotalPagar' => $totalPagar,
                            'MontoCuota' => $montoCuota,
                            'SaldoPendiente' => $nuevoSaldo,
                            'FechaInicio' => $rangoFechas['FechaInicio']->toDateString(),
                            'FechaVencimiento' => $rangoFechas['FechaVencimiento']->toDateString(),
                        ],
                        $prop->SedeID
                    );

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->modalWidth('lg'),

            Action::make('descargar_pagos')
                ->label('Descargar Pagos (PDF)')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('descargar-pagos.pdf', $this->record->CreditoID))
                ->openUrlInNewTab(),

            Action::make('eliminar_credito')
                ->label('Eliminar Crédito')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => auth()->user()?->esAdmin() ?? false)
                ->requiresConfirmation()
                ->modalHeading('⚠️ Eliminar Crédito')
                ->modalDescription('Esta acción eliminará el crédito permanentemente de todos los reportes y listados. El registro queda marcado para trazabilidad.')
                ->form([
                    Forms\Components\Textarea::make('motivo')
                        ->label('Motivo de Eliminación')
                        ->required()
                        ->placeholder('Indique la razón por la que se elimina este crédito...'),
                ])
                ->action(function (array $data) {
                    $creditoID = $this->record->CreditoID;
                    $propID = $this->record->ProposicionCreditoID;
                    $userID = auth()->id();

                    // Verificar si el credito tiene pagos registrados
                    $totalPagos = DB::table('pago')->where('CreditoID', $creditoID)->where('Activo', 1)->count();
                    if ($totalPagos > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar')
                            ->body("El crédito tiene {$totalPagos} pago(s) registrado(s). No se puede eliminar.")
                            ->persistent()
                            ->send();

                        return;
                    }

                    DB::transaction(function () use ($creditoID, $propID, $userID, $data) {
                        // Marcar proposicion como eliminada
                        DB::table('ProposicionCredito')
                            ->where('ProposicionCreditoID', $propID)
                            ->update([
                                'Eliminado' => 1,
                                'FechaEliminacion' => now(),
                                'UserEliminacionID' => $userID,
                                'MotivoEliminacion' => $data['motivo'],
                                'Activo' => 0,
                                'SaldoPendiente' => 0,
                            ]);

                        // Marcar credito como inactivo y eliminado
                        DB::table('Credito')
                            ->where('CreditoID', $creditoID)
                            ->update([
                                'Activo' => 0,
                                'EstatusCreditoFinal' => 'ELIMINADO',
                                'FechaSaldamiento' => now(),
                            ]);

                        // Desactivar pagos
                        DB::table('pago')->where('CreditoID', $creditoID)->update(['Activo' => 0]);
                        // Desactivar cuotas
                        DB::table('cuota')->where('CreditoID', $creditoID)->update(['Activo' => 0]);
                        // Eliminar mora
                        DB::table('mora')->where('CreditoID', $creditoID)->delete();
                    });

                    // Registrar en auditoría
                    \App\Models\Log::registrar(
                        'ELIMINAR_CREDITO',
                        'Credito',
                        $creditoID,
                        null,
                        ['codigo' => $this->record->CodigoCredito, 'motivo' => $data['motivo'], 'user_id' => auth()->id()]
                    );

                    Notification::make()
                        ->danger()
                        ->title('Crédito Eliminado')
                        ->body('El crédito ha sido eliminado. Queda registrado para trazabilidad.')
                        ->send();

                    $this->redirect(CreditoResource::getUrl('index'));
                }),
        ];
    }

    private static function recalcularTotales(Set $set, Get $get): void
    {
        $monto = self::normalizarNumero($get('MontoTotal'));
        $tasa = self::normalizarNumero($get('TasaInteres'));
        $cuotas = (int) $get('NumeroCuotas');

        if ($monto > 0 && $tasa > 0 && $cuotas > 0) {
            $interes = round($monto * ($tasa / 100), 2);
            // El Placeholder se actualiza solo via live()
        }
    }

    private static function normalizarNumero(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }

        $valor = preg_replace('/[^\d,.\-]/', '', $valor) ?? '';
        $ultimaComa = strrpos($valor, ',');
        $ultimoPunto = strrpos($valor, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($ultimaComa !== false) {
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema(CreditoResource::getInfolistSchema());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }
}
