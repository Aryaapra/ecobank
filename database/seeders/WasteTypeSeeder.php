<?php

namespace Database\Seeders;

use App\Models\WasteType;
use Illuminate\Database\Seeder;

class WasteTypeSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Plastik' => [
                'Botol plastik' => 6500,
                'Tutup botol' => 7200,
                'Gelas plastik' => 4800,
                'Plastik kemasan' => 3800,
                'Kantong plastik' => 2800,
            ],
            'Kertas' => [
                'Kardus' => 3800,
                'Kertas HVS' => 3500,
                'Koran' => 2900,
                'Majalah' => 2600,
                'Buku bekas' => 3000,
            ],
            'Logam' => [
                'Kaleng aluminium' => 8500,
                'Besi bekas' => 4200,
                'Tembaga' => 56000,
                'Baja ringan' => 3500,
            ],
            'Kaca' => [
                'Botol kaca bening' => 1800,
                'Botol kaca warna' => 1500,
                'Toples kaca' => 2000,
                'Pecahan kaca' => 800,
            ],
        ];

        foreach ($categories as $category => $items) {
            foreach ($items as $name => $price) {
                WasteType::firstOrCreate(
                    ['category' => $category, 'name' => $name],
                    ['price' => $price, 'is_active' => true],
                );
            }
        }
    }
}
