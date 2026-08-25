<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaldarCreditoResource\Pages;
use App\Models\Log as AuditLog;
use App\Models\Pago;
use App\Models\ProposicionCredito;
use App\Models\Zona;
use App\Services\DateFieldResolver;
use App\Services\FondoSedeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SaldarCreditoResource extends Resource
{
    protected static ?string $model = ProposicionCredito::class;

    protected static ?string $navigationGroup = 'Administración';
    protected static ?int $navigationGroupSort = 100;
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?int $navigationSort = 9;
    protected static ?string $label = 'Crédito a Saldar';
    protected static ?string $pluralLabel = 'Créditos por Saldar';

    public static function getEloquentQuery(): Builder
    {
        $sedeId = session('sede_activa') ?? auth()->user()?->getEffectiveSedeId();

        return parent::getEloquentQuery()
            ->withoutGlobalScope('sede')
            ->join('Cliente', 'ProposicionCredito.ClienteID', '=', 'Cliente.ClienteID')
            ->join('Credito', 'ProposicionCredito.ProposicionCreditoID', '=', 'Credito.ProposicionCreditoID')
            ->leftJoin('TipoCredito', 'ProposicionCredito.TipoCreditoID', '=', 'TipoCredito.TipoCreditoID')
            ->leftJoin('Zona', 'ProposicionCredito.ZonaID', '=', 'Zona.ZonaID')
            ->where('ProposicionCredito.Activo', 1)
            ->where('Credito.Activo', 1)
            ->where('ProposicionCredito.SedeID', $sedeId)
            ->where(function (Builder $q) {
                $q->where('ProposicionCredito.SaldoPendiente', '>', 0)
                  ->orWhere(function (Builder $sub) {
                      $sub->whereExists(function ($ex) {
                          $ex->select(DB::raw(1))
                             ->from('mora')
                             ->whereColumn('mora.CreditoID', 'Credito.CreditoID')
                             ->whereRaw('MoraAcumulada > COALESCE((
                                 SELECT SUM(p.MontoPagado) FROM pago p
                                  WHERE p.CreditoID = mora.CreditoID
                                    AND (p.TipoConcepto = ? OR p.EsMora = 1)
                                    AND p.Activo = 1
                             ), 0)', ['M']);
                      });
                  });
            })
            ->select(
                'ProposicionCredito.*',
                'Cliente.NombresApellidos',
                'Cliente.DNI',
                'Credito.CreditoID',
                'Credito.EstatusCreditoFinal',
                'Credito.FechaGeneracion',
                'TipoCredito.Descripcion as TipoCreditoDesc',
                'Zona.Nombre as ZonaNombre',
                DB::raw('(SELECT MoraAcumulada FROM mora WHERE CreditoID = Credito.CreditoID ORDER BY FechaMora DESC LIMIT 1) as MoraAcumulada')
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('CodigoCredito')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('FechaGeneracion')
                    ->label('F. Generación')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('DNI')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ZonaNombre')
                    ->label('Zona')
                    ->sortable(),

                Tables\Columns\TextColumn::make('MontoTotal')
                    ->label('Capital')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('SaldoPendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable()
                    ->color(fn($state) => (float)$state > 0 ? 'danger' : 'success')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\TextColumn::make('MoraAcumulada')
                    ->label('Mora Acum.')
                    ->money('PEN')
                    ->sortable()
                    ->color(fn($state) => ((float)$state ?? 0) > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('TipoCreditoDesc')
                    ->label('Tipo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ZonaID')
                    ->label('Zona')
                    ->options(function () {
                        $sedeId = session('sede_activa') ?? auth()->user()?->getEffectiveSedeId();

                        return Zona::where('Activo', true)
                            ->when($sedeId, fn($query) => $query->where('SedeID', $sedeId))
                            ->orderBy('Nombre')
                            ->pluck('Nombre', 'ZonaID');
                    })
                    ->query(fn(Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('ProposicionCredito.ZonaID', $data['value'])
                        : $query),

                Tables\Filters\Filter::make('con_mora')
                    ->label('Solo con mora pendiente')
                    ->toggle()
                    ->query(function (Builder $q) {
                        $q->whereExists(function ($ex) {
                            $ex->select(DB::raw(1))
                               ->from('mora')
                               ->whereColumn('mora.CreditoID', 'Credito.CreditoID')
                               ->whereRaw('MoraAcumulada > COALESCE((
                                   SELECT SUM(p.MontoPagado) FROM pago p
                                    WHERE p.CreditoID = mora.CreditoID
                                      AND (p.TipoConcepto = ? OR p.EsMora = 1)
                                      AND p.Activo = 1
                               ), 0)', ['M']);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('saldar')
                    ->label('Saldar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Saldar Crédito')
                    ->modalDescription(fn($record) => match (true) {
                        (float) ($record->SaldoPendiente ?? 0) > 0 => "Revise el monto para {$record->CodigoCredito}. Si no cubre todo el saldo, se registrara como pago parcial.",
                        default => "El credito {$record->CodigoCredito} ya tiene saldo S/ 0.00. Solo se eliminara la mora acumulada.",
                    })
                    ->form([
                        Forms\Components\TextInput::make('MontoSaldar')
                            ->label('Monto a saldar')
                            ->prefix('S/')
                            ->numeric()
                            ->required()
                            ->minValue(fn($record) => (float) ($record->SaldoPendiente ?? 0) > 0 ? 0.01 : 0)
                            ->default(fn($record) => (float) ($record->SaldoPendiente ?? 0))
                            ->helperText(fn($record) => (float) ($record->SaldoPendiente ?? 0) <= 0 ? 'Saldo pendiente: S/ 0.00. Solo se eliminara la mora.' : null)
                            ->extraAttributes(['onwheel' => 'return false;']),

                        Forms\Components\DatePicker::make('FechaSaldamiento')
                            ->label('Fecha de saldamiento')
                            ->required()
                            ->default(fn() => DateFieldResolver::getFechaAbierta() ?? now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\Textarea::make('Comentario')
                            ->label('Comentario')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function ($record, array $data) {
                        $creditoID = $record->CreditoID;
                        $codigo = $record->CodigoCredito;
                        $montoSaldar = (float) $data['MontoSaldar'];
                        $fechaSaldamiento = Carbon::parse($data['FechaSaldamiento'])->setTime(now()->hour, now()->minute, now()->second);
                        $comentario = trim((string) ($data['Comentario'] ?? ''));
                        $saldoAnterior = (float) ($record->SaldoPendiente ?? 0);

                        DB::beginTransaction();
                        try {
                            if ($montoSaldar > 0) {
                                $pago = Pago::create([
                                    'CreditoID' => $creditoID,
                                    'CuotaID' => null,
                                    'MontoPagado' => $montoSaldar,
                                    'FechaPago' => $fechaSaldamiento,
                                    'TipoPago' => Pago::TIPO_EFECTIVO,
                                    'TipoConcepto' => 'C',
                                    'EsMora' => false,
                                    'EsPagoAMayor' => $montoSaldar > $saldoAnterior,
                                    'EsPagoForzado' => true,
                                    'EsPagoAutomatico' => false,
                                    'Comentario' => $comentario ?: "Saldamiento manual de {$codigo}",
                                    'UsuarioRegistro' => auth()->user()?->name ?? (string) auth()->id(),
                                    'Activo' => true,
                                    'SedeID' => $record->SedeID,
                                ]);

                                app(FondoSedeService::class)->registrarIngresoRecaudo(
                                    $record->SedeID,
                                    $montoSaldar,
                                    $pago->PagoID,
                                    auth()->id()
                                );
                            }

                            // Usar el saldo ANTERIOR al pago (PagoObserver ya recalculo el
                            // saldo incluyendo el pago creado arriba). Restarlo de nuevo
                            // contaba el monto dos veces y saldaba creditos con saldo real.
                            $saldoRestante = max(0, $saldoAnterior - $montoSaldar);

                            if ($saldoRestante > 0) {
                                AuditLog::registrar(
                                    'PAGO_PARCIAL',
                                    'Credito',
                                    $creditoID,
                                    ['SaldoPendiente' => $saldoAnterior],
                                    [
                                        'SaldoPendiente' => $saldoRestante,
                                        'MontoSaldar' => $montoSaldar,
                                        'FechaPago' => $fechaSaldamiento->toDateTimeString(),
                                        'Comentario' => $comentario,
                                        'PagoID' => isset($pago) ? $pago->PagoID : null,
                                    ]
                                );

                                DB::commit();

                                Notification::make()
                                    ->success()
                                    ->title("Pago parcial registrado")
                                    ->body("Se registro S/ " . number_format($montoSaldar, 2) . " para {$codigo}. Saldo restante: S/ " . number_format($saldoRestante, 2) . ".")
                                    ->send();

                                return;
                            }

                            DB::table('ProposicionCredito')
                                ->where('ProposicionCreditoID', $record->ProposicionCreditoID)
                                ->update(['SaldoPendiente' => 0]);

                            DB::table('Credito')
                                ->where('CreditoID', $creditoID)
                                ->update([
                                    'EstatusCreditoFinal' => 'SALDADO',
                                    'FechaSaldamiento' => $fechaSaldamiento,
                                ]);

                            DB::table('cuota')
                                ->where('CreditoID', $creditoID)
                                ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA'])
                                ->update(['Estado' => 'PAGADA', 'FechaPago' => $fechaSaldamiento]);

                            $morasElim = DB::table('mora')->where('CreditoID', $creditoID)->delete();

                            AuditLog::registrar(
                                'SALDAR',
                                'Credito',
                                $creditoID,
                                ['SaldoPendiente' => $saldoAnterior],
                                [
                                    'SaldoPendiente' => 0,
                                    'MontoSaldar' => $montoSaldar,
                                    'FechaSaldamiento' => $fechaSaldamiento->toDateTimeString(),
                                    'Comentario' => $comentario,
                                    'PagoID' => isset($pago) ? $pago->PagoID : null,
                                    'MorasEliminadas' => $morasElim,
                                ]
                            );

                            DB::commit();

                            $mensaje = $montoSaldar > 0
                                ? "Pago registrado: S/ " . number_format($montoSaldar, 2) . ". Mora eliminada: {$morasElim} registros."
                                : "Mora eliminada: {$morasElim} registros. Credito ya estaba saldado.";

                            Notification::make()
                                ->success()
                                ->title("{$codigo} saldado")
                                ->body($mensaje)
                                ->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('saldar_masivo')
                    ->label('Saldar seleccionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Saldar Créditos')
                    ->modalDescription(fn($records) => "¿Está seguro de saldar {$records->count()} crédito(s)?")
                    ->action(function ($records) {
                        $saldados = 0;
                        $morasElim = 0;

                        DB::beginTransaction();
                        try {
                            foreach ($records as $record) {
                                $creditoID = $record->CreditoID;

                                DB::table('ProposicionCredito')
                                    ->where('ProposicionCreditoID', $record->ProposicionCreditoID)
                                    ->update(['SaldoPendiente' => 0]);

                                DB::table('Credito')
                                    ->where('CreditoID', $creditoID)
                                    ->update([
                                        'EstatusCreditoFinal' => 'SALDADO',
                                        'FechaSaldamiento' => now(),
                                    ]);

                                DB::table('cuota')
                                    ->where('CreditoID', $creditoID)
                                    ->whereIn('Estado', ['PENDIENTE', 'NORMAL', 'MORA', 'VENCIDA'])
                                    ->update(['Estado' => 'PAGADA', 'FechaPago' => now()]);

                                $morasElim += DB::table('mora')->where('CreditoID', $creditoID)->delete();
                                AuditLog::registrar(
                                    'SALDAR_MASIVO',
                                    'Credito',
                                    $creditoID,
                                    ['SaldoPendiente' => (float) $record->SaldoPendiente],
                                    ['SaldoPendiente' => 0, 'MorasEliminadas' => $morasElim],
                                    $record->SedeID
                                );
                                $saldados++;
                            }

                            DB::commit();

                            Notification::make()
                                ->success()
                                ->title("{$saldados} crédito(s) saldado(s)")
                                ->body("Mora eliminada: {$morasElim} registros.")
                                ->send();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('SaldoPendiente', 'desc')
            ->poll('60s');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaldarCreditos::route('/'),
        ];
    }
}
