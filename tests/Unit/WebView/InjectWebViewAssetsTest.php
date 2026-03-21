<?php

namespace Tests\Unit\WebView;

use Figurate\WebView\Http\Middleware\InjectWebViewAssets;
use Figurate\WebView\Support\WebViewAssets;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

class InjectWebViewAssetsTest extends TestCase
{
    public function test_it_auto_injects_app_assets_for_inertia_html_responses(): void
    {
        $middleware = new class extends InjectWebViewAssets
        {
            protected function appAssets(): string
            {
                return '<script src="/build/web-view.js"></script>';
            }
        };

        $response = $middleware->handle(
            Request::create('/chat', 'GET'),
            fn (): Response => new Response(
                '<html><head></head><body><div id="app" data-page="{}"></div></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        );

        $this->assertStringContainsString('<script src="/build/web-view.js"></script>', (string) $response->getContent());
        $this->assertStringContainsString('</head>', (string) $response->getContent());
    }

    public function test_it_replaces_explicit_markers_with_assets(): void
    {
        $middleware = new class extends InjectWebViewAssets
        {
            protected function appAssets(): string
            {
                return '<script data-app></script>';
            }

            protected function passkeysAssets(): string
            {
                return '<script data-passkeys></script>';
            }
        };

        $response = $middleware->handle(
            Request::create('/passkeys/manage', 'GET'),
            fn (): Response => new Response(
                '<html><head>'.WebViewAssets::appMarker().WebViewAssets::passkeysMarker().'</head><body></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            ),
        );

        $this->assertStringContainsString('<script data-app></script>', (string) $response->getContent());
        $this->assertStringContainsString('<script data-passkeys></script>', (string) $response->getContent());
        $this->assertStringNotContainsString(WebViewAssets::appMarker(), (string) $response->getContent());
        $this->assertStringNotContainsString(WebViewAssets::passkeysMarker(), (string) $response->getContent());
    }
}
