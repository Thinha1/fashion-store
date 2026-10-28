<?php

namespace Database\Factories;

use App\Models\SizeChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeChart>
 */
class SizeChartFactory extends Factory
{
    /**
     * A shop-wide chart (any brand, any category) with M/L/XL rows.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Bảng size '.fake()->unique()->word(),
            'brand_id' => null,
            'category_id' => null,
            'columns' => ['Vòng ngực (cm)'],
            'rows' => [
                ['size' => 'M', 'height_min' => 160, 'height_max' => 168, 'weight_min' => 55, 'weight_max' => 62, 'values' => ['90-94']],
                ['size' => 'L', 'height_min' => 168, 'height_max' => 174, 'weight_min' => 62, 'weight_max' => 69, 'values' => ['94-98']],
                ['size' => 'XL', 'height_min' => 174, 'height_max' => 180, 'weight_min' => 69, 'weight_max' => 76, 'values' => ['98-102']],
            ],
            'note' => null,
        ];
    }
}
