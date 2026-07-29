<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('+1##########'),
            'email' => fake()->unique()->safeEmail(),
            'website' => fake()->boolean(70) ? fake()->url() : null,
            'gender' => fake()->randomElement(Contact::GENDERS),
            'age' => fake()->numberBetween(18, 90),
            'nationality' => fake()->country(),
            'created_by' => User::factory(),
        ];
    }
}
