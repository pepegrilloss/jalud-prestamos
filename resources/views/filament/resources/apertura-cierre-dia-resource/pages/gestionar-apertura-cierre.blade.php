<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6">
        <!-- Card de Estado -->
        <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-center">
                <h2 class="text-2xl font-bold mb-4">Estado del Día Actual</h2>
                
                @if($this->hoy)
                    <div class="space-y-2">
                        <div class="text-lg">
                            <strong>Fecha:</strong> {{ $this->hoy->Fecha->format('d/m/Y') }}
                        </div>
                        
                        <div class="text-xl font-bold mb-4">
                            @if($this->hoy->EstadoDia === 'ABIERTO')
                                <span class="text-green-600">✅ ABIERTO</span>
                            @else
                                <span class="text-red-600">❌ CERRADO</span>
                            @endif
                        </div>

                        @if($this->hoy->FechaApertura)
                            <div class="text-sm">
                                <strong>Abierto a las:</strong> {{ $this->hoy->FechaApertura->format('H:i:s') }}
                                @if($this->hoy->usuarioApertura)
                                    <br><strong>Por:</strong> {{ $this->hoy->usuarioApertura->name }}
                                @endif
                            </div>
                        @endif

                        @if($this->hoy->FechaCierre)
                            <div class="text-sm">
                                <strong>Cerrado a las:</strong> {{ $this->hoy->FechaCierre->format('H:i:s') }}
                                @if($this->hoy->usuarioCierre)
                                    <br><strong>Por:</strong> {{ $this->hoy->usuarioCierre->name }}
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-gray-500 text-lg">
                        Sin registro para hoy - Apertura el día para continuar
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
