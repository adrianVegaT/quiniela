<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Quiniela') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-950 text-zinc-200 flex items-center justify-center min-h-screen">
        <div class="text-center">
            <div class="w-14 h-14 rounded-2xl bg-accent/10 flex items-center justify-center mb-4 mx-auto">
                <svg class="w-7 h-7 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-4.5A6.75 6.75 0 009.75 7.5h0A6.75 6.75 0 003 14.25v4.5"/></svg>
            </div>
            <h1 class="text-xl font-bold text-white mb-2">{{ config('app.name', 'Quiniela') }}</h1>
            <p class="text-zinc-400 text-sm mb-6">Predicciones deportivas</p>
            <div class="flex gap-3 justify-center">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center px-6 py-2.5 bg-accent hover:bg-accent-dark text-white text-sm font-semibold rounded-xl transition">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-6 py-2.5 bg-accent hover:bg-accent-dark text-white text-sm font-semibold rounded-xl transition">Iniciar sesión</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-2.5 border border-zinc-700 hover:border-zinc-600 text-zinc-300 text-sm font-medium rounded-xl transition">Registrarse</a>
                    @endif
                @endauth
            </div>
        </div>
    </body>
</html>
