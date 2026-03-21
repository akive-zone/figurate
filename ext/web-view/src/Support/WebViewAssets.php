<?php

namespace Figurate\WebView\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

class WebViewAssets
{
    public static function appMarker(): string
    {
        return '<!-- figurate-web-view-assets -->';
    }

    public static function passkeysMarker(): string
    {
        return '<!-- figurate-web-view-passkeys-assets -->';
    }

    public static function app(): HtmlString
    {
        /** @var Vite $vite */
        $vite = clone app(Vite::class);

        return $vite([
            'resources/css/app.css',
            'ext/web-view/resources/js/app.js',
        ]);
    }

    public static function passkeys(): HtmlString
    {
        /** @var Vite $vite */
        $vite = clone app(Vite::class);

        return $vite('ext/web-view/resources/js/passkeys.js');
    }
}
