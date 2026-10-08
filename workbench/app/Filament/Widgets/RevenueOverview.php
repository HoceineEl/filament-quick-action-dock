<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Revenue', '$48,210')->description('12% up this month')->descriptionIcon(Heroicon::ArrowTrendingUp)->chart([4, 7, 5, 9, 8, 12, 15])->color('success'),
            Stat::make('Refunds', '$1,904')->description('3% down this month')->descriptionIcon(Heroicon::ArrowTrendingDown)->chart([9, 6, 8, 5, 6, 3, 4])->color('danger'),
            Stat::make('New customers', '318')->description('Steady')->chart([5, 6, 5, 7, 6, 8, 7])->color('info'),
        ];
    }
}
