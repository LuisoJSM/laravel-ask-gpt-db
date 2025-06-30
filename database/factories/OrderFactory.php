<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        // Create a date distribution: 50% in the past, 50% in the future
        $dateType = $this->faker->randomElement(['past', 'future']);

        if ($dateType === 'past') {
            // Date in the past (up to 90 days ago)
            $deliveryDate = $this->faker->dateTimeBetween('-90 days', 'now');
            // For past orders, favor the 'completed' status
            $status = $this->faker->randomElement(['completed', 'completed', 'completed', 'cancelled']);
        } else {
            // Date in the future (up to 30 days ahead)
            $deliveryDate = $this->faker->dateTimeBetween('now', '+30 days');
            // For future orders, favor 'pending' and 'processing' statuses
            $status = $this->faker->randomElement(['pending', 'pending', 'processing', 'processing', 'cancelled']);
        }

        // Creation date: always before the delivery date
        $createdAt = Carbon::instance($deliveryDate)->copy()->subDays($this->faker->numberBetween(1, 15));

        // The creation date must not be in the future
        if ($createdAt->isFuture()) {
            $createdAt = Carbon::now()->subHours($this->faker->numberBetween(1, 48));
        }

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . $this->faker->unique()->numerify('######'),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'status' => $status,
            'delivery_date' => $deliveryDate,
            'created_at' => $createdAt,
            'updated_at' => $createdAt->copy()->addHours($this->faker->numberBetween(1, 24)),
        ];
    }

    /**
     * Sets the order to have a date in the past.
     *
     * @return Factory
     */
    public function past(): Factory
    {
        return $this->state(function (array $attributes) {
            // Delivery date in the past (up to 90 days ago)
            $deliveryDate = $this->faker->dateTimeBetween('-90 days', '-1 day');

            // For past orders, they are usually completed or cancelled
            $status = $this->faker->randomElement(['completed', 'completed', 'completed', 'cancelled']);

            // Creation date: before delivery
            $createdAt = Carbon::instance($deliveryDate)->copy()->subDays($this->faker->numberBetween(1, 10));

            return [
                'status' => $status,
                'delivery_date' => $deliveryDate,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours($this->faker->numberBetween(1, 48)),
            ];
        });
    }

    /**
     * Set the order to have a date in the future.
     *
     * @return Factory
     */
    public function future(): Factory
    {
        return $this->state(function (array $attributes) {
            // Delivery date in the future (up to 30 days ahead)
            $deliveryDate = $this->faker->dateTimeBetween('+1 day', '+30 days');

            // For future orders, they are usually pending or in process
            $status = $this->faker->randomElement(['pending', 'pending', 'processing', 'processing', 'cancelled']);

            // Creation date: always now or in the recent past
            $createdAt = Carbon::now()->subHours($this->faker->numberBetween(1, 72));

            return [
                'status' => $status,
                'delivery_date' => $deliveryDate,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours($this->faker->numberBetween(1, 12)),
            ];
        });
    }

    /**
     * Set the order as recently completed.
     *
     * @return Factory
     */
    public function recentlyCompleted(): Factory
    {
        return $this->state(function (array $attributes) {
            // Delivery in the last 7 days
            $deliveryDate = $this->faker->dateTimeBetween('-7 days', 'now');

            // Order created 1-5 days before delivery
            $createdAt = Carbon::instance($deliveryDate)->copy()->subDays($this->faker->numberBetween(1, 5));

            return [
                'status' => 'completed',
                'delivery_date' => $deliveryDate,
                'created_at' => $createdAt,
                'updated_at' => Carbon::instance($deliveryDate), // Actualizado en la fecha de entrega
            ];
        });
    }
}
