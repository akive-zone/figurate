<?php

namespace App\Providers\Server;

use App\Models\Server\Channel;
use App\Models\Server\Fulfillment\Assessment;
use App\Models\Server\Fulfillment\Dispute;
use App\Models\Server\Fulfillment\Order;
use App\Models\Server\Fulfillment\Payment;
use App\Models\Server\Fulfillment\Process;
use App\Models\Server\Fulfillment\Quote;
use App\Models\Server\Fulfillment\Rating;
use App\Models\Server\Fulfillment\Request;
use App\Models\Server\Fulfillment\ServiceCategory;
use App\Models\Server\Message;
use App\Models\Server\Profile;
use App\Models\Server\Thread;
use App\Policies\Server\AssessmentPolicy;
use App\Policies\Server\ChannelPolicy;
use App\Policies\Server\DisputePolicy;
use App\Policies\Server\MessagePolicy;
use App\Policies\Server\OrderPolicy;
use App\Policies\Server\PaymentPolicy;
use App\Policies\Server\ProcessPolicy;
use App\Policies\Server\ProfilePolicy;
use App\Policies\Server\QuotePolicy;
use App\Policies\Server\RatingPolicy;
use App\Policies\Server\RequestPolicy;
use App\Policies\Server\ServiceCategoryPolicy;
use App\Policies\Server\ThreadPolicy;
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
        Gate::policy(Process::class, ProcessPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Rating::class, RatingPolicy::class);
        Gate::policy(Dispute::class, DisputePolicy::class);
        Gate::policy(ServiceCategory::class, ServiceCategoryPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Thread::class, ThreadPolicy::class);
    }
}
