<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use App\Models\Server\Profile;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Quote;
use Figurate\FulfillmentManager\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @var class-string<Order>
     */
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'quote_id' => Quote::factory(),
            'buyer_id' => User::factory(),
            'seller_profile_id' => Profile::factory(),
            'status' => 'booked',
        ];
    }
}
