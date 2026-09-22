<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->profile() // own profile (name, email, password) – each user edits their own
            // Colors matched to the raftingmorava.cz website (water blue #09afe6 + green accent).
            ->colors([
                'primary' => Color::hex('#09afe6'),
                'info' => Color::hex('#09afe6'),
                'success' => Color::hex('#1da867'),
                'gray' => Color::Slate,
            ])
            ->brandName('Rafting Morava')
            ->brandLogo(asset('logo-cerna.png'))
            ->darkModeBrandLogo(asset('logo-bila.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.png'))
            // The left menu can collapse on desktop into a narrow icon strip (less space).
            ->sidebarCollapsibleOnDesktop()
            // Default is a centered max-w-7xl - too narrow for our widest tables (Poptávky, Trips).
            // Full width everywhere; a page can still narrow itself via its own $maxContentWidth.
            ->maxContentWidth(Width::Full)
            // Fixed menu group order (Provoz first – it's used the most day to day).
            ->navigationGroups([
                NavigationGroup::make('Provoz'),
                NavigationGroup::make('Přehledy'),
                NavigationGroup::make('Správa'),
                NavigationGroup::make('Číselníky'),
                NavigationGroup::make('Nápověda'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
