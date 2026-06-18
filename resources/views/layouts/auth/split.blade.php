<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-200">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col p-10 text-white lg:flex bg-zinc-900 border-e border-zinc-800">
                <div class="relative z-20 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-accent/10 flex items-center justify-center">
                        <x-app-logo-icon class="size-5 text-accent" />
                    </div>
                    <span class="text-lg font-semibold">{{ config('app.name', 'Quiniela') }}</span>
                </div>

                <div class="relative z-20 my-auto text-center px-8">
                    <div class="w-20 h-20 rounded-3xl bg-accent/10 flex items-center justify-center mb-8 mx-auto">
                        <svg class="w-10 h-10 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-4.5A6.75 6.75 0 009.75 7.5h0A6.75 6.75 0 003 14.25v4.5"/></svg>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-3">{{ config('app.name', 'Quiniela') }}</h1>
                    <p class="text-zinc-400 text-lg mb-8">Crea quinielas, invita amigos y compite con predicciones deportivas</p>
                    <div class="grid grid-cols-3 gap-6">
                        <div>
                            <p class="text-2xl font-bold text-accent">&#9873;</p>
                            <p class="text-xs text-zinc-500 mt-1">Predice marcadores</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-accent">&#127942;</p>
                            <p class="text-xs text-zinc-500 mt-1">Compite con amigos</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-accent">&#128202;</p>
                            <p class="text-xs text-zinc-500 mt-1">Tablas en vivo</p>
                        </div>
                    </div>
                </div>

                @php
                    [$message, $author] = str(Illuminate\Foundation\Inspiring::quotes()->random())->explode('-');
                @endphp

                <div class="relative z-20">
                    <blockquote class="space-y-2">
                        <flux:heading size="lg">&ldquo;{{ trim($message) }}&rdquo;</flux:heading>
                        <footer><flux:heading>{{ trim($author) }}</flux:heading></footer>
                    </blockquote>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-accent" />
                        </span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
