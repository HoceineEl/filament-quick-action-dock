<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use HoceineEl\QuickActionDock\QuickActionDockPlugin;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->resources([CustomerResource::class])
            ->plugin(QuickActionDockPlugin::make());
    }
}
