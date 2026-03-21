<?php

namespace Figurate\WebView\Http\Middleware;

use Closure;
use Figurate\WebView\Support\WebViewAssets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InjectWebViewAssets
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldInject($response)) {
            return $response;
        }

        $originalView = $response->original ?? null;

        $response->setContent($this->injectAssets($response->getContent()));

        if ($originalView instanceof View && property_exists($response, 'original')) {
            $response->original = $originalView;
        }

        return $response;
    }

    protected function shouldInject(Response $response): bool
    {
        $unsupportedResponseTypes = [
            StreamedResponse::class,
            BinaryFileResponse::class,
            JsonResponse::class,
            RedirectResponse::class,
        ];

        foreach ($unsupportedResponseTypes as $unsupportedResponseType) {
            if ($response instanceof $unsupportedResponseType) {
                return false;
            }
        }

        if (! str_contains((string) $response->headers->get('content-type', ''), 'html')) {
            return false;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return false;
        }

        if (! str_contains($content, '<html') && ! str_contains($content, '<head')) {
            return false;
        }

        return str_contains($content, WebViewAssets::appMarker())
            || str_contains($content, WebViewAssets::passkeysMarker())
            || $this->shouldAutoInjectAppAssets($content);
    }

    protected function injectAssets(string $content): string
    {
        if (str_contains($content, WebViewAssets::appMarker())) {
            $content = str_replace(WebViewAssets::appMarker(), $this->appAssets(), $content);
        } elseif ($this->shouldAutoInjectAppAssets($content)) {
            $content = $this->injectIntoHead($content, $this->appAssets());
        }

        if (str_contains($content, WebViewAssets::passkeysMarker())) {
            $content = str_replace(WebViewAssets::passkeysMarker(), $this->passkeysAssets(), $content);
        }

        return $content;
    }

    protected function shouldAutoInjectAppAssets(string $content): bool
    {
        return str_contains($content, 'data-page=');
    }

    protected function injectIntoHead(string $content, string $assets): string
    {
        if (str_contains($content, '</head>')) {
            return str_replace('</head>', $assets."\n</head>", $content);
        }

        if (str_contains($content, '</body>')) {
            return str_replace('</body>', $assets."\n</body>", $content);
        }

        return $content.$assets;
    }

    protected function appAssets(): string
    {
        return WebViewAssets::app()->toHtml();
    }

    protected function passkeysAssets(): string
    {
        return WebViewAssets::passkeys()->toHtml();
    }
}
