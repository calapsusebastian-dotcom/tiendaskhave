<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin',
            'logistica',
            'logistica.pedidos',
            'logistica.materias',
            'logistica.proveedores',
            'inventarios',
            'comandas',
            'comandas.mesas',
            'comandas.historial',
            'comandas.carta',
            'traslados',
            'traslados.imov',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Asignar admin al primer usuario registrado
        $admin = User::first();
        if ($admin && ! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
