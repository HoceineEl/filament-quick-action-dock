<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use HoceineEl\QuickActionDock\Enums\DockLayout;
use HoceineEl\QuickActionDock\Enums\DockPosition;
use HoceineEl\QuickActionDock\QuickActionDockPlugin;

it('has the quick-action-dock id', function (): void {
    expect(QuickActionDockPlugin::make()->getId())->toBe('quick-action-dock');
});

it('resolves the instance registered on the current panel', function (): void {
    expect(QuickActionDockPlugin::get())
        ->toBe(Filament::getPanel('admin')->getPlugin('quick-action-dock'))
        ->and(Filament::getPanel('other')->hasPlugin('quick-action-dock'))->toBeFalse();
});

it('ships sensible defaults', function (): void {
    $plugin = QuickActionDockPlugin::make();

    expect($plugin->getLayout())->toBe(DockLayout::SpeedDial)
        ->and($plugin->getPosition())->toBe(DockPosition::BottomEnd)
        ->and($plugin->getTriggerIcon())->toBe(Heroicon::Plus)
        ->and($plugin->getTriggerLabel())->toBe('Quick actions')
        ->and($plugin->getKeyBindings())->toBe(['mod+.'])
        ->and($plugin->getOffset())->toBe(24)
        ->and($plugin->hasLabels())->toBeFalse()
        ->and($plugin->getBadgePollingInterval())->toBeNull()
        ->and($plugin->getActions())->toBe([]);
});

it('accepts values and closures for every option', function (): void {
    $plugin = QuickActionDockPlugin::make()
        ->layout(fn (): DockLayout => DockLayout::Bar)
        ->position(DockPosition::BottomCenter)
        ->triggerIcon(fn () => Heroicon::Bolt)
        ->triggerLabel(fn (): string => 'Shortcuts')
        ->keyBindings('mod+j')
        ->offset(fn (): int => 12)
        ->showLabels()
        ->pollBadges();

    expect($plugin->getLayout())->toBe(DockLayout::Bar)
        ->and($plugin->getPosition())->toBe(DockPosition::BottomCenter)
        ->and($plugin->getTriggerIcon())->toBe(Heroicon::Bolt)
        ->and($plugin->getTriggerLabel())->toBe('Shortcuts')
        ->and($plugin->getKeyBindings())->toBe(['mod+j'])
        ->and($plugin->getOffset())->toBe(12)
        ->and($plugin->hasLabels())->toBeTrue()
        ->and($plugin->getBadgePollingInterval())->toBe('30s');
});

it('allows the toggle key binding to be disabled', function (): void {
    expect(QuickActionDockPlugin::make()->keyBindings(null)->getKeyBindings())->toBe([]);
});

it('translates the trigger label', function (string $locale, string $label): void {
    app()->setLocale($locale);

    expect(QuickActionDockPlugin::make()->getTriggerLabel())->toBe($label);
})->with([
    ['en', 'Quick actions'],
    ['ar', 'إجراءات سريعة'],
    ['fr', 'Actions rapides'],
]);
