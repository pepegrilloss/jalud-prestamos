<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditoResource\Pages;
use App\Models\Credito;
use App\Models\ProposicionCredito;
use App\Models\TipoPago;
use App\Models\Zona;
use App\Models\TipoCredito;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Sede;
class CreditoResource extends Resource
{
    protected static ?string $model = Credito::class;

    protected static ?string $navigationGroup = 'Créditos';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?int $navigationSort = 8;
    protected static ?string $label = 'Créditos Generados';
    protected static ?string $pluralLabel = 'Créditos Generados';

    protected static ?string $recordTitleAttribute = 'CreditoID';

    public static function getGloballySearchableAttributes(): array
    {
        return ['proposicion.CodigoCredito', 'proposicion.cliente.NombresApellidos', 'proposicion.cliente.DNI'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return 'Crédito: ' . ($record->proposicion?->CodigoCredito ?? '#' . $record->CreditoID) . ' (' . ($record->proposicion?->cliente?->NombresApellidos ?? 'Sin cliente') . ')';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Proposición')
                    ->schema([
                        Forms\Components\TextInput::make('proposicion_codigocredito')
                            ->label('Código de Crédito')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cliente_nombre')
                            ->label('Cliente')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cliente_dni')
                            ->label('DNI')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_monto')
                            ->label('Monto Total')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_tasa')
                            ->label('Tasa (%)')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_plazo')
                            ->label('Plazo (días)')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_cuotas')
                            ->label('Número de Cuotas')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_monto_cuota')
                            ->label('Monto por Cuota')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_interes')
                            ->label('Monto Total de Interés')
                            ->disabled(),

                        Forms\Components\TextInput::make('proposicion_mora')
                            ->label('Tasa de Mora (%)')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Información del Crédito Generado')
                    ->schema([
                        Forms\Components\TextInput::make('FechaGeneracion')
                            ->label('Fecha de Generación')
                            ->disabled(),

                        Forms\Components\Select::make('TipoPagoID')
                            ->label('Tipo de Pago')
                            ->relationship('tipoPago', 'Nombre')
                            ->disabled(),

                        Forms\Components\Textarea::make('ComentarioGeneracion')
                            ->label('Comentario de Generación')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.tipoCredito.Descripcion')
                    ->label('Tipo de Crédito')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.ZonaID')
                    ->label('Zona')
                    ->getStateUsing(function ($record) {
                        return $record->proposicion?->zona?->Nombre ?? '-';
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.TasaInteres')
                    ->label('Tasa (%)')
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', '') . ' %')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoInteres')
                    ->label('Interés')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.MontoTotalPagar')
                    ->label('Monto + Interés')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposicion.SaldoPendiente')
                    ->label('Saldo Pendiente')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaVencimiento')
                    ->label('Fecha Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->FechaVencimiento)
                            return 'gray';

                        if ($record->FechaVencimiento < today()) {
                            return 'danger'; // Vencido (fecha pasada) = Rojo
                        }

                        $diasFaltantes = today()->diffInDays($record->FechaVencimiento);
                        if ($diasFaltantes <= 5) {
                            return 'warning'; // Próximo a vencer (0-5 días) = Amarillo
                        }

                        return 'success'; // Al día (más de 5 días) = Verde
                    }),

                Tables\Columns\TextColumn::make('mora_acumulada')
                    ->label('Mora Acumulada')
                    ->money('PEN')
                    ->getStateUsing(function ($record) {
                        return $record->moras()?->latest('FechaMora')?->first()?->MoraAcumulada ?? 0;
                    })
                    ->color(function ($state) {
                        return $state > 0 ? 'danger' : 'success';
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('FechaGeneracion')
                    ->label('Fecha Generación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha_filtro')
                    ->form([
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Día de Generación')
                            ->default(fn () => now()->toDateString())
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['fecha'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('FechaGeneracion', $date),
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['fecha']) {
                            return null;
                        }
                        return 'Día: ' . \Carbon\Carbon::parse($data['fecha'])->format('d/m/Y');
                    }),
                Tables\Filters\SelectFilter::make('SedeID')
                    ->label('Sede')
                    ->options(Sede::where('Activo', true)->pluck('Nombre', 'SedeID'))
                    ->visible(fn() => auth()->user()->esAdmin()),
                Tables\Filters\SelectFilter::make('TipoPagoID')
                    ->label('Tipo de Pago')
                    ->relationship('tipoPago', 'Nombre'),
                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona')
                    ->options(Zona::where('Activo', true)->pluck('Nombre', 'ZonaID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('ZonaID', $data['value']))
                        );
                    })
                    ->native(false),
                Tables\Filters\SelectFilter::make('cliente')
                    ->label('Cliente')
                    ->options(function () {
                        return \App\Models\Cliente::where('Activo', true)
                            ->whereHas('proposiciones.credito')
                            ->pluck('NombresApellidos', 'ClienteID')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion.cliente', fn(Builder $subQ) => $subQ->where('ClienteID', $data['value']))
                        );
                    })
                    ->searchable()
                    ->native(false),
                Tables\Filters\SelectFilter::make('tipoCredito')
                    ->label('Tipo de Crédito')
                    ->options(TipoCredito::where('Activo', true)->pluck('Descripcion', 'TipoCreditoID')->toArray())
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn(Builder $q) => $q->whereHas('proposicion', fn(Builder $subQ) => $subQ->where('TipoCreditoID', $data['value']))
                        );
                    })
                    ->native(false),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->with(['proposicion', 'tipoPago'])
                    // Excluir créditos de proposiciones refinanciadas
                    ->whereHas('proposicion', function (Builder $q) {
                        $q->where('FueRefinanciada', 0);
                    });
            })
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('descargar_libreta')
                    ->label('Excel')
                    ->tooltip('Descargar Libreta de Pagos (Excel)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn($record) => route('libreta-pagos.descargar', $record->CreditoID))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('descargar_libreta_html')
                    ->label('Imprimir')
                    ->tooltip('Ver Libreta de Pagos para Imprimir')
                    ->icon('heroicon-o-printer')
                    ->url(fn($record) => route('libreta-pagos.html', $record->CreditoID))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('descargar_ticket')
                    ->label('Descargar Ticket')
                    ->icon('heroicon-o-ticket')
                    ->color('danger')
                    ->url(fn($record) => route('ticket.descargar', $record->CreditoID))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->defaultSort('FechaGeneracion', 'desc')
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditos::route('/'),
            'view' => Pages\ViewCredito::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            CreditoResource\Widgets\CreditosDelDiaStats::class,
        ];
    }

    public static function canCreate(): bool
    {
        if (!parent::canCreate()) { return false; }

        if (!\App\Models\AperturaCierreDia::estaAbierto()) {
            \Filament\Notifications\Notification::make()
                ->title('❌ Día Cerrado')
                ->body('El día de operaciones está cerrado. No se pueden realizar operaciones. Contacte con administración.')
                ->danger()
                ->persistent()
                ->send();
            return false;
        }
        return true;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canEdit($record)) { return false; }

        // Si tiene FechaCierre, no se puede editar
        if ($record->FechaCierre !== null) {
            return false;
        }

        // Verificar si el día de generación está cerrado
        $fechaGeneracion = $record->FechaGeneracion->toDateString();
        $fechaHoy = now()->toDateString();

        if ($fechaGeneracion !== $fechaHoy) {
            $diaDel = \App\Models\AperturaCierreDia::whereDate('Fecha', $fechaGeneracion)->first();
            if ($diaDel && $diaDel->EstadoDia === 'CERRADO') {
                return false;
            }
        }
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (!parent::canDelete($record)) { return false; }

        // Si tiene FechaCierre, no se puede eliminar
        if ($record->FechaCierre !== null) {
            return false;
        }

        // Verificar si el día de generación está cerrado
        $fechaGeneracion = $record->FechaGeneracion->toDateString();
        $fechaHoy = now()->toDateString();

        if ($fechaGeneracion !== $fechaHoy) {
            $diaDel = \App\Models\AperturaCierreDia::whereDate('Fecha', $fechaGeneracion)->first();
            if ($diaDel && $diaDel->EstadoDia === 'CERRADO') {
                return false;
            }
        }
        return true;
    }
}
