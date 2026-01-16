<div class="space-y-4">
    @if(empty($cuentas))
        <div class="rounded-lg bg-yellow-50 border-2 border-yellow-200 p-4">
            <p class="text-yellow-800 font-medium">No hay cuentas activas con saldo disponibles para refinanciar</p>
        </div>
    @else
        <div class="grid gap-3">
            @foreach($cuentas as $cuenta)
                <div class="rounded-lg border-2 border-blue-200 bg-blue-50 p-4 hover:shadow-md transition cursor-pointer"
                     onclick="seleccionarCuenta({{ $cuenta->ProposicionCreditoID }})">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-lg text-gray-800">{{ $cuenta->CodigoCredito }}</h4>
                        <span class="text-2xl font-bold text-blue-600">S/ {{ number_format($cuenta->SaldoPendiente, 2) }}</span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>Saldo Pendiente: <strong>S/ {{ number_format($cuenta->SaldoPendiente, 2) }}</strong></p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
