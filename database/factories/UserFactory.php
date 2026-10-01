<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake('id_ID')->name(),
            'phone_number' => '62' . fake()->unique()->numerify('8##########'),
            'telegram_user_id' => null,
            'password' => 'password', // di-hash otomatis oleh cast 'hashed'
            'role' => 'operator',
            'department' => null,
            'plant_id' => (string) config('poms.plant_id', 'PKS_01'),
            'status' => 'active',
        ];
    }

    public function operator(string $department = 'proses'): static
    {
        return $this->state(fn () => ['role' => 'operator', 'department' => $department]);
    }

    public function asisten(string $department = 'proses'): static
    {
        return $this->state(fn () => ['role' => 'asisten', 'department' => $department]);
    }

    public function askep(): static
    {
        return $this->state(fn () => ['role' => 'askep']);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => 'manager']);
    }

    public function hqAdmin(): static
    {
        return $this->state(fn () => ['role' => 'hq_admin']);
    }

    public function forPlant(string $plantId): static
    {
        return $this->state(fn () => ['plant_id' => $plantId]);
    }
}
