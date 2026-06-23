<div x-data x-init="
    $el.addEventListener('click', e => {
        let link = e.target.closest('a[href$=\'#reporte-creditos\']');
        if (link) {
            e.preventDefault();
            e.stopPropagation();
            $wire.abrirModal();
        }
    }, { capture: true })
" @click.window="
    let link = $event.target.closest('a[href$=\'#reporte-creditos\']');
    if (link) {
        $event.preventDefault();
        $event.stopPropagation();
        $wire.abrirModal();
    }
">
    {{-- Modal de Reporte de Creditos (global) --}}
    <x-filament-actions::modals />
</div>
