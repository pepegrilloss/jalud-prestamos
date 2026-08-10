<style>
    .jalud-alerta-icono {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        cursor: help;
        flex-shrink: 0;
        vertical-align: middle;
    }
    .jalud-alerta-icono svg {
        width: 15px;
        height: 15px;
    }
    .jalud-alerta-bloqueante {
        background-color: #fde8e8;
        border: 1px solid #f5b5b5;
        color: #c81e1e;
    }
    .jalud-alerta-advertencia {
        background-color: #fef9e7;
        border: 1px solid #f7dc6f;
        color: #b7950b;
    }
    .jalud-alerta-ok {
        background-color: #eafaf1;
        border: 1px solid #82e0aa;
        color: #1e8449;
    }
</style>

<div style="display:flex; align-items:center; gap:6px;">
    @php
        $record = $getRecord();
        $alertas = app(\App\Services\ProposicionAprobacionValidatorService::class)->obtenerAlertas($record);
        $bloqueantes = $alertas['bloqueantes'] ?? [];
        $advertencias = $alertas['advertencias'] ?? [];
        $tooltip = implode("\n", array_merge($bloqueantes, $advertencias));
        $hayBloqueantes = count($bloqueantes) > 0;
        $hayAdvertencias = count($advertencias) > 0;
    @endphp

    @if($hayBloqueantes)
        <span class="jalud-alerta-icono jalud-alerta-bloqueante" title="{{ $tooltip }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
        </span>
    @endif

    @if($hayAdvertencias)
        <span class="jalud-alerta-icono jalud-alerta-advertencia" title="{{ $tooltip }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
        </span>
    @endif

    @if(! $hayBloqueantes && ! $hayAdvertencias)
        <span class="jalud-alerta-icono jalud-alerta-ok" title="Sin alertas">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
        </span>
    @endif
</div>
