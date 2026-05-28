<?php

namespace App\Filament\Pages;

use App\Models\Compra;
use App\Models\FondoSede;
use App\Models\Sede;
use App\Services\FondoSedeService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class FacturasPendientes extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Compras y Gastos';
    protected static ?string $title = 'Facturas Pendientes';
    protected static string $view = 'filament.pages.facturas-pendientes';
    protected static ?int $navigationSort = 2002;

    public static function canAccess(): bool
    {
        if (!auth()->check()) return false;
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') return true;
        return auth()->user()?->can('page_FacturasPendientes');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (filament()->getCurrentPanel()?->getId() === 'gerencia') return true;
        return auth()->user()?->can('page_FacturasPendientes');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Compra::activos()->where('TipoCompra', 'CREDITO')->where('EstadoPago', 'PENDIENTE'))
            ->columns([
                Tables\Columns\TextColumn::make('FechaEmision')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipoComprobante.Nombre')
                    ->label('Comprobante')
                    ->searchable(),
                Tables\Columns\TextColumn::make('Numero')
                    ->label('Número')
                    ->searchable(),
                Tables\Columns\TextColumn::make('proveedor.Nombre')
                    ->label('Proveedor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('Total')
                    ->label('Total')
                    ->numeric(2)
                    ->prefix('S/. ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sede.Nombre')
                    ->label('Sede')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID')),
            ])
            ->actions([
                Tables\Actions\Action::make('pagar')
                    ->label('Pagar Factura')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Pago')
                    ->modalContent(fn(Compra $record) => view('filament.pages.factura-pago-resumen', [
                        'subtotal' => $record->SubtotalBase,
                        'igv' => $record->MontoIGV,
                        'total' => $record->Total,
                        'igvLabel' => $record->TipoIGV === 'EXONERADO' ? 'IGV (Exonerado)' : 'IGV (18%)',
                        'proveedor' => $record->proveedor?->Nombre,
                        'numero' => $record->Numero,
                        'comprobante' => $record->tipoComprobante?->Nombre,
                    ]))
                    ->modalSubmitActionLabel('Sí, pagar')
                    ->action(function (Compra $record) {
                        $sedeId = $record->SedeID ?? auth()->user()->getEffectiveSedeId();
                        $fondo = FondoSede::withoutGlobalScope('sede')->where('SedeID', $sedeId)->first();
                        $saldo = $fondo ? (float) $fondo->SaldoCajaChica : 0;

                        if ($saldo < (float) $record->Total) {
                            Notification::make()
                                ->danger()
                                ->title('Saldo insuficiente en Caja Chica')
                                ->body("Saldo disponible: S/ " . number_format($saldo, 2) . ". Monto requerido: S/ " . number_format($record->Total, 2))
                                ->persistent()
                                ->send();
                            return;
                        }

                        try {
                            DB::transaction(function () use ($record, $sedeId) {
                                app(FondoSedeService::class)->registrarEgresoCajaChica(
                                    $sedeId,
                                    (float) $record->Total,
                                    $record->CompraID,
                                    auth()->id()
                                );
                                $record->update([
                                    'EstadoPago' => 'PAGADO',
                                    'FechaPago' => \App\Services\DateFieldResolver::getFechaAbierta() ?? now(),
                                    'UsuarioPagoID' => auth()->id(),
                                ]);
                            });
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al pagar factura')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Factura pagada correctamente')
                            ->send();
                    }),
            ])
            ->defaultSort('FechaEmision', 'desc')
            ->recordUrl(fn(Compra $record): string => route('filament.admin.resources.compras.view', ['record' => $record]))
            ->paginationPageOptions([10, 25, 50]);
    }
}
