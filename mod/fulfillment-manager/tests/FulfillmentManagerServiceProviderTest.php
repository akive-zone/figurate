<?php

namespace Figurate\FulfillmentManager\Tests;

use Figurate\FulfillmentManager\Models\Request;
use Figurate\FulfillmentManager\Models\ServiceCategory;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FulfillmentManagerServiceProviderTest extends TestCase
{
    public function test_the_module_registers_api_platform_resource_paths_and_policies(): void
    {
        $resources = config('api-platform.resources', []);

        $this->assertContains(
            base_path('mod/fulfillment-manager/src/Models'),
            $resources,
        );

        $this->assertContains(
            base_path('mod/fulfillment-manager/src/Http/Resources'),
            $resources,
        );

        $this->assertNotNull(Gate::getPolicyFor(Request::class));
    }

    public function test_the_module_models_resolve_their_package_factories(): void
    {
        $this->assertInstanceOf(Request::class, Request::factory()->make());
        $this->assertInstanceOf(ServiceCategory::class, ServiceCategory::factory()->make());
    }
}
