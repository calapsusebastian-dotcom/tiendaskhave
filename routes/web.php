<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('abastos.dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    return redirect()->route('abastos.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

Route::prefix('abastos')
    ->middleware(['auth', 'verified'])
    ->name('abastos.')
    ->group(function () {
        // Dashboard — accesible para cualquier rol de logística o admin
        Route::livewire('/', 'pages::abastos.dashboard')
            ->middleware('role:admin|logistica|logistica.pedidos|logistica.materias|logistica.proveedores')
            ->name('dashboard');

        // Pedidos
        Route::livewire('/pedidos', 'pages::abastos.pedidos.index')
            ->middleware('role:admin|logistica|logistica.pedidos')
            ->name('pedidos.index');
        Route::livewire('/pedidos/crear', 'pages::abastos.pedidos.crear')
            ->middleware('role:admin|logistica|logistica.pedidos')
            ->name('pedidos.crear');
        Route::livewire('/pedidos/{pedido}/editar', 'pages::abastos.pedidos.editar')
            ->middleware('role:admin|logistica|logistica.pedidos')
            ->name('pedidos.editar');
        Route::get('/pedidos/{pedido}/imprimir', function (\App\Models\Pedido $pedido) {
            $pedido->load(['tienda', 'proveedor', 'items.materiaPrima']);
            return view('pages.abastos.pedidos.imprimir', compact('pedido'));
        })->middleware('role:admin|logistica|logistica.pedidos')->name('pedidos.imprimir');

        // Materias primas
        Route::livewire('/materias-primas', 'pages::abastos.materias-primas')
            ->middleware('role:admin|logistica|logistica.materias')
            ->name('materias-primas');

        // Proveedores
        Route::livewire('/proveedores', 'pages::abastos.proveedores')
            ->middleware('role:admin|logistica|logistica.proveedores')
            ->name('proveedores');

        // Inventarios
        Route::livewire('/inventarios', 'pages::abastos.inventarios.index')
            ->middleware('role:admin|inventarios')
            ->name('inventarios.index');
        Route::livewire('/inventarios/{inventario}/diligenciar', 'pages::abastos.inventarios.diligenciar')
            ->middleware('role:admin|inventarios')
            ->name('inventarios.diligenciar');
        Route::livewire('/inventarios/{inventario}/verificar', 'pages::abastos.inventarios.verificar')
            ->middleware('role:admin|inventarios')
            ->name('inventarios.verificar');

        // Usuarios (solo admin)
        Route::livewire('/usuarios', 'pages::abastos.usuarios')
            ->middleware('role:admin')
            ->name('usuarios');
    });

// Comandas
Route::prefix('comandas')
    ->middleware(['auth', 'verified'])
    ->name('comandas.')
    ->group(function () {
        Route::livewire('/mesas', 'pages::comandas.mesas')
            ->middleware('role:admin|comandas|comandas.mesas')
            ->name('mesas');
        Route::livewire('/mesas/{mesa}/tomar', 'pages::comandas.tomar')
            ->middleware('role:admin|comandas|comandas.mesas')
            ->name('tomar');
        Route::livewire('/carta', 'pages::comandas.carta')
            ->middleware('role:admin|comandas|comandas.carta')
            ->name('carta');
        Route::livewire('/historial', 'pages::comandas.historial')
            ->middleware('role:admin|comandas|comandas.historial')
            ->name('historial');
        Route::get('/{comanda}/imprimir', function (\App\Models\Comanda $comanda) {
            $comanda->load(['mesa.tienda', 'tienda', 'items.productoMenu', 'mesero']);
            return view('pages.comandas.imprimir', compact('comanda'));
        })->middleware('role:admin|comandas|comandas.mesas')->name('imprimir');
    });

// Traslados
Route::prefix('traslados')
    ->middleware(['auth', 'verified'])
    ->name('traslados.')
    ->group(function () {
        Route::livewire('/', 'pages::traslados.index')
            ->middleware('role:admin|traslados')
            ->name('index');
        Route::livewire('/crear', 'pages::traslados.crear')
            ->middleware('role:admin|traslados')
            ->name('crear');
        Route::livewire('/{traslado}', 'pages::traslados.ver')
            ->middleware('role:admin|traslados')
            ->name('ver');
    });


require __DIR__.'/settings.php';
