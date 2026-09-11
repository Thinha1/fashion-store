<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => 'GR-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
            'supplier_id' => Supplier::factory(),
            'status' => 'draft',
            'total_cost' => 0,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory()->admin(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => 'confirmed',
            'confirmed_by' => User::factory()->admin(),
            'confirmed_at' => now(),
        ]);
    }
}
