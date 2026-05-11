<?php

namespace App\Filament\Widgets;

use App\Models\TransferenciaSede;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SolicitudesPendientesSedeWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $sedeId = $user->SedeID;

        if ($user->esAdmin() && session('sede_activa')) {
            $sedeId = session('sede_activa');
        }

        if (!$sedeId) {
            return $table->query(TransferenciaSede::query()->whereRaw('1=0'));
        }

        return $table
            ->query(
                TransferenciaSede::where(function ($q) use ($sedeId) {
                    $q->where(function ($sq) use ($sedeId) {
                        $sq->where('SedeOrigenID', $sedeId)
                           ->where('Estado', 'PENDIENTE');
                    })->orWhere(function ($sq) use ($sedeId) {
                        $sq->where('SedeDestinoID', $sedeId)
                           ->where('EsSolicitudGerencia', true)
                           ->where('Estado', 'PENDIENTE');
                    });
                })
            )
            ->heading('Solicitudes y Órdenes Pendientes')
            ->description('Solicitudes de capital, traslados internos y órdenes de Gerencia pendientes de acción.')
            ->columns([
                Tables\Columns\TextColumn::make('TransferenciaID')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('Tipo')
                    ->label('Tipo')
                    ->getStateUsing(fn (TransferenciaSede $record) => match(true) {
                        $record->EsSolicitudGerencia => 'Gerencia solicita',
                        $record->EsSolicitudCapital => 'Solicitud Capital',
                        default => 'Traslado Interno',
                    })
                    ->badge()
                    ->color(fn (TransferenciaSede $record) => match(true) {
                        $record->EsSolicitudGerencia => 'warning',
                        $record->EsSolicitudCapital => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('Monto')
                    ->label('Monto Solicitado')
                    ->money('PEN'),
                Tables\Columns\TextColumn::make('Observacion')
                    ->label('Motivo')
                    ->limit(50),
                Tables\Columns\TextColumn::make('FechaTransferencia')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('usuarioOrigen.name')
                    ->label('Solicitado por'),
            ])
            ->emptyStateHeading('Sin solicitudes pendientes')
            ->emptyStateDescription('No hay traslados ni solicitudes de capital pendientes para esta sede.')
            ->paginated(false)
            ->defaultSort('TransferenciaID', 'desc');
    }

    public static function canView(): bool
    {
        return filament()->getCurrentPanel()?->getId() !== 'gerencia';
    }
}
