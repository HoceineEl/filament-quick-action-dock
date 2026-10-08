<?php

declare(strict_types=1);

use HoceineEl\QuickActionDock\QuickActionDockPlugin;
use HoceineEl\QuickActionDock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function dock(): QuickActionDockPlugin
{
    return QuickActionDockPlugin::get();
}
