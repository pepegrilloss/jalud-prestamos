<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaldarCreditoResource\Pages;
use App\Models\ProposicionCredito;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
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
                Tables\Filters\SelectFilter::make('zona')
                    ->label('Zona')
                    ->options(fn() => \App\Models\Zona::where('Activo', true)->pluck('Nombre', 'ZonaID'))
                    ->searchable()
                    ->query(fn(Builder $q, array $data) => $q->when(
                        $data['value'] ?? null,
                        fn(Builder $q, $v) => $q->where('ProposicionCredito.ZonaID', $v)
                    )),

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
                    ->modalDescription(fn($record) => "¿Está seguro de saldar {$record->CodigoCredito}? Se pondrá el saldo en 0 y se eliminará la mora.")
                    ->action(function ($record) {
                        $creditoID = $record->CreditoID;
                        $codigo = $record->CodigoCredito;

                        DB::beginTransaction();
                        try {
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

                            $morasElim = DB::table('mora')->where('CreditoID', $creditoID)->delete();

                            DB::commit();

                            Notification::make()
                                ->success()
                                ->title("{$codigo} saldado")
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
