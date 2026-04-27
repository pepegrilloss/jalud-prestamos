<div x-data x-init="
    $el.addEventListener('click', e => {
        let link = e.target.closest('a[href$=\'#balance-diario\']');
        if (link) {
            e.preventDefault();
            e.stopPropagation();
            $wire.abrirModal();
        }
    }, { capture: true })
" @click.window="
    let link = $event.target.closest('a[href$=\'#balance-diario\']');
    if (link) {
        $event.preventDefault();
        $event.stopPropagation();
        $wire.abrirModal();
    }
">
    {{-- Modal de Balance Diario (global) --}}
    <x-filament-actions::modals />
</div>
