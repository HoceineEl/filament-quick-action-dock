<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Support;

use Closure;
use Filament\Actions\Action;

class DockGroup
{
    /**
     * @param  array<Action> | Closure(): array<Action>  $actions
     */
    public function __construct(
        public readonly string|Closure|null $label,
        public readonly array|Closure $actions,
    ) {}
}
