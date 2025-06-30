<?php

namespace Database\Factories;

use App\Models\ContactForm;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactForm>
 */
class ContactFormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'subject' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(3),
            'is_responded' => $this->faker->boolean(30), // 30% chance of being responded
            'created_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
            'updated_at' => function (array $attributes) {
                // If it has been answered, the update date is after the creation date
                if ($attributes['is_responded']) {
                    return Carbon::parse($attributes['created_at'])->addHours(rand(1, 72));
                }
                // If it hasn't been answered, the update date is the same as the creation date
                return $attributes['created_at'];
            },
        ];
    }

    /**
     * Configure the model factory to create a contact form from this week.
     *
     * @return Factory
     */
    public function thisWeek(): Factory
    {
        return $this->state(function (array $attributes) {
            // Calculate the start of the current week (Monday)
            $startOfWeek = Carbon::now()->startOfWeek();

            // Generate a random date between the beginning of the week and now
            $createdAt = $this->faker->dateTimeBetween(
                $startOfWeek->format('Y-m-d H:i:s'),
                Carbon::now()->format('Y-m-d H:i:s')
            );

            return [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'is_responded' => $this->faker->boolean(20), // 20% chance of being answered
            ];
        });
    }

    /**
     * Configure the model factory to create a contact form from last week.
     *
     * @return Factory
     */
    public function lastWeek(): Factory
    {
        return $this->state(function (array $attributes) {
            // Calculate the start of last week
            $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek();
            $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek();

            $createdAt = $this->faker->dateTimeBetween(
                $startOfLastWeek->format('Y-m-d H:i:s'),
                $endOfLastWeek->format('Y-m-d H:i:s')
            );

            return [
                'created_at' => $createdAt,
                'updated_at' => $this->faker->boolean(70) ? // 70% chance of being updated
                    Carbon::parse($createdAt)->addHours(rand(1, 48)) :
                    $createdAt,
                'is_responded' => $this->faker->boolean(60), // 60% chance of being answered
            ];
        });
    }
}
