<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::inRandomOrder()->first()->id,
            'name' => 'Voucher '.$this->faker->word(),
            'description' => $this->faker->sentence(),
            'size' => $this->faker->randomElement(['Small', 'Medium', 'Large']),
            'price' => $this->faker->numberBetween(5000, 20000),
            'user_account' => $this->faker->userName(),
            'password_account' => $this->faker->password(),
            'status' => 'available',
        ];
    }
}
