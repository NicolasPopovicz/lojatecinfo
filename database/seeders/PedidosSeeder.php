<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PedidosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->call('pedidos:popular', [
            '--total' => 1_000_000,
            '--lote'  => 2_000,
        ]);
    }
}
