<?php

namespace Figurate\FulfillmentManager;

use App\Events\Server\Ai\ConversationPostRequested;
use Figurate\FulfillmentManager\Listeners\HandleConversationPostRequested;
use Figurate\FulfillmentManager\Models\Assessment;
use Figurate\FulfillmentManager\Models\Dispute;
use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Payment;
use Figurate\FulfillmentManager\Models\Process;
use Figurate\FulfillmentManager\Models\Quote;
use Figurate\FulfillmentManager\Models\Rating;
use Figurate\FulfillmentManager\Models\Request;
use Figurate\FulfillmentManager\Models\ServiceCategory;
use Figurate\FulfillmentManager\Policies\Server\AssessmentPolicy;
use Figurate\FulfillmentManager\Policies\Server\DisputePolicy;
use Figurate\FulfillmentManager\Policies\Server\OrderPolicy;
use Figurate\FulfillmentManager\Policies\Server\PaymentPolicy;
use Figurate\FulfillmentManager\Policies\Server\ProcessPolicy;
use Figurate\FulfillmentManager\Policies\Server\QuotePolicy;
use Figurate\FulfillmentManager\Policies\Server\RatingPolicy;
use Figurate\FulfillmentManager\Policies\Server\RequestPolicy;
use Figurate\FulfillmentManager\Policies\Server\ServiceCategoryPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FulfillmentManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $existingResources = collect(config('api-platform.resources', []))
            ->filter(fn (mixed $resourcePath): bool => is_string($resourcePath) && $resourcePath !== '');

        $moduleResources = collect([
            dirname(__DIR__).'/src/Models',
            dirname(__DIR__).'/src/Http/Resources',
        ]);

        config([
            'api-platform.resources' => $existingResources
                ->merge($moduleResources)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    public function boot(): void
    {
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(Dispute::class, DisputePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Process::class, ProcessPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Rating::class, RatingPolicy::class);
        Gate::policy(Request::class, RequestPolicy::class);
        Gate::policy(ServiceCategory::class, ServiceCategoryPolicy::class);

        Event::listen(ConversationPostRequested::class, HandleConversationPostRequested::class);
    }
}
