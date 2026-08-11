@once
    <style>
        .jalud-alerta-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 92px;
            height: 30px;
            padding: 0 10px;
            border: 1px solid transparent;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            cursor: help;
            transition: border-color 150ms ease, background-color 150ms ease;
        }

        .jalud-alerta-chip:focus-visible {
            outline: 2px solid currentColor;
            outline-offset: 2px;
        }

        .jalud-alerta-chip svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .jalud-alerta-chip--bloqueante {
            color: #b91c1c;
            background: #fef2f2;
            border-color: #fecaca;
        }

        .jalud-alerta-chip--bloqueante:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .jalud-alerta-chip--advertencia {
            color: #a16207;
            background: #fffbeb;
            border-color: #fde68a;
        }

        .jalud-alerta-chip--advertencia:hover {
            background: #fef3c7;
            border-color: #fcd34d;
        }

        .jalud-alerta-chip--ok {
            color: #15803d;
            background: #f0fdf4;
            border-color: #bbf7d0;
            cursor: default;
        }

        .dark .jalud-alerta-chip--bloqueante {
            color: #fca5a5;
            background: rgba(127, 29, 29, 0.28);
            border-color: #7f1d1d;
        }

        .dark .jalud-alerta-chip--advertencia {
            color: #fcd34d;
            background: rgba(120, 53, 15, 0.28);
            border-color: #78350f;
        }

        .dark .jalud-alerta-chip--ok {
            color: #86efac;
            background: rgba(20, 83, 45, 0.28);
            border-color: #14532d;
        }
    </style>
@endonce

@php
    $record = $getRecord();
    $alertas = app(\App\Services\ProposicionAprobacionValidatorService::class)->obtenerAlertas($record);
    $bloqueantes = $alertas['bloqueantes'] ?? [];
    $advertencias = $alertas['advertencias'] ?? [];
    $hayBloqueantes = count($bloqueantes) > 0;
    $hayAdvertencias = count($advertencias) > 0;
    $mensajes = array_merge($bloqueantes, $advertencias);
    $detalle = implode(' ', $mensajes);
@endphp

<div class="flex items-center justify-center">
    @if($hayBloqueantes)
        <button
            type="button"
            class="jalud-alerta-chip jalud-alerta-chip--bloqueante"
            aria-label="{{ $detalle }}"
            x-on:click.stop
            x-tooltip="{ content: @js($detalle), maxWidth: 380 }"
        >
            <x-filament::icon icon="heroicon-m-x-circle" />
            <span>{{ count($bloqueantes) }} {{ count($bloqueantes) === 1 ? 'bloqueo' : 'bloqueos' }}</span>
        </button>
    @elseif($hayAdvertencias)
        <button
            type="button"
            class="jalud-alerta-chip jalud-alerta-chip--advertencia"
            aria-label="{{ $detalle }}"
            x-on:click.stop
            x-tooltip="{ content: @js($detalle), maxWidth: 380 }"
        >
            <x-filament::icon icon="heroicon-m-exclamation-triangle" />
            <span>{{ count($advertencias) }} {{ count($advertencias) === 1 ? 'aviso' : 'avisos' }}</span>
        </button>
    @else
        <span class="jalud-alerta-chip jalud-alerta-chip--ok" aria-label="Sin alertas">
            <x-filament::icon icon="heroicon-m-check-circle" />
            <span>Sin alertas</span>
        </span>
    @endif
</div>
