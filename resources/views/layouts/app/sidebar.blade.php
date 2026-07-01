<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <style>
            /* Sidebar compacto */
            [data-flux-sidebar-item] { height: 1.85rem !important; }
            [data-flux-sidebar-group] > div:first-child { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
        </style>
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 w-52!">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('abastos.dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @php $user = auth()->user(); @endphp

                @if($user->hasAnyRole(['admin','logistica','logistica.pedidos','logistica.materias','logistica.proveedores']))
                <flux:sidebar.group heading="Logística" expandable :expanded="request()->routeIs('abastos.*')" class="grid">
                    <flux:sidebar.item icon="chart-bar" :href="route('abastos.dashboard')" :current="request()->routeIs('abastos.dashboard')" wire:navigate>
                        Dashboard
                    </flux:sidebar.item>

                    @if($user->hasAnyRole(['admin','logistica','logistica.pedidos']))
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('abastos.pedidos.index')" :current="request()->routeIs('abastos.pedidos.index')" wire:navigate>
                        Pedidos
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="plus-circle" :href="route('abastos.pedidos.crear')" :current="request()->routeIs('abastos.pedidos.crear')" wire:navigate>
                        Crear pedido
                    </flux:sidebar.item>
                    @endif

                    @if($user->hasAnyRole(['admin','logistica','logistica.materias']))
                    <flux:sidebar.item icon="cube" :href="route('abastos.materias-primas')" :current="request()->routeIs('abastos.materias-primas')" wire:navigate>
                        Materias primas
                    </flux:sidebar.item>
                    @endif

                    @if($user->hasAnyRole(['admin','logistica','logistica.proveedores']))
                    <flux:sidebar.item icon="building-storefront" :href="route('abastos.proveedores')" :current="request()->routeIs('abastos.proveedores')" wire:navigate>
                        Proveedores
                    </flux:sidebar.item>
                    @endif

                </flux:sidebar.group>
                @endif

                @if($user->hasAnyRole(['admin','inventarios']))
                <flux:sidebar.group heading="Inventarios" expandable :expanded="request()->routeIs('abastos.inventarios.*')" class="grid">
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('abastos.inventarios.index')" :current="request()->routeIs('abastos.inventarios.*')" wire:navigate>
                        Inventarios
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                @if($user->hasAnyRole(['admin','comandas','comandas.mesas','comandas.historial','comandas.carta']))
                <flux:sidebar.group heading="Comandas" expandable :expanded="request()->routeIs('comandas.*')" class="grid">
                    @if($user->hasAnyRole(['admin','comandas','comandas.mesas']))
                    <flux:sidebar.item icon="squares-2x2" :href="route('comandas.mesas')" :current="request()->routeIs('comandas.mesas') || request()->routeIs('comandas.tomar')" wire:navigate>
                        Mesas
                    </flux:sidebar.item>
                    @endif

                    @if($user->hasAnyRole(['admin','comandas','comandas.carta']))
                    <flux:sidebar.item icon="book-open" :href="route('comandas.carta')" :current="request()->routeIs('comandas.carta')" wire:navigate>
                        Carta
                    </flux:sidebar.item>
                    @endif

                    @if($user->hasAnyRole(['admin','comandas','comandas.historial']))
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('comandas.historial')" :current="request()->routeIs('comandas.historial')" wire:navigate>
                        Historial
                    </flux:sidebar.item>
                    @endif

                </flux:sidebar.group>
                @endif

                @if($user->hasAnyRole(['admin','traslados']))
                <flux:sidebar.group heading="Traslados" expandable :expanded="request()->routeIs('traslados.*')" class="grid">
                    <flux:sidebar.item icon="arrow-right-circle" :href="route('traslados.index')" :current="request()->routeIs('traslados.index') || request()->routeIs('traslados.ver')" wire:navigate>
                        Traslados
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="plus-circle" :href="route('traslados.crear')" :current="request()->routeIs('traslados.crear')" wire:navigate>
                        Nuevo traslado
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif

                @if($user->hasRole('admin'))
                <flux:sidebar.group heading="Administración" expandable :expanded="request()->routeIs('abastos.usuarios')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('abastos.usuarios')" :current="request()->routeIs('abastos.usuarios')" wire:navigate>
                        Usuarios
                    </flux:sidebar.item>
                </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        <script src="{{ asset('js/qz-tray.js') }}" data-navigate-once></script>
    </body>
</html>
