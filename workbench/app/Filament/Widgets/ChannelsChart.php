<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ChannelsChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Orders by channel';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $palette = ['#f43f5e', '#f59e0b', '#10b981', '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6'];

        return [
            'datasets' => [
                ['label' => 'Web', 'data' => [120, 190, 150, 220, 260, 240, 300], 'backgroundColor' => $palette],
                ['label' => 'Mobile', 'data' => [80, 140, 170, 160, 210, 280, 260], 'backgroundColor' => array_reverse($palette)],
            ],
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }
}
