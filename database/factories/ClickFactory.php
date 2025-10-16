<?php

namespace Database\Factories;

use App\Models\Click;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClickFactory extends Factory
{
    protected $model = Click::class;

    public function definition(): array
    {
        $isValid = (bool) random_int(0, 1);
        $ts = now()->subDays(random_int(0, 13))
                   ->setTime(random_int(0, 23), random_int(0, 59));

        return [
            'subscription_id' => null,
            'token'           => Str::random(16),
            'is_valid'        => $isValid,
            'invalid_reason'  => $isValid ? null : (random_int(0, 3) === 0 ? 'not_subscribed' : null),
            'clicked_at'      => $ts,
            'ip'              => $this->faker->ipv4(),
            'user_agent'      => $this->faker->userAgent(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }
}
