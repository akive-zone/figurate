<?php

namespace Figurate\WebView;

use App\Http\Middleware\EnsurePanelUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ControlPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('server-control')
            ->path('p')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: __DIR__.'/Filament/Resources', for: 'Figurate\\WebView\\Filament\\Resources')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make('Back to Chat')
                    ->url(fn (): string => route('chat.index'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->sort(-100),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePanelUser::class,
            ]);
    }
}
