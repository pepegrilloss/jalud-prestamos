<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AprobacionExoneracionResource\Pages;
use App\Models\SolicitudExoneracion;
use App\Models\AprobacionExoneracion;
use App\Models\UserNivelAprobacion;
use App\Models\Pago;
use App\Models\HistorialExoneracion;
use App\Services\DateFieldResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AprobacionExoneracionResource extends Resource
{
    protected static ?string $model = SolicitudExoneracion::class;

    protected static ?string $navigationGroup = 'Exoneraciones';
    protected static ?int $navigationGroupSort = 100;
    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?int $navigationSort = 10;
    protected static ?string $label = 'Aprobación de Exoneraciones';
    protected static ?string $pluralLabel = 'Aprobaciones de Exoneraciones';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        $userID = auth()->id();
        
        // El usuario debe tener el nivel de aprobación 3 (Gerencia) asignado
        $tieneNivelGerencia = UserNivelAprobacion::where('UserID', $userID)
            ->where('NivelAprobacionID', 3)
            ->where('Activo', 1)
            ->exists();

        // Si no tiene el nivel de gerencia, eliminar registros
        if (!$tieneNivelGerencia) {
            return $query->whereRaw('0 = 1'); // Sin resultados
        }

        // Solo mostrar solicitudes PENDIENTES que requieren nivel de aprobación 3 (Gerencia)
        return $query->where('NivelAprobacionRequerido', 3)
            ->where('Estado', 'PENDIENTE');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        // Ocultar para promotores/cobradores
        if ($user && $user->PromotorCobradorID) {
            return false;
        }
        return true;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        // Denegar acceso a promotores/cobradores
        if ($user && $user->PromotorCobradorID) {
            return false;
        }
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('credito.proposicion.CodigoCredito')
                    ->label('Código Crédito')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credito.proposicion.cliente.NombresApellidos')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tipoExoneracion.Nombre')
                    ->label('Tipo Exoneración')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('MontoExonerado')
                    ->label('Monto Exonerado')
                    ->money('PEN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('FechaSolicitud')
                    ->label('Fecha Solicitud')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('TipoExoneracionID')
                    ->label('Tipo de Exoneración')
                    ->relationship('tipoExoneracion', 'Nombre'),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with(['credito.proposicion.cliente', 'tipoExoneracion'])
                    ->where('Activo', 1)
                    ->orderBy('FechaSolicitud', 'desc');
            })
            ->actions([
                Tables\Actions\Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('Estado')
                            ->label('Decisión')
                            ->options([
                                'APROBADO' => 'Aprobar',
                                'RECHAZADO' => 'Rechazar',
                            ])
                            ->required()
                            ->placeholder('Seleccione una opción'),
                        Forms\Components\Textarea::make('Comentario')
                            ->label('Comentario')
                            ->required()
                            ->placeholder('Escriba el motivo de su decisión'),
                    ])
                    ->action(function (SolicitudExoneracion $record, array $data) {
                        $aprobacion = new AprobacionExoneracion();
                        $aprobacion->SolicitudExoneracionID = $record->SolicitudExoneracionID;
                        $aprobacion->NivelAprobacionID = 3;
                        $aprobacion->UserAprobadorID = auth()->id();
                        $aprobacion->Estado = $data['Estado'];
                        $aprobacion->Comentario = $data['Comentario'];
                        $fechaAbierta = DateFieldResolver::getFechaAbierta();
                        $aprobacion->FechaAprobacion = $fechaAbierta 
                            ? $fechaAbierta->copy()->setTime(now()->hour, now()->minute, now()->second) 
                            : now();
                        $aprobacion->save();

                        $record->update([
                            'Estado' => $data['Estado'],
                            'FechaModificacion' => $aprobacion->FechaAprobacion,
                        ]);

                        // Si se aprueba, crear pago automático
                        if ($data['Estado'] === 'APROBADO') {
                            $tipoConcepto = $record->tipoExoneracion->Codigo; // I, M, P
                            $usuarioActual = auth()->user();
                            
                            $pago = new Pago();
                            $pago->CreditoID = $record->CreditoID;
                            $pago->EsPagoAutomatico = 1;
                            $pago->TipoConcepto = $tipoConcepto;
                            $pago->MontoPagado = $record->MontoExonerado;
                            $pago->Comentario = 'Exoneración aprobada - ' . $data['Comentario'];
                            $pago->FechaPago = $aprobacion->FechaAprobacion;
                            $pago->UsuarioRegistro = $usuarioActual?->username ?? $usuarioActual?->name ?? auth()->id();
                            $pago->Activo = 1;
                            $pago->save();

                            // Actualizar el SaldoPendiente en proposicioncredito
                            $proposicion = $record->credito->proposicion;
                            if ($proposicion) {
                                $proposicion->SaldoPendiente -= $record->MontoExonerado;
                                $proposicion->save();
                            }

                            // Crear registro en HistorialExoneracion
                            $historial = new HistorialExoneracion();
                            $historial->SolicitudExoneracionID = $record->SolicitudExoneracionID;
                            $historial->CreditoID = $record->CreditoID;
                            $historial->ClienteID = $record->credito->proposicion->ClienteID;
                            $historial->TipoExoneracion = $record->tipoExoneracion->Codigo;
                            $historial->MontoExonerado = $record->MontoExonerado;
                            $historial->UsuarioAprobador = auth()->user()->name;
                            $historial->Comentario = $data['Comentario'];
                            $historial->FechaExoneracion = $aprobacion->FechaAprobacion;
                            $historial->save();
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Solicitud ' . ($data['Estado'] === 'APROBADO' ? 'aprobada' : 'rechazada'))
                            ->body('La solicitud ha sido ' . strtolower($data['Estado']) . ' correctamente')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Aprobar Solicitud de Exoneración')
                    ->modalDescription('Complete la información para aprobar o rechazar la solicitud')
                    ->modalButton('Confirmar'),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->recordUrl(null)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAprobacionExoneraciones::route('/'),
            'view' => Pages\EditAprobacionExoneracion::route('/{record}'),
        ];
    }
}

