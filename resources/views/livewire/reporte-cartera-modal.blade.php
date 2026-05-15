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
            margin-bottom: 10px !important;
        }

        .cartera-checkbox-list label.fi-fo-checkbox-list-option-label {
            display: flex !important;
            align-items: flex-start !important;
            padding: 14px !important;
            border: 2px solid #f3f4f6 !important;
            border-radius: 14px !important;
            background-color: #ffffff !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
            cursor: pointer !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            width: 100% !important;
        }

        .cartera-checkbox-list label.fi-fo-checkbox-list-option-label:hover {
            border-color: #3b82f6 !important;
            background-color: #f0f7ff !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1), 0 4px 6px -2px rgba(59, 130, 246, 0.05) !important;
        }

        /* Contenedor del checkbox */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label input[type="checkbox"] {
            margin-top: 4px !important;
            margin-right: 14px !important;
            border-radius: 6px !important;
            border: 2px solid #d1d5db !important;
            width: 20px !important;
            height: 20px !important;
            color: #3b82f6 !important;
            transition: all 0.2s ease !important;
        }

        /* Cuando está marcado */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label:has(input:checked) {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.1) !important;
        }

        /* Texto principal */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label .fi-fo-checkbox-list-option-label-text {
            font-size: 1rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            line-height: 1.2 !important;
            margin-bottom: 2px !important;
        }

        /* Descripción */
        .cartera-checkbox-list .fi-fo-checkbox-list-option-label .fi-fo-checkbox-list-option-description {
            font-size: 0.875rem !important;
            color: #6b7280 !important;
            line-height: 1.4 !important;
        }

        /* Animación para el check */
        .cartera-checkbox-list input:checked {
            animation: checkbox-pop 0.3s ease-out !important;
        }

        @keyframes checkbox-pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
    </style>

    {{-- Modal de Reporte de Cartera (global) --}}
    <x-filament-actions::modals />
</div>
