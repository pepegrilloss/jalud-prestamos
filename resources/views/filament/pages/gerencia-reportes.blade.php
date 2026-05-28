<style>
.reportes-page {
    font-family: 'Ubuntu', sans-serif;
}

.reportes-page .sede-filter {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 18px 24px;
    margin-top: 20px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.reportes-page .sede-filter label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
}

.reportes-page .sede-filter select {
    flex: 1;
    max-width: 320px;
    padding: 9px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #f9fafb;
    color: #111827;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.reportes-page .sede-filter select:focus {
    outline: none;
    border-color: #a4cb3b;
    box-shadow: 0 0 0 3px rgba(164, 203, 59, 0.15);
    background: #fff;
}

.reportes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px;
    margin-bottom: 20px;
}

@media (max-width: 1024px) {
    .reportes-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .reportes-grid { grid-template-columns: 1fr; }
}

.reporte-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s;
}

.reporte-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.reporte-card .card-bar {
    height: 5px;
}

.reporte-card .card-body {
    padding: 22px 24px 24px;
}

.reporte-card .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.reporte-card .card-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.reporte-card .card-icon svg {
    width: 20px;
    height: 20px;
}

.reporte-card .card-title {
    font-size: 15px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.reporte-card .card-desc {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
    margin: 0 0 16px;
}

.reporte-card .field-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
    margin-bottom: 6px;
}

.reporte-card .field-input {
    display: block;
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #f9fafb;
    color: #111827;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    box-sizing: border-box;
}

.reporte-card .field-input:focus {
    outline: none;
    border-color: #a4cb3b;
    box-shadow: 0 0 0 3px rgba(164, 203, 59, 0.15);
    background: #fff;
}

.reporte-card .field-group {
    margin-bottom: 12px;
}

.reporte-card .field-group:last-child {
    margin-bottom: 0;
}

.reporte-card .field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.reporte-card .field-row .field-group {
    margin-bottom: 0;
}

.reporte-card .checkbox-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

.reporte-card .checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    color: #374151;
    cursor: pointer;
    transition: background 0.15s;
}

.reporte-card .checkbox-item:hover {
    background: #f9fafb;
}

.reporte-card .checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #a4cb3b;
    margin: 0;
    flex-shrink: 0;
}

.card-actions {
    display: flex;
    gap: 8px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #f3f4f6;
}

.btn-pdf {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #a4cb3b;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    font-family: inherit;
}

.btn-pdf:hover {
    background: #8fb832;
}

.btn-pdf svg {
    width: 15px;
    height: 15px;
}

.btn-excel {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #059669;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    font-family: inherit;
}

.btn-excel:hover {
    background: #047857;
}

.btn-excel svg {
    width: 15px;
    height: 15px;
}
</style>

<div x-data="{
    sedeId: '0',
    fechaDiario: '{{ now()->format('Y-m-d') }}',
    fechaCanceladas: '{{ now()->format('Y-m-d') }}',
    fechaCartera: '{{ now()->format('Y-m-d') }}',
    carteraTipos: [],
    fechaVencidos: '{{ now()->format('Y-m-d') }}',
    fechaAtraso: '{{ now()->format('Y-m-d') }}',
    fechaInactivos: '{{ now()->format('Y-m-d') }}',

    toggleTipo(tipo) {
        if (this.carteraTipos.includes(tipo)) {
            this.carteraTipos = this.carteraTipos.filter(t => t !== tipo);
        } else {
            this.carteraTipos.push(tipo);
        }
    },
    openReport(url) { window.open(url, '_blank'); }
}" class="reportes-page">

    <div class="sede-filter">
        <label>Filtrar por Sede</label>
        <select x-model="sedeId">
            <option value="0">Todas las Sedes</option>
            @foreach($sedes as $id => $nombre)
                <option value="{{ $id }}">{{ $nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="reportes-grid">

        {{-- Balance Diario --}}
        <div class="reporte-card">
            <div class="card-bar" style="background:#a4cb3b;"></div>
            <div class="card-body">
                <div class="card-header">
                    <div class="card-icon" style="background:#f0f7df;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#a4cb3b"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    </div>
                    <h3 class="card-title">Balance Diario</h3>
                </div>
                <p class="card-desc">Amortizaciones, créditos emitidos, remesas y movimiento de caja chica del día.</p>
                <div class="field-group">
                    <label class="field-label">Fecha</label>
                    <input type="date" x-model="fechaDiario" class="field-input">
                </div>
                <div class="card-actions">
                    <button type="button" class="btn-pdf" x-on:click="openReport('{{ route('reporte-diario.pdf') }}?sede_id=' + sedeId + '&fecha=' + fechaDiario)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        PDF
                    </button>
                    <button type="button" class="btn-excel" x-on:click="openReport('{{ route('reporte-diario.excel') }}?sede_id=' + sedeId + '&fecha=' + fechaDiario)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        {{-- Cuentas Canceladas --}}
        <div class="reporte-card">
            <div class="card-bar" style="background:#059669;"></div>
            <div class="card-body">
                <div class="card-header">
                    <div class="card-icon" style="background:#d1fae5;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#059669"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <h3 class="card-title">Cuentas Canceladas</h3>
                </div>
                <p class="card-desc">Créditos que fueron saldados completamente durante el día seleccionado.</p>
                <div class="field-group">
                    <label class="field-label">Fecha</label>
                    <input type="date" x-model="fechaCanceladas" class="field-input">
                </div>
                <div class="card-actions">
                    <button type="button" class="btn-pdf" x-on:click="openReport('{{ route('cuentas-canceladas.view') }}?sede_id=' + sedeId + '&fecha=' + fechaCanceladas)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        PDF
                    </button>
                    <button type="button" class="btn-excel" x-on:click="openReport('{{ route('reporte-canceladas.excel') }}?sede_id=' + sedeId + '&fecha=' + fechaCanceladas)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        {{-- Reporte de Cartera --}}
        <div class="reporte-card">
            <div class="card-bar" style="background:#d97706;"></div>
            <div class="card-body">
                <div class="card-header">
                    <div class="card-icon" style="background:#fef3c7;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#d97706"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    </div>
                    <h3 class="card-title">Reporte de Cartera</h3>
                </div>
                <p class="card-desc">Clasificación de cartera: no vencida, vencida, morosa y pesada.</p>
                <div class="field-group">
                    <label class="field-label">Fecha de Corte</label>
                    <input type="date" x-model="fechaCartera" class="field-input">
                </div>
                <div class="field-group">
                    <label class="field-label">Tipos de Cartera</label>
                    <div class="checkbox-grid">
                        <label class="checkbox-item">
                            <input type="checkbox" x-on:change="toggleTipo('no_vencida')"> No Vencida
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" x-on:change="toggleTipo('vencida')"> Vencida
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" x-on:change="toggleTipo('morosa')"> Morosa
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" x-on:change="toggleTipo('pesada')"> Pesada
                        </label>
                    </div>
                </div>
                <div class="card-actions">
                    <button type="button" class="btn-pdf"
                        x-on:click="if(carteraTipos.length === 0) { alert('Seleccione al menos un tipo de cartera'); return; } openReport('{{ route('reporte-cartera.pdf') }}?sede_id=' + sedeId + '&fecha=' + fechaCartera + '&tipos=' + carteraTipos.join(','))">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        PDF
                    </button>
                    <button type="button" class="btn-excel"
                        x-on:click="if(carteraTipos.length === 0) { alert('Seleccione al menos un tipo de cartera'); return; } openReport('{{ route('reporte-cartera.excel') }}?sede_id=' + sedeId + '&fecha=' + fechaCartera + '&tipos=' + carteraTipos.join(','))">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        {{-- Créditos Vencidos --}}
        <div class="reporte-card">
            <div class="card-bar" style="background:#dc2626;"></div>
            <div class="card-body">
                <div class="card-header">
                    <div class="card-icon" style="background:#fee2e2;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#dc2626"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <h3 class="card-title">Créditos Vencidos</h3>
                </div>
                <p class="card-desc">Créditos con cuotas vencidas en la fecha seleccionada.</p>
                <div class="field-group">
                    <label class="field-label">Fecha</label>
                    <input type="date" x-model="fechaVencidos" class="field-input">
                </div>
                <div class="card-actions">
                    <button type="button" class="btn-pdf" x-on:click="openReport('{{ route('creditos-vencidos.view') }}?sede_id=' + sedeId + '&fecha_desde=' + fechaVencidos + '&fecha_hasta=' + fechaVencidos)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        PDF
                    </button>
                    <button type="button" class="btn-excel" x-on:click="openReport('{{ route('reporte-vencidos.excel') }}?sede_id=' + sedeId + '&fecha_desde=' + fechaVencidos + '&fecha_hasta=' + fechaVencidos)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        {{-- Clientes con Atraso --}}
        <div class="reporte-card">
            <div class="card-bar" style="background:#e11d48;"></div>
            <div class="card-body">
                <div class="card-header">
                    <div class="card-icon" style="background:#ffe4e6;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#e11d48"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <h3 class="card-title">Clientes con Atraso</h3>
                </div>
                <p class="card-desc">Clientes con pagos atrasados en la fecha seleccionada.</p>
                <div class="field-group">
                    <label class="field-label">Fecha</label>
                    <input type="date" x-model="fechaAtraso" class="field-input">
                </div>
                <div class="card-actions">
                    <button type="button" class="btn-pdf" x-on:click="openReport('{{ route('clientes-atraso.view') }}?sede_id=' + sedeId + '&fecha_desde=' + fechaAtraso + '&fecha_hasta=' + fechaAtraso)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        PDF
                    </button>
                    <button type="button" class="btn-excel" x-on:click="openReport('{{ route('reporte-atraso.excel') }}?sede_id=' + sedeId + '&fecha_desde=' + fechaAtraso + '&fecha_hasta=' + fechaAtraso)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

        {{-- Clientes Inactivos --}}
        <div class="reporte-card">
            <div class="card-bar" style="background:#9ca3af;"></div>
            <div class="card-body">
                <div class="card-header">
                    <div class="card-icon" style="background:#f3f4f6;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#6b7280"><path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/></svg>
                    </div>
                    <h3 class="card-title">Clientes Inactivos</h3>
                </div>
                <p class="card-desc">Clientes que no han renovado crédito después de su último saldo.</p>
                <div class="field-group">
                    <label class="field-label">Fecha</label>
                    <input type="date" x-model="fechaInactivos" class="field-input">
                </div>
                <div class="card-actions">
                    <button type="button" class="btn-pdf" x-on:click="openReport('{{ route('clientes-inactivos.view') }}?sede_id=' + sedeId + '&fecha_desde=' + fechaInactivos + '&fecha_hasta=' + fechaInactivos)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        PDF
                    </button>
                    <button type="button" class="btn-excel" x-on:click="openReport('{{ route('reporte-inactivos.excel') }}?sede_id=' + sedeId + '&fecha_desde=' + fechaInactivos + '&fecha_hasta=' + fechaInactivos)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Excel
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
