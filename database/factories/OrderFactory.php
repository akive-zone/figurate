<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Quote;
use App\Models\Request;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $buyer = User::factory();
        $profile = Profile::factory();
        $request = Request::factory()
            ->for($buyer, 'requester')
            ->for($profile);
        $quote = Quote::factory()
            ->for($request)
            ->for($profile);

        return [
            'request_id' => $request,
            'quote_id' => $quote,
            'buyer_id' => $buyer,
            'seller_profile_id' => $profile,
            'status' => 'booked',
        ];
    }
}
