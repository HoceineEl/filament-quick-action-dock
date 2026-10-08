<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\Widget;

class CampaignPalette extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'widgets.campaign-palette';
}
