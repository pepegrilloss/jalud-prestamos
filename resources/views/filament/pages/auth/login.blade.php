<div class="jalud-login-wrapper">

    {{-- ══════════════════════════════════
    IZQUIERDA: Imagen / Branding
    ══════════════════════════════════ --}}
    <div class="jalud-login-image-side">

        <div class="jalud-image-overlay"></div>

        <img src="{{ asset('JALUD.png') }}" alt="Fondo JALUD" class="jalud-bg-image"
            onerror="this.src='https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'">

        <div class="jalud-geo-circle jalud-geo-1"></div>
        <div class="jalud-geo-circle jalud-geo-2"></div>
        <div class="jalud-geo-circle jalud-geo-3"></div>

        <div class="jalud-brand-content">
            <div class="jalud-brand-badge">Sistema de Gestión</div>
            <h2 class="jalud-brand-headline">
                Bienvenido a
                <span>JALUD</span>
            </h2>
            <p class="jalud-brand-sub">
                Plataforma de préstamos y créditos.<br>
                Gestiona tu cartera con confianza.
            </p>
            <div class="jalud-brand-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════
    DERECHA: Formulario
    ══════════════════════════════════ --}}
    <div class="jalud-login-form-side">

        <div class="jalud-particle jalud-p1"></div>
        <div class="jalud-particle jalud-p2"></div>
        <div class="jalud-particle jalud-p3"></div>

        <div class="jalud-form-container">

            <div class="jalud-logo-wrap">
                <img src="{{ asset('logo.png') }}" alt="Logo JALUD" class="jalud-logo"
                    onerror="this.src='https://ui-avatars.com/api/?name=JALUD&background=4f7942&color=fff&size=120'">
            </div>

            <div class="jalud-form-header">
                <h1 class="jalud-form-title">Iniciar Sesión</h1>
                <p class="jalud-form-subtitle">Ingresa tus credenciales para acceder al sistema</p>
            </div>

            <div class="jalud-divider">
                <span class="jalud-divider-line"></span>
                <span class="jalud-divider-icon">🔐</span>
                <span class="jalud-divider-line"></span>
            </div>
            
            <div class="jalud-fields-wrap">
                <x-filament-panels::form wire:submit="authenticate">
                    {{ $this->form }}
                    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()" class="jalud-actions-wrap" />
                </x-filament-panels::form>
            </div>

            <div class="jalud-form-footer">
                <p>&copy; {{ date('Y') }} jalud-prestamos. Todos los derechos reservados.</p>
            </div>

        </div>
    </div>

</div>