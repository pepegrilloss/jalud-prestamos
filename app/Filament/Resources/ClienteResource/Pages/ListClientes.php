<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use App\Models\AperturaCierreDia;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    public function getTitle(): string
    {
        $title = 'Clientes';
        if (!AperturaCierreDia::estaAbierto()) {
            $title .= ' ⚠️ (Día Cerrado)';
        }
        return $title;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        if (AperturaCierreDia::estaAbierto()) {
            $actions[] = Actions\CreateAction::make()
                ->label('Nuevo Cliente');
        }

        $actions[] = Actions\Action::make('descargar_excel')
            ->label('Descargar Excel')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                DatePicker::make('fecha_desde')->label('Fecha Desde')->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('fecha_hasta')->label('Fecha Hasta')->native(false)->displayFormat('d/m/Y')->required(),
            ])
            ->action(function (array $data) {
                return $this->redirect(route('clientes.excel', [
                    'fecha_desde' => Carbon::parse($data['fecha_desde'])->format('Y-m-d'),
                    'fecha_hasta' => Carbon::parse($data['fecha_hasta'])->format('Y-m-d'),
                ]));
            })
            ->modalHeading('Descargar Excel')
            ->modalSubmitActionLabel('Descargar');

        $actions[] = Actions\Action::make('descargar_pdf')
            ->label('Descargar PDF')
            ->icon('heroicon-o-eye')
            ->color('danger')
            ->form([
                DatePicker::make('fecha_desde')->label('Fecha Desde')->native(false)->displayFormat('d/m/Y')->required(),
                DatePicker::make('fecha_hasta')->label('Fecha Hasta')->native(false)->displayFormat('d/m/Y')->required(),
            ])
            ->action(function (array $data) {
                $url = route('clientes.pdf', [
                    'fecha_desde' => Carbon::parse($data['fecha_desde'])->format('Y-m-d'),
                    'fecha_hasta' => Carbon::parse($data['fecha_hasta'])->format('Y-m-d'),
                ]);
                $this->js("window.open('{$url}', '_blank')");
            })
            ->modalHeading('Previsualizar PDF')
            ->modalSubmitActionLabel('Ver PDF');
        
        return $actions;
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (auth()->user()?->hasRole('Promotor Cobrador')) {
            $promotorCobrador = auth()->user()->promotorCobrador;
            
            if ($promotorCobrador && $promotorCobrador->ZonaID) {
                // Filtrar clientes cuyas proposiciones estén en la zona del promotor
                return $query->whereHas('proposiciones', function (Builder $q) use ($promotorCobrador) {
                    $q->where('ZonaID', $promotorCobrador->ZonaID);
                });
            }

            return $query->whereRaw('1 = 0');
        }

        return $query;
    }
}