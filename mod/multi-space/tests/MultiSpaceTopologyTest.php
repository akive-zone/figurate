<?php

namespace Figurate\MultiSpace\Tests;

use Figurate\MultiSpace\MultiSpaceDefinition;
use Tests\TestCase;

class MultiSpaceTopologyTest extends TestCase
{
    public function test_multi_space_is_configured_for_shared_database_within_multi_site(): void
    {
        $definition = $this->app->make(MultiSpaceDefinition::class);

        $this->assertSame('spatie/laravel-multitenancy', $definition->driver);
        $this->assertSame('shared', $definition->databaseStrategy);
        $this->assertTrue($definition->sharedDatabase);
        $this->assertTrue($definition->requiresSiteContext);
        $this->assertSame('multi_site', $definition->parentLayer);
        $this->assertSame(['site_id', 'space_id'], $definition->scopeColumns);
        $this->assertFalse(config('multitenancy.queues_are_tenant_aware_by_default'));
    }
}
