<?php

namespace Tests\Unit\WebView;

use App\Support\Runtime\AppRuntime;
use Figurate\AccountManager\Support\AccountContextFactory;
use Figurate\WebView\Http\Middleware\HandleInertiaRequests;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class HandleInertiaRequestsRootViewTest extends TestCase
{
    public function test_it_uses_the_configured_frontend_root_view(): void
    {
        $container = new Container;
        $runtime = new AppRuntime;
        $runtime->registerRootView('desktop', 'desktop-native::app');
        $runtime->forceHost('desktop');

        $container->instance(AppRuntime::class, $runtime);
        Container::setInstance($container);

        $middleware = new HandleInertiaRequests(
            $this->createStub(AccountContextFactory::class),
            $runtime,
        );

        $this->assertSame('desktop-native::app', $middleware->rootView(Request::create('/')));

        Container::setInstance(null);
    }
}
