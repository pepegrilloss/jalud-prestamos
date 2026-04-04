<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 - Error de Servidor</title>
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
        <div class="mb-8 flex justify-center">
            <div class="relative">
                <div class="absolute inset-0 bg-amber-500 rounded-full blur-2xl opacity-20 animate-pulse"></div>
                <div class="relative bg-white dark:bg-gray-900 border border-amber-100 dark:border-amber-900/30 p-5 rounded-3xl shadow-xl">
                    <svg class="w-16 h-16 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
        </div>

        <h1 class="text-7xl font-black text-gray-900 dark:text-white mb-2 tracking-tighter">500</h1>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4 uppercase tracking-widest">Error Interno del Servidor</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-10 leading-relaxed max-w-sm mx-auto">
            Algo salió mal de nuestro lado. Estamos trabajando para solucionarlo. Intenta recargar la página en unos minutos.
        </p>

        <a href="/admin" class="group relative inline-flex items-center justify-center px-8 py-3.5 font-semibold text-white bg-[#a4cb3b] hover:bg-[#8eb032] rounded-2xl shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#a4cb3b]/50">
            <svg class="w-5 h-5 mr-2 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Intentar de Nuevo
        </a>
    </div>

    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fade-in 0.6s ease-out forwards; }
    </style>

    <script>
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>
