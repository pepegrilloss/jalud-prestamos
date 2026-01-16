<?php

namespace App\Filament\Components;

use App\Models\ProposicionCredito;
use Filament\Forms\Components\Component;

class SelectRefinanciamientoModal extends Component
{
    protected string $view = 'filament.components.select-refinanciamiento-modal';

    public ?int $clienteID = null;
    public array $creditosDisponibles = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function clienteID(?int $id): static
    {
        $this->clienteID = $id;
        if ($id) {
            $this->cargarCreditosDisponibles();
        }
        return $this;
    }

    private function cargarCreditosDisponibles(): void
    {
        $proposiciones = ProposicionCredito::obtenerCreditosActivosConSaldo($this->clienteID);
        $this->creditosDisponibles = $proposiciones->map(fn($p) => $p->obtenerInfoRefinanciamiento())->toArray();
    }

    public function getCreditosDisponibles(): array
    {
        return $this->creditosDisponibles;
    }
}
