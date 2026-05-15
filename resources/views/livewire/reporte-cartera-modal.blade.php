<div x-data x-init="
    $el.addEventListener('click', e => {
        let link = e.target.closest('a[href$=\'#reporte-cartera\']');
        if (link) {
            e.preventDefault();
            e.stopPropagation();
            $wire.abrirModal();
        }
    }, { capture: true })
" @click.window="
    let link = $event.target.closest('a[href$=\'#reporte-cartera\']');
    if (link) {
        $event.preventDefault();
        $event.stopPropagation();
        $wire.abrirModal();
    }
">
    <style>
        .cartera-checkbox-list .fi-fo-checkbox-list-option {
            margin-bottom: 12px !important;
            position: relative !important;
        }

        .cartera-checkbox-list label.fi-fo-checkbox-list-option-label {
            display: flex !important;
            align-items: flex-start !important;
            padding: 16px 16px 16px 24px !important; /* Más padding a la izquierda para el indicador */
            border: 2px solid #f3f4f6 !important;
            border-radius: 14px !important;
            background-color: #ffffff !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
            width: 100% !important;
            position: relative !important;
            overflow: hidden !important;
        }

        /* Indicador lateral de color (Status Bar) */
        .cartera-checkbox-list label.fi-fo-checkbox-list-option-label::before {
            content: "" !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 6px !important;
            transition: width 0.2s ease !important;
        }

        /* Colores específicos para cada tipo de cartera */
        .cartera-checkbox-list .fi-fo-checkbox-list-option:nth-child(1) label::before { background-color: #22c55e !important; } /* No Vencida - Verde */
        .cartera-checkbox-list .fi-fo-checkbox-list-option:nth-child(2) label::before { background-color: #f59e0b !important; } /* Vencida - Naranja */
        .cartera-checkbox-list .fi-fo-checkbox-list-option:nth-child(3) label::before { background-color: #ef4444 !important; } /* Morosa - Rojo */
        .cartera-checkbox-list .fi-fo-checkbox-list-option:nth-child(4) label::before { background-color: #7f1d1d !important; } /* Pesada - Bordó */

        .cartera-checkbox-list label.fi-fo-checkbox-list-option-label:hover {
            border-color: #9cd333 !important;
            background-color: #f7fee7 !important;
            transform: translateX(4px) !important;
            box-shadow: 0 4px 12px rgba(156, 211, 51, 0.1) !important;
        }

        .cartera-checkbox-list label.fi-fo-checkbox-list-option-label:hover::before {
            width: 10px !important;
        }

        /* Contenedor del checkbox */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label input[type="checkbox"] {
            margin-top: 2px !important;
            margin-right: 16px !important;
            border-radius: 6px !important;
            border: 2px solid #d1d5db !important;
            width: 22px !important;
            height: 22px !important;
            color: #9cd333 !important;
            transition: all 0.2s ease !important;
            flex-shrink: 0 !important;
        }

        /* Cuando está marcado */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label:has(input:checked) {
            border-color: #9cd333 !important;
            background-color: #f0fdf4 !important;
        }

        /* Texto principal */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label .fi-fo-checkbox-list-option-label-text {
            font-size: 1.05rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            line-height: 1.2 !important;
            margin-bottom: 4px !important;
        }

        /* Descripción */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label .fi-fo-checkbox-list-option-description {
            font-size: 0.9rem !important;
            color: #4b5563 !important;
            line-height: 1.4 !important;
        }

        /* Animación para el check */
        .cartera-checkbox-list input:checked {
            animation: checkbox-pop 0.3s ease-out !important;
        }

        @keyframes checkbox-pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
    </style>

    {{-- Modal de Reporte de Cartera (global) --}}
    <x-filament-actions::modals />
</div>
