<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Mobile Legends', 'Free Fire', 'PUBG Mobile', 'Genshin Impact'];

        foreach ($categories as $name) {
            Category::create(['name' => $name]);
        }
    }
}
