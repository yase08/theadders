<?php

namespace Database\Seeders;

use App\Models\CategorySub;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CategorySubSeeder extends Seeder
{
    public function run(): void
    {
        $categorySubs = [
            [
                'category_name' => 'Mobile Phones',
                'category_id' => 1,
                'icon' => 'mobile.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
            [
                'category_name' => 'Laptops',
                'category_id' => 1,
                'icon' => 'laptop.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
            [
                'category_name' => 'Clothing',
                'category_id' => 2,
                'icon' => 'clothing.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
            [
                'category_name' => 'Furniture',
                'category_id' => 3,
                'icon' => 'furniture.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
        ];

        // Insert data ke dalam database
        CategorySub::insert($categorySubs);
    }
}
