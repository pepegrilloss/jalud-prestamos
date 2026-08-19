@php
    $esCumplimiento = filament()->getCurrentPanel()?->getId() === 'cumplimiento';
    $tituloArea = $esCumplimiento ? 'Cumplimiento SBS' : 'JALUD';
@endphp

<div class="jalud-login-wrapper {{ $esCumplimiento ? 'is-compliance' : 'is-core' }}">
    <section class="jalud-login-image-side" aria-label="{{ $tituloArea }}">
        <img
            src="{{ asset('images/login-jalud.webp') }}"
            alt="Reunión de trabajo de JALUD"
            class="jalud-bg-image"
            fetchpriority="high"
        >
        <div class="jalud-image-overlay" aria-hidden="true"></div>

        <div class="jalud-brand-content">
            <div class="jalud-brand-badge">
                <span aria-hidden="true"></span>
                {{ $esCumplimiento ? 'Gestión de cumplimiento' : 'Sistema de gestión' }}
            </div>

            <h1 class="jalud-brand-headline">
                Bienvenido a
                <strong>{{ $tituloArea }}</strong>
            </h1>

            <p class="jalud-brand-sub">
                @if ($esCumplimiento)
                    Prevención, control y trazabilidad para una gestión responsable.
                @else
                    Plataforma de préstamos y créditos.<br>
                    Gestiona tu cartera con confianza.
                @endif
            </p>
        </div>
    </section>

    <main class="jalud-login-form-side">
        <div class="jalud-panel-stack">
            <section class="jalud-form-card" aria-labelledby="jalud-login-title">
                <div class="jalud-logo-wrap">
                    <img src="{{ asset('logo.png') }}" alt="JALUD" class="jalud-logo">
                    @if ($esCumplimiento)
                        <span class="jalud-area-label">Cumplimiento SBS</span>
                    @endif
                </div>

                <header class="jalud-form-header">
                    <h2 id="jalud-login-title" class="jalud-form-title">Iniciar sesión</h2>
                    <p class="jalud-form-subtitle">
                        {{ $esCumplimiento
                            ? 'Ingresa tus credenciales para acceder al panel de cumplimiento.'
                            : 'Ingresa tus credenciales para acceder al sistema.' }}
                    </p>
                </header>

                <div class="jalud-fields-wrap">
                    <x-filament-panels::form wire:submit="authenticate">
                        {{ $this->form }}

                        <x-filament-panels::form.actions
                            :actions="$this->getCachedFormActions()"
                            :full-width="$this->hasFullWidthFormActions()"
                            class="jalud-actions-wrap"
                        />
                    </x-filament-panels::form>
                </div>
            </section>

            <footer class="jalud-form-footer">
                &copy; {{ date('Y') }} {{ $esCumplimiento ? 'JALUD Cumplimiento SBS' : 'JALUD Préstamos' }}.
                Todos los derechos reservados.
            </footer>
        </div>
    </main>
</div>
