@php $sedeActual = request()->query('sede', session('gerencia_dashboard_sede', '0')); @endphp

<style>
.gerencia-sede-filter {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.gerencia-sede-filter label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
}
.gerencia-sede-filter label svg {
    width: 18px;
    height: 18px;
    color: #a4cb3b;
}
.gerencia-sede-filter select {
    flex: 1;
    max-width: 300px;
    padding: 8px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #f9fafb;
    color: #111827;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.gerencia-sede-filter select:focus {
    outline: none;
    border-color: #a4cb3b;
    box-shadow: 0 0 0 3px rgba(164, 203, 59, 0.15);
    background: #fff;
}
</style>

<x-filament-panels::page>
    <div x-data="{ sede: '{{ $sedeActual }}' }" class="gerencia-sede-filter">
        <label>
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
            Ver sede:
        </label>
        <select x-model="sede" x-on:change="window.location.href = '/gerencia?sede=' + $event.target.value">
            <option value="0">Todas las Sedes</option>
            @foreach($this->getSedes() as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
    </div>

    @php $columns = $this->getColumns(); @endphp
    <x-filament::grid
        :default="$columns['default'] ?? 1"
        :sm="$columns['sm'] ?? null"
        :md="$columns['md'] ?? null"
        :lg="$columns['lg'] ?? 3"
        :xl="$columns['xl'] ?? null"
        :two-xl="$columns['2xl'] ?? null"
        class="gap-6"
    >
        @foreach ($this->getVisibleWidgets() as $widgetKey => $widget)
            @livewire($widget, key($widget . '-' . $sedeActual . '-' . $widgetKey))
        @endforeach
    </x-filament::grid>
</x-filament-panels::page>
