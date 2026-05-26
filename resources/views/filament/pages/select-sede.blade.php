<div class="sede-selection-container">
    <div class="selection-card-wrapper">
        <div class="selection-header">
            <h1 class="selection-title">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="selection-subtitle">Por favor, selecciona la sede con la que trabajarás hoy.</p>
        </div>

        <div class="selection-grid">
            @php $user = auth()->user(); @endphp

            @foreach($this->getSedes() as $sede)
                <div wire:click="seleccionarSede({{ $sede->SedeID }})" class="sede-card group">
                    <div class="icon-circle">
                        <x-heroicon-o-building-office-2 class="sede-icon" />
                    </div>
                    <h3 class="sede-name">{{ $sede->Nombre }}</h3>
                    <p class="sede-desc">{{ $sede->Direccion ?? 'Sede operativa' }}</p>
                </div>
            @endforeach
        </div>

        <style>
            .sede-selection-container {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                background-color: #f3f4f6;
                padding: 1.5rem 3rem;
            }
            .dark .sede-selection-container {
                background-color: #111827;
            }
            .selection-card-wrapper {
                width: 100%;
                max-width: 64rem;
            }
            .selection-header {
                text-align: center;
                margin-bottom: 2.5rem;
            }
            .selection-title {
                font-size: 1.875rem;
                font-weight: 700;
                color: #111827;
            }
            .dark .selection-title {
                color: #ffffff;
            }
            .selection-subtitle {
                margin-top: 1rem;
                font-size: 1.125rem;
                color: #4b5563;
            }
            .dark .selection-subtitle {
                color: #9ca3af;
            }
            .selection-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
            }
            .sede-card {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem;
                background-color: #ffffff;
                border: 2px solid transparent;
                border-radius: 1rem;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                cursor: pointer;
                transition: all 0.2s;
            }
            .dark .sede-card {
                background-color: #1f2937;
            }
            .sede-card:hover {
                border-color: #a4cb3b;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                transform: translateY(-2px);
            }
            .icon-circle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 4rem;
                height: 4rem;
                margin-bottom: 1rem;
                border-radius: 9999px;
                background-color: #f4f9e6;
                color: #a4cb3b;
                transition: transform 0.2s;
            }
            .dark .icon-circle {
                background-color: rgba(164, 203, 59, 0.1);
            }
            .sede-card:hover .icon-circle {
                transform: scale(1.1);
            }
            .sede-icon {
                width: 2.5rem;
                height: 2.5rem;
            }
            .sede-name {
                font-size: 1.25rem;
                font-weight: 600;
                color: #111827;
            }
            .dark .sede-name {
                color: #ffffff;
            }
            .sede-desc {
                margin-top: 0.5rem;
                font-size: 0.875rem;
                text-align: center;
                color: #6b7280;
            }
            .dark .sede-desc {
                color: #9ca3af;
            }
        </style>
    </div>
</div>
