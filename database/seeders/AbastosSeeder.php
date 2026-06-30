<?php

namespace Database\Seeders;

use App\Models\MateriaPrima;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Proveedor;
use App\Models\Tienda;
use Illuminate\Database\Seeder;

class AbastosSeeder extends Seeder
{
    public function run(): void
    {
        // Tiendas
        $tiendas = [
            ['nombre' => 'Kahve Rosario',    'codigo' => 'KR', 'color' => '#E8642E', 'direccion' => 'Av. Rosario 120'],
            ['nombre' => 'Tienda Hotel',     'codigo' => 'TH', 'color' => '#2A9D8F', 'direccion' => 'Hotel Central, planta baja'],
            ['nombre' => 'Tienda Guadalupe', 'codigo' => 'TG', 'color' => '#7B61FF', 'direccion' => 'Calz. Guadalupe 45'],
        ];

        foreach ($tiendas as $data) {
            Tienda::firstOrCreate(['codigo' => $data['codigo']], $data);
        }

        // Proveedores
        $proveedores = [
            ['nombre' => 'Café del Valle',           'contacto' => 'María Soto',  'telefono' => '55 1234 5678', 'email' => 'ventas@cafedelvalle.mx',    'categoria' => 'Café y granos'],
            ['nombre' => 'Lácteos La Pradera',        'contacto' => 'Jorge Núñez', 'telefono' => '55 2345 6789', 'email' => 'pedidos@lapradera.mx',      'categoria' => 'Lácteos'],
            ['nombre' => 'Panificadora Trigo de Oro', 'contacto' => 'Ana Ruiz',   'telefono' => '55 3456 7890', 'email' => 'contacto@trigodeoro.mx',    'categoria' => 'Panadería'],
            ['nombre' => 'Frutas El Campo',           'contacto' => 'Luis Mena',  'telefono' => '55 4567 8901', 'email' => 'elcampo@frutas.mx',         'categoria' => 'Frutas y verduras'],
            ['nombre' => 'Insumos Aurora',            'contacto' => 'Carla Díaz', 'telefono' => '55 5678 9012', 'email' => 'hola@insumosaurora.mx',     'categoria' => 'Desechables y limpieza'],
        ];

        foreach ($proveedores as $data) {
            Proveedor::firstOrCreate(['nombre' => $data['nombre']], $data);
        }

        // Materias primas
        $p = Proveedor::orderBy('id')->pluck('id')->values();
        [$p1, $p2, $p3, $p4, $p5] = [$p[0], $p[1], $p[2], $p[3], $p[4]];

        $materiales = [
            ['nombre' => 'Café en grano arábica',     'unidad' => 'kg',  'precio' => 285, 'proveedor_id' => $p1],
            ['nombre' => 'Café en grano robusta',     'unidad' => 'kg',  'precio' => 210, 'proveedor_id' => $p1],
            ['nombre' => 'Café molido descafeinado',  'unidad' => 'kg',  'precio' => 240, 'proveedor_id' => $p1],
            ['nombre' => 'Leche entera',              'unidad' => 'L',   'precio' => 24,  'proveedor_id' => $p2],
            ['nombre' => 'Leche deslactosada',        'unidad' => 'L',   'precio' => 28,  'proveedor_id' => $p2],
            ['nombre' => 'Crema para batir',          'unidad' => 'L',   'precio' => 62,  'proveedor_id' => $p2],
            ['nombre' => 'Harina de trigo',           'unidad' => 'kg',  'precio' => 32,  'proveedor_id' => $p3],
            ['nombre' => 'Croissant congelado',       'unidad' => 'pza', 'precio' => 14,  'proveedor_id' => $p3],
            ['nombre' => 'Pan de caja artesanal',     'unidad' => 'pza', 'precio' => 48,  'proveedor_id' => $p3],
            ['nombre' => 'Naranja para jugo',         'unidad' => 'kg',  'precio' => 22,  'proveedor_id' => $p4],
            ['nombre' => 'Fresa',                     'unidad' => 'kg',  'precio' => 68,  'proveedor_id' => $p4],
            ['nombre' => 'Plátano',                   'unidad' => 'kg',  'precio' => 18,  'proveedor_id' => $p4],
            ['nombre' => 'Vasos 12oz (paq. 50)',      'unidad' => 'paq', 'precio' => 95,  'proveedor_id' => $p5],
            ['nombre' => 'Servilletas (paq. 500)',    'unidad' => 'paq', 'precio' => 42,  'proveedor_id' => $p5],
            ['nombre' => 'Jarabe de vainilla',        'unidad' => 'L',   'precio' => 88,  'proveedor_id' => $p5],
            ['nombre' => 'Bolsas kraft (paq. 100)',   'unidad' => 'paq', 'precio' => 56,  'proveedor_id' => $p5],
        ];

        foreach ($materiales as $data) {
            MateriaPrima::firstOrCreate(
                ['nombre' => $data['nombre'], 'proveedor_id' => $data['proveedor_id']],
                $data
            );
        }

        // Pedidos de ejemplo
        if (Pedido::count() > 0) {
            return;
        }

        $t = Tienda::orderBy('id')->pluck('id', 'codigo');
        $m = MateriaPrima::orderBy('id')->pluck('id')->values();

        $pedidosData = [
            ['folio' => 'PED-1035', 'tienda_id' => $t['TG'], 'proveedor_id' => $p1, 'estado' => 'recibido',   'created_at' => '2026-06-07', 'items' => [[$m[0], 8]]],
            ['folio' => 'PED-1036', 'tienda_id' => $t['KR'], 'proveedor_id' => $p3, 'estado' => 'recibido',   'created_at' => '2026-06-08', 'items' => [[$m[6], 15], [$m[7], 60]]],
            ['folio' => 'PED-1037', 'tienda_id' => $t['TH'], 'proveedor_id' => $p4, 'estado' => 'recibido',   'created_at' => '2026-06-10', 'items' => [[$m[9], 30], [$m[10], 8]]],
            ['folio' => 'PED-1038', 'tienda_id' => $t['KR'], 'proveedor_id' => $p5, 'estado' => 'enviado',    'created_at' => '2026-06-11', 'items' => [[$m[12], 12], [$m[14], 4]]],
            ['folio' => 'PED-1039', 'tienda_id' => $t['TG'], 'proveedor_id' => $p3, 'estado' => 'enviado',    'created_at' => '2026-06-12', 'items' => [[$m[7], 100], [$m[8], 20]]],
            ['folio' => 'PED-1040', 'tienda_id' => $t['TH'], 'proveedor_id' => $p2, 'estado' => 'pendiente',  'created_at' => '2026-06-13', 'items' => [[$m[3], 40], [$m[4], 12]]],
            ['folio' => 'PED-1041', 'tienda_id' => $t['TG'], 'proveedor_id' => $p2, 'estado' => 'por_aprobar','created_at' => '2026-06-14', 'items' => [[$m[3], 25], [$m[5], 4]]],
            ['folio' => 'PED-1042', 'tienda_id' => $t['KR'], 'proveedor_id' => $p1, 'estado' => 'por_aprobar','created_at' => '2026-06-15', 'items' => [[$m[0], 10], [$m[1], 5]]],
        ];

        foreach ($pedidosData as $data) {
            $items = $data['items'];
            unset($data['items']);

            $pedido = Pedido::create($data);

            foreach ($items as [$matId, $qty]) {
                $mat = MateriaPrima::find($matId);
                PedidoItem::create([
                    'pedido_id'       => $pedido->id,
                    'materia_prima_id' => $matId,
                    'cantidad'        => $qty,
                    'precio_unitario' => $mat->precio,
                ]);
            }
        }
    }
}
