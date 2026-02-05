<?php

namespace App\Providers\Browser;

use App\Models\Order;
use App\Models\Profile;
use App\Models\Quote;
use App\Models\Request;
use App\Policies\OrderPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\QuotePolicy;
use App\Policies\RequestPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(Request::class, RequestPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
