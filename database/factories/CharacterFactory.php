<?php

namespace Database\Factories;

use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->firstName();

        return [
            'slug' => strtolower($name),
            'name' => $name,
            'tagline' => "{$name} listens.",
            'system_prompt' => "You are {$name}.",
        ];
    }
}
