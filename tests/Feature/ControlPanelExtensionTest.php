<?php

namespace Tests\Feature;

use Figurate\ControlPanel\ControlPanelServiceProvider;
use Figurate\ControlPanel\Filament\Resources\ContextServers\ContextServerResource;
use Figurate\ControlPanel\Http\Middleware\EnsurePanelUser;
use Figurate\WebView\ControlPanelProvider;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ControlPanelExtensionTest extends TestCase
{
    public function test_control_panel_is_owned_by_the_control_panel_extension(): void
    {
        $this->assertTrue(class_exists(ControlPanelServiceProvider::class));
        $this->assertTrue(class_exists(ContextServerResource::class));
        $this->assertTrue(class_exists(EnsurePanelUser::class));
        $this->assertFalse(class_exists(ControlPanelProvider::class));
        $this->assertFalse(class_exists(\App\Http\Middleware\EnsurePanelUser::class));

        $route = Route::getRoutes()->getByName(
            'filament.server-control.resources.context-servers.index',
        );

        $this->assertNotNull($route);
        $this->assertSame('p/context-servers', $route->uri());
        $this->assertStringStartsWith(
            'Figurate\\ControlPanel\\',
            $route->getActionName(),
        );
    }
}
