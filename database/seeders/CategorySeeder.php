<?php

namespace Database\Seeders;

use App\Models\Categories;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'category_name' => 'Electronics',
                'icon' => 'electronics.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
            [
                'category_name' => 'Fashion',
                'icon' => 'fashion.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
            [
                'category_name' => 'Home & Living',
                'icon' => 'home_living.png',
                'author' => 'Seeder',
                'created' => Carbon::now(),
                'updated' => Carbon::now(),
                'updater' => 'Seeder',
                'status' => '1',
            ],
        ];

        // Insert data ke dalam database
        Categories::insert($categories);
    }
}
