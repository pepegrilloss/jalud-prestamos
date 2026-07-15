<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CuentaTesoreriaResource\Pages;
use App\Filament\Widgets\CajaAbiertaGerenciaTesoreriaWidget;
use App\Models\CuentaTesoreria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CuentaTesoreriaResource extends Resource
{
    protected static ?string $model = CuentaTesoreria::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Tesorería';
    protected static ?string $navigationLabel = 'Cuentas Bancarias';
    protected static ?string $modelLabel = 'Cuenta bancaria';
    protected static ?string $pluralModelLabel = 'Cuentas bancarias';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return self::enGerencia();
    }

    public static function canAccess(): bool
    {
        return self::enGerencia();
    }

    public static function canCreate(): bool
    {
        return self::enGerencia();
    }

    public static function canEdit($record): bool
    {
        return self::enGerencia();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cuenta bancaria')
                ->schema([
                    Forms\Components\TextInput::make('Banco')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('NumeroCuenta')
                        ->label('Numero de cuenta')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('Estado')
                        ->options([
                            CuentaTesoreria::ESTADO_ACTIVA => 'Activa',
                            CuentaTesoreria::ESTADO_INACTIVA => 'Inactiva',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('SaldoActual')
                        ->label('Saldo actual')
                        ->prefix('S/')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($record) => $record !== null),
                    Forms\Components\DateTimePicker::make('FechaUltimoMovimiento')
                        ->label('Ultimo movimiento')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($record) => $record !== null),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('Banco')
            ->columns([
                Tables\Columns\TextColumn::make('Banco')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('NumeroCuenta')->label('Numero de cuenta')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('SaldoActual')->label('Saldo actual')->money('PEN')->color('success')->weight('bold')->sortable(),
                Tables\Columns\TextColumn::make('FechaUltimoMovimiento')->label('Ultimo movimiento')->dateTime('d/m/Y H:i:s')->placeholder('Sin movimientos')->sortable(),
                Tables\Columns\BadgeColumn::make('Estado')->colors([
                    'success' => CuentaTesoreria::ESTADO_ACTIVA,
                    'gray' => CuentaTesoreria::ESTADO_INACTIVA,
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('Estado')->options([
                    CuentaTesoreria::ESTADO_ACTIVA => 'Activa',
                    CuentaTesoreria::ESTADO_INACTIVA => 'Inactiva',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getHeaderWidgets(): array
    {
        return [CajaAbiertaGerenciaTesoreriaWidget::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuentaTesorerias::route('/'),
            'edit' => Pages\EditCuentaTesoreria::route('/{record}/edit'),
        ];
    }

    private static function enGerencia(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'gerencia'
            && (auth()->user()?->puedeAccederAGerencia() ?? false);
    }
}
