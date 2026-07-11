<?php

namespace App\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Credito;

class CreditoGeneradoTotalWidget extends BaseWidget
{
    use HasWidgetShield;

    public ?string $fechaFiltro = null;

    protected int | string | array $columnSpan = 1;

    public function mount(): void
    {
        $this->fechaFiltro = session('creditos_fecha_filtro_v2');
    }

    public function updatedFechaFiltro($value)
    {
        session()->put('creditos_fecha_filtro_v2', $value);
        $this->dispatch('updateFechaCreditos', fecha: $value);
    }

    #[\Livewire\Attributes\On('updateFechaCreditos')]
    public function syncFechaFiltro($fecha)
    {
        $this->fechaFiltro = $fecha;
    }

    public static function canView(): bool
    {
        return auth()->user()->can('widget_' . class_basename(static::class));
    }


    protected function getStats(): array
    {
        $user = auth()->user();
        $fecha = $this->fechaFiltro;

        $query = Credito::withoutGlobalScope('sede')
            ->join('ProposicionCredito', 'Credito.ProposicionCreditoID', '=', 'ProposicionCredito.ProposicionCreditoID')
            ->where('ProposicionCredito.FueRefinanciada', 0);
        
        if ($fecha) {
            $query->whereDate('Credito.FechaGeneracion', $fecha);
        }

        if (!$user->isPrivileged() || $user->getEffectiveSedeId()) {
            $query->where('Credito.SedeID', $user->getEffectiveSedeId());
        }

        $totalMonto = (float) $query->sum('ProposicionCredito.MontoTotal');

        $inputId = 'datePickerTotal';

        $svgIcon = '<svg class="w-4 h-4 ml-1 cursor-pointer pointer-events-auto" onclick="setTimeout(() => { document.getElementById(\''.$inputId.'\').showPicker(); }, 50)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
  <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
</svg>';

        $text = $fecha ? 'Filtrado: ' . \Carbon\Carbon::parse($fecha)->format('d/m/Y') : 'Histórico Completo 📅';

        $description = '
        <span class="flex items-center">
            <span>'.$text.'</span>
            '.$svgIcon.'
            <input type="date" id="'.$inputId.'" wire:model.live="fechaFiltro" class="sr-only">
        </span>';

        return [
            Stat::make('Créditos Generados Totales', 'S/ ' . number_format($totalMonto, 2))
                ->description(new \Illuminate\Support\HtmlString($description))
                ->color($fecha ? 'warning' : 'success'),
        ];
    }
}
