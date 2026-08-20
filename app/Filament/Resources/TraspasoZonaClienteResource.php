<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TraspasoZonaClienteResource\Pages;
use App\Models\TraspasoZonaCliente;
use App\Models\Zona;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TraspasoZonaClienteResource extends Resource
{
    protected static ?string $model = TraspasoZonaCliente::class;
    protected static ?string $navigationGroup = 'Clientes';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Traspasos de Zona';
    protected static ?string $modelLabel = 'Traspaso de Zona';
    protected static ?string $pluralModelLabel = 'Traspasos de Zona';
    protected static ?int $navigationGroupSort = 1;
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_traspaso::zona::cliente') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->columns([
                Tables\Columns\TextColumn::make('FechaTraspaso')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-calendar')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('cliente.DNI')
                    ->label('DNI')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('zonaAnterior.Nombre')
                    ->label('Zona Anterior')
                    ->badge()
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('zonaNueva.Nombre')
                    ->label('Zona Nueva')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('promotorAnterior.Descripcion')
                    ->label('Promotor Anterior')
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('promotorNuevo.Descripcion')
                    ->label('Promotor Nuevo')
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('MotivoTraspaso')
                    ->label('Motivo')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->MotivoTraspaso)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('userSolicita.name')
                    ->label('Ejecutado por')
                    ->sortable()
                    ->icon('heroicon-m-user')
                    ->iconColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ZonaAnteriorID')
                    ->label('Zona Anterior')
                    ->options(fn () => Zona::pluck('Nombre', 'ZonaID'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('ZonaNuevaID')
                    ->label('Zona Nueva')
                    ->options(fn () => Zona::pluck('Nombre', 'ZonaID'))
                    ->searchable(),

                Tables\Filters\Filter::make('FechaTraspaso')
                    ->label('Rango de Fechas')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('FechaTraspaso', '>=', $date)
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('FechaTraspaso', '<=', $date)
                            );
                    }),

                Tables\Filters\Filter::make('ClienteNombre')
                    ->label('Buscar Cliente')
                    ->form([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre del Cliente')
                            ->placeholder('Buscar por nombre'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['nombre'] ?? null,
                            fn (Builder $q, $value) => $q->whereHas('cliente', fn (Builder $sq) => $sq->where('NombresApellidos', 'like', "%{$value}%"))
                        );
                    }),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with([
                    'cliente',
                    'zonaAnterior',
                    'zonaNueva',
                    'promotorAnterior',
                    'promotorNuevo',
                    'userSolicita',
                ]);
            })
            ->defaultSort('FechaTraspaso', 'desc')
            ->actions([
                Tables\Actions\Action::make('ver_detalle')
                    ->label('Ver Detalle')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Detalle del Traspaso — {$record->cliente?->NombresApellidos}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(function ($record) {
                        return view('filament.modals.detalle-traspaso', ['traspaso' => $record]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTraspasoZonaClientes::route('/'),
        ];
    }
}
