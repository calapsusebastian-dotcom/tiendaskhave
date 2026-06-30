@props([
    'sidebar' => false,
    'href' => '/',
])

<a href="{{ $href }}" {{ $attributes->except('href') }} data-flux-sidebar-brand>
    <img src="{{ asset('images/Logo Tiendas 2024.png') }}" alt="Tiendas Kahvé" class="h-9 w-auto object-contain" />
</a>
