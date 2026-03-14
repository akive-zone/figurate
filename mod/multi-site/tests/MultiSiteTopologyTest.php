<?php

namespace Figurate\MultiSite\Tests;

use Figurate\MultiSite\MultiSiteDefinition;
use Tests\TestCase;

class MultiSiteTopologyTest extends TestCase
{
    public function test_multi_site_is_configured_for_separate_databases(): void
    {
        $definition = $this->app->make(MultiSiteDefinition::class);

        $this->assertSame('stancl/tenancy', $definition->driver);
        $this->assertSame('separate', $definition->databaseStrategy);
        $this->assertTrue($definition->separateDatabase);
        $this->assertSame(['domain', 'subdomain'], $definition->identifiers);
    }
}
