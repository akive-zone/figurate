<?php

namespace Figurate\WebView;

use App\Support\Runtime\AppRuntime;
use Figurate\WebView\Http\Middleware\HandleInertiaRequests;
use Figurate\WebView\Http\Middleware\InjectWebViewAssets;
use Figurate\WebView\Support\WebViewAssets;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class WebViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        app(AppRuntime::class)->registerRootView('server', 'web-view::app');

        if (runtime() === 'server') {
            $this->app->register(ControlPanelProvider::class);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/web.php');
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'web-view');

        $this->callAfterResolving('blade.compiler', $this->registerBladeDirectives(...));
        $this->hookIntoResponses($this->app->make(Router::class));
    }

    protected function registerBladeDirectives(BladeCompiler $bladeCompiler): void
    {
        $bladeCompiler->directive('webViewAssets', fn (): string => '<?php echo '.var_export(WebViewAssets::appMarker(), true).'; ?>');
        $bladeCompiler->directive('webViewPasskeysAssets', fn (): string => '<?php echo '.var_export(WebViewAssets::passkeysMarker(), true).'; ?>');
    }

    protected function hookIntoResponses(Router $router): void
    {
        $this->app->booted(function () use ($router): void {
            if (app(AppRuntime::class)->isNative()) {
                return;
            }

            $router->pushMiddlewareToGroup('web', InjectWebViewAssets::class);
            $router->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        });
    }
}
