<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    /**
     * @var array<string, list<array{name: string, price: int}>>
     */
    private const MENUS = [
        'KOPI' => [
            ['name' => 'Americano', 'price' => 13000],
            ['name' => 'Matcha Milk', 'price' => 15000],
            ['name' => 'Hazelnut Latte', 'price' => 15000],
        ],
        'MAKANAN' => [
            ['name' => 'Nasi Katsu Mentai', 'price' => 13000],
            ['name' => 'Mie Goreng Spesial', 'price' => 12000],
        ],
        'SNACK' => [
            ['name' => 'Pisang Choco', 'price' => 10000],
            ['name' => 'Cireng', 'price' => 10000],
            ['name' => 'French Fries', 'price' => 12000],
        ],
        'NON KOPI' => [
            ['name' => 'Matcha', 'price' => 15000],
            ['name' => 'Mineral', 'price' => 5000],
        ],
    ];

    public function run(): void
    {
        foreach (self::MENUS as $categoryName => $menus) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($menus as $menu) {
                Menu::updateOrCreate(
                    ['slug' => Str::slug($menu['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $menu['name'],
                        'description' => '',
                        'price' => $menu['price'],
                        'is_available' => true,
                        'is_featured' => false,
                    ],
                );
            }
        }
    }
}
