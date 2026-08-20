<div class="overflow-x-auto">
    <table class="w-full text-sm border-collapse">
        <thead>
            <tr class="bg-gray-100 dark:bg-gray-800 text-left">
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">#</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Fecha</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Zona Anterior</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Zona Nueva</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Promotor Anterior</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Promotor Nuevo</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Motivo</th>
                <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 border-b dark:border-gray-600">Ejecutado por</th>
            </tr>
        </thead>
        <tbody>
            @foreach($traspasos as $index => $traspaso)
                <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $index + 1 }}</td>
                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                        {{ $traspaso->FechaTraspaso->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                            {{ $traspaso->zonaAnterior?->Nombre ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                            {{ $traspaso->zonaNueva?->Nombre ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs">
                        {{ $traspaso->promotorAnterior?->Descripcion ?? '—' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs">
                        {{ $traspaso->promotorNuevo?->Descripcion ?? '—' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs max-w-xs truncate" title="{{ $traspaso->MotivoTraspaso }}">
                        {{ Str::limit($traspaso->MotivoTraspaso, 60) }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs whitespace-nowrap">
                        {{ $traspaso->userSolicita?->name ?? 'N/A' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
