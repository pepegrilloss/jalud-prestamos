<div class="space-y-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Factura <strong>{{ $comprobante ?? 'N/A' }}</strong>
        #<strong>{{ $numero ?? 'N/A' }}</strong>
        de <strong>{{ $proveedor ?? 'N/A' }}</strong>
    </p>
    <div class="border-t border-b border-gray-200 dark:border-gray-700 py-3 space-y-2">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600 dark:text-gray-400">Subtotal Base</span>
            <span>S/ {{ number_format($subtotal, 2) }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-600 dark:text-gray-400">{{ $igvLabel }}</span>
            <span>S/ {{ number_format($igv, 2) }}</span>
        </div>
        <div class="flex justify-between text-base font-bold border-t border-gray-200 dark:border-gray-700 pt-2">
            <span>Total a pagar</span>
            <span>S/ {{ number_format($total, 2) }}</span>
        </div>
    </div>
</div>
