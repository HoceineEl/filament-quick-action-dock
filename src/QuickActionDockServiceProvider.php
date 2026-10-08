<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock;

use Filament\Support\Facades\FilamentAsset;
use HoceineEl\QuickActionDock\Assets\DockCss;
use HoceineEl\QuickActionDock\Assets\DockScript;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use HoceineEl\QuickActionDock\Support\DockContext;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class QuickActionDockServiceProvider extends PackageServiceProvider
{
    public static string $name = 'quick-action-dock';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews(static::$name)
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(DockContext::class, fn (): DockContext => DockContext::fromRoute(request()->route(), request()->path()));
    }

    public function packageBooted(): void
    {
        Livewire::component('quick-action-dock', QuickActionDock::class);

        FilamentAsset::register([
            DockScript::make('quick-action-dock', __DIR__.'/../dist/quick-action-dock.js'),
            DockCss::make('quick-action-dock', __DIR__.'/../dist/quick-action-dock.css'),
        ], 'hoceineel/filament-quick-action-dock');
    }
}
