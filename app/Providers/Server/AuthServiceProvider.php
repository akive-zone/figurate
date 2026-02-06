<?php

namespace App\Providers\Server;

use App\Models\Server\Assessment;
use App\Models\Server\Dispute;
use App\Models\Server\Order;
use App\Models\Server\Payment;
use App\Models\Server\Profile;
use App\Models\Server\Quote;
use App\Models\Server\Rating;
use App\Models\Server\Request;
use App\Models\Server\ServiceCategory;
use App\Models\Server\WorkLog;
use App\Policies\Server\AssessmentPolicy;
use App\Policies\Server\DisputePolicy;
use App\Policies\Server\OrderPolicy;
use App\Policies\Server\PaymentPolicy;
use App\Policies\Server\ProfilePolicy;
use App\Policies\Server\QuotePolicy;
use App\Policies\Server\RatingPolicy;
use App\Policies\Server\RequestPolicy;
use App\Policies\Server\ServiceCategoryPolicy;
use App\Policies\Server\WorkLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(Request::class, RequestPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(WorkLog::class, WorkLogPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Rating::class, RatingPolicy::class);
        Gate::policy(Dispute::class, DisputePolicy::class);
        Gate::policy(ServiceCategory::class, ServiceCategoryPolicy::class);
    }
}
