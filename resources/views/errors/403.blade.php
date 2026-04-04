<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Acceso Denegado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-950 flex items-center justify-center p-6 text-center">
    <div class="max-w-md w-full animate-fade-in">
        <!-- Icono de Candado / Alerta -->
        <div class="mb-8 flex justify-center">
            <div class="relative">
                <div class="absolute inset-0 bg-red-500 rounded-full blur-2xl opacity-20 animate-pulse"></div>
                <div class="relative bg-white dark:bg-gray-900 border border-red-100 dark:border-red-900/30 p-5 rounded-3xl shadow-xl">
                    <svg class="w-16 h-16 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
            </div>
        </div>

        <h1 class="text-7xl font-black text-gray-900 dark:text-white mb-2 tracking-tighter">403</h1>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4 uppercase tracking-widest">Acceso Denegado</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-10 leading-relaxed max-w-sm mx-auto">
            Lo sentimos, no tienes los permisos necesarios para acceder a esta sección o realizar esta acción. Si crees que esto es un error, contacta al administrador.
        </p>

        <!-- Botón de acción -->
        <a href="/admin" class="group relative inline-flex items-center justify-center px-8 py-3.5 font-semibold text-white bg-[#a4cb3b] hover:bg-[#8eb032] rounded-2xl shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#a4cb3b]/50">
            <svg class="w-5 h-5 mr-2 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver al Panel
        </a>

        <!-- Mensaje sutil al pie -->
        <p class="mt-12 text-sm text-gray-400 dark:text-gray-500 italic">
            El sistema ha registrado este intento de acceso.
        </p>
    </div>

    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fade-in 0.6s ease-out forwards; }
    </style>

    <script>
        // Soporte para dark mode basado en el sistema si Filament lo usa
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>
