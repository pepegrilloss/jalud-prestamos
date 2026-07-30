<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrestamoBancarioResource\Pages;
use App\Filament\Resources\PrestamoBancarioResource\RelationManagers;
use App\Filament\Widgets\AlertasCuotasPrestamosBancariosWidget;
use App\Models\CuentaTesoreria;
use App\Models\PrestamoBancario;
use App\Services\PrestamoBancarioService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrestamoBancarioResource extends Resource
{
    protected static ?string $model = PrestamoBancario::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Tesorería';

    protected static ?string $navigationLabel = 'Préstamos Bancarios';

    protected static ?string $modelLabel = 'Préstamo bancario';

    protected static ?string $pluralModelLabel = 'Préstamos bancarios';

    protected static ?int $navigationSort = 3;

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
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('cuentaTesoreria');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos del préstamo bancario')
                ->schema([
                    Forms\Components\Select::make('Banco')
                        ->label('Banco')
                        ->options(fn () => CuentaTesoreria::query()
                            ->where('Estado', CuentaTesoreria::ESTADO_ACTIVA)
                            ->orderBy('Banco')->distinct()->pluck('Banco', 'Banco'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('CuentaTesoreriaID', null))
                        ->required(),
                    Forms\Components\Select::make('CuentaTesoreriaID')
                        ->label('Cuenta bancaria de débito')
                        ->options(fn (Get $get) => CuentaTesoreria::query()
                            ->where('Estado', CuentaTesoreria::ESTADO_ACTIVA)
                            ->when($get('Banco'), fn (Builder $query, string $banco) => $query->where('Banco', $banco))
                            ->orderBy('NumeroCuenta')
                            ->get()
                            ->mapWithKeys(fn (CuentaTesoreria $cuenta) => [
                                $cuenta->CuentaTesoreriaID => $cuenta->NumeroCuenta,
                            ]))
                        ->searchable()
                        ->placeholder('Caja Abierta - Gerencia')
                        ->helperText('Si no selecciona una cuenta, las cuotas se descontarán de Caja Abierta - Gerencia.'),
                    Forms\Components\TextInput::make('Cliente')->label('Cliente / deudor')->required()->maxLength(255),
                    Forms\Components\TextInput::make('CuentaPrestamo')->label('Cuenta del préstamo')->required()->maxLength(100),
                    Forms\Components\TextInput::make('Operacion')->label('Operación')->maxLength(100),
                    Forms\Components\TextInput::make('MontoPrestamo')->label('Préstamo')->numeric()->prefix('S/')->minValue(0.01)->required()
                        ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularCronograma($set, $get)),
                    Forms\Components\DatePicker::make('FechaDesembolso')->label('Fecha de desembolso')->required()
                        ->live()->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularCronograma($set, $get)),
                    Forms\Components\TextInput::make('NumeroCuotas')->label('Cuotas')->numeric()->integer()->minValue(1)->maxValue(360)->required()
                        ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularCronograma($set, $get)),
                    Forms\Components\TextInput::make('DiaPago')->label('Día de pago')->numeric()->integer()->minValue(1)->maxValue(31)->required()
                        ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularCronograma($set, $get)),
                    Forms\Components\TextInput::make('TEA')->label('TEA')->numeric()->suffix('%')->minValue(0)->required()
                        ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularCronograma($set, $get)),
                    Forms\Components\TextInput::make('TED')->label('TED')->numeric()->suffix('%')->minValue(0)->required(),
                    Forms\Components\TextInput::make('PagoMensual')->label('Pago mensual')->numeric()->prefix('S/')->minValue(0)->required()
                        ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => static::recalcularCronograma($set, $get, true)),
                    Forms\Components\DatePicker::make('FechaVencimiento')->label('Fecha de vencimiento')->required(),
                    Forms\Components\Textarea::make('Observaciones')->maxLength(1000)->columnSpanFull(),
                ])->columns(3),
            Forms\Components\Section::make('Cronograma de pagos')
                ->description('Revise o ajuste los importes antes de confirmar el préstamo. Comisión y seguros inician en cero.')
                ->schema([
                    Forms\Components\Repeater::make('Cronograma')
                        ->schema([
                            Forms\Components\TextInput::make('Numero')->label('N°')->numeric()->integer()->disabled()->dehydrated(),
                            Forms\Components\DatePicker::make('FechaVencimiento')->label('F. vcto')->required(),
                            Forms\Components\TextInput::make('Capital')->numeric()->prefix('S/')->minValue(0)->required(),
                            Forms\Components\TextInput::make('Interes')->label('Interés')->numeric()->prefix('S/')->minValue(0)->required(),
                            Forms\Components\TextInput::make('Comision')->label('Comisión')->numeric()->prefix('S/')->minValue(0)->default(0)->required(),
                            Forms\Components\TextInput::make('Seguros')->numeric()->prefix('S/')->minValue(0)->default(0)->required(),
                            Forms\Components\TextInput::make('MontoCuota')->label('Cuota')->numeric()->prefix('S/')->minValue(0)->required(),
                            Forms\Components\TextInput::make('SaldoDeuda')->label('Saldo deuda')->numeric()->prefix('S/')->minValue(0)->required(),
                        ])->columns(4)->defaultItems(0)->addable(false)->deletable(false)->reorderable(false)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('PrestamoBancarioID')->label('#')->sortable(),
            Tables\Columns\TextColumn::make('Banco')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('Cliente')->searchable()->wrap(),
            Tables\Columns\TextColumn::make('CuentaPrestamo')->label('Cuenta')->searchable(),
            Tables\Columns\TextColumn::make('FuentePago')->label('Origen de pago')->toggleable(),
            Tables\Columns\TextColumn::make('Operacion')->searchable()->placeholder('-'),
            Tables\Columns\TextColumn::make('MontoPrestamo')->label('Préstamo')->money('PEN')->weight('bold')->sortable(),
            Tables\Columns\TextColumn::make('FechaVencimiento')->label('Vencimiento')->date('d/m/Y')->sortable(),
            Tables\Columns\BadgeColumn::make('Estado')->colors([
                'success' => PrestamoBancario::ESTADO_VIGENTE,
                'gray' => PrestamoBancario::ESTADO_CANCELADO,
                'warning' => PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO,
            ])->formatStateUsing(fn (string $state) => match ($state) {
                PrestamoBancario::ESTADO_VIGENTE => 'Vigente',
                PrestamoBancario::ESTADO_CANCELADO => 'Cancelado',
                PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO => 'Cancelado anticipadamente',
                default => $state,
            }),
        ])->filters([
            Tables\Filters\SelectFilter::make('Banco')->options(fn () => CuentaTesoreria::query()
                ->where('Estado', CuentaTesoreria::ESTADO_ACTIVA)->orderBy('Banco')->distinct()->pluck('Banco', 'Banco')),
            Tables\Filters\SelectFilter::make('Estado')->options([
                PrestamoBancario::ESTADO_VIGENTE => 'Vigente',
                PrestamoBancario::ESTADO_CANCELADO => 'Cancelado',
                PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO => 'Cancelado anticipadamente',
            ]),
        ])->actions([Tables\Actions\ViewAction::make()])->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Préstamo bancario')
                ->schema([
                    Infolists\Components\TextEntry::make('NombreBanco')->label('Banco'),
                    Infolists\Components\TextEntry::make('Cliente')->label('Cliente'),
                    Infolists\Components\TextEntry::make('CuentaPrestamo')->label('Cuenta'),
                    Infolists\Components\TextEntry::make('FuentePago')->label('Origen de pago'),
                    Infolists\Components\TextEntry::make('Operacion')->placeholder('-'),
                    Infolists\Components\TextEntry::make('MontoPrestamo')->label('Préstamo')->money('PEN'),
                    Infolists\Components\TextEntry::make('FechaDesembolso')->label('Desembolso')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('FechaVencimiento')->label('Vencimiento')->date('d/m/Y'),
                    Infolists\Components\TextEntry::make('NumeroCuotas')->label('Cuotas'),
                    Infolists\Components\TextEntry::make('DiaPago')->label('Día de pago'),
                    Infolists\Components\TextEntry::make('PagoMensual')->label('Pago mensual')->money('PEN'),
                    Infolists\Components\TextEntry::make('TEA')->suffix('%'),
                    Infolists\Components\TextEntry::make('TED')->suffix('%'),
                    Infolists\Components\TextEntry::make('Estado')->badge()
                        ->formatStateUsing(fn (string $state) => match ($state) {
                            PrestamoBancario::ESTADO_VIGENTE => 'Vigente',
                            PrestamoBancario::ESTADO_CANCELADO => 'Cancelado',
                            PrestamoBancario::ESTADO_CANCELADO_ANTICIPADO => 'Cancelado anticipadamente',
                            default => $state,
                        }),
                    Infolists\Components\TextEntry::make('CapitalPendiente')->label('Capital pendiente')->money('PEN'),
                    Infolists\Components\TextEntry::make('Observaciones')->placeholder('Sin observaciones')->columnSpanFull(),
                ])->columns(4),
        ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\CuotasRelationManager::class, RelationManagers\PagosRelationManager::class];
    }

    public static function getHeaderWidgets(): array
    {
        return [AlertasCuotasPrestamosBancariosWidget::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrestamosBancarios::route('/'),
            'create' => Pages\CreatePrestamoBancario::route('/create'),
            'view' => Pages\ViewPrestamoBancario::route('/{record}'),
        ];
    }

    private static function recalcularCronograma(Set $set, Get $get, bool $usarPagoMensualManual = false): void
    {
        $data = [
            'MontoPrestamo' => $get('MontoPrestamo'),
            'NumeroCuotas' => $get('NumeroCuotas'),
            'TEA' => $get('TEA'),
            'FechaDesembolso' => $get('FechaDesembolso'),
            'DiaPago' => $get('DiaPago'),
        ];
        if ($usarPagoMensualManual) {
            $data['PagoMensual'] = $get('PagoMensual');
        }

        $service = app(PrestamoBancarioService::class);
        $cronograma = $service->generarCronograma($data);
        if ($cronograma === []) {
            return;
        }

        $set('Cronograma', $cronograma);
        $set('TED', $service->calcularTed((float) $data['TEA']));
        $set('PagoMensual', $cronograma[0]['MontoCuota']);
        $set('FechaVencimiento', $cronograma[array_key_last($cronograma)]['FechaVencimiento']);
    }

    private static function enGerencia(): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'gerencia'
            && (auth()->user()?->puedeAccederAGerencia() ?? false);
    }
}
