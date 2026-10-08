<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Enums;

enum DockStyle: string
{
    case Native = 'native';
    case Outline = 'outline';
    case Contrast = 'contrast';
    case Tinted = 'tinted';
}
