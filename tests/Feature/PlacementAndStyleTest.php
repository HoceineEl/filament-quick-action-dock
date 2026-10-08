<?php

declare(strict_types=1);

use Filament\Actions\Action;
use HoceineEl\QuickActionDock\Enums\DockPosition;
use HoceineEl\QuickActionDock\Enums\DockStyle;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use HoceineEl\QuickActionDock\QuickActionDockPlugin;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;

function dockUser(int $id): User
{
    $user = new User;
    $user->forceFill(['id' => $id]);

    return $user;
}

it('defaults to the native style with dragging, snapping and remembering on', function (): void {
    $plugin = QuickActionDockPlugin::make();

    expect($plugin->getStyle())->toBe(DockStyle::Native)
        ->and($plugin->isDraggable())->toBeTrue()
        ->and($plugin->shouldSnapToEdges())->toBeTrue()
        ->and($plugin->shouldRememberPosition())->toBeTrue();
});

it('configures style and placement with values or closures', function (): void {
    $plugin = QuickActionDockPlugin::make()
        ->style(fn (): DockStyle => DockStyle::Outline)
        ->draggable(fn (): bool => false)
        ->snapToEdges(false)
        ->rememberPosition(false)
        ->defaultPosition(DockPosition::BottomStart);

    expect($plugin->getStyle())->toBe(DockStyle::Outline)
        ->and($plugin->isDraggable())->toBeFalse()
        ->and($plugin->shouldSnapToEdges())->toBeFalse()
        ->and($plugin->shouldRememberPosition())->toBeFalse()
        ->and($plugin->getPosition())->toBe(DockPosition::BottomStart);
});

it('renders the chosen style', function (DockStyle $style): void {
    dock()->actions([Action::make('inbox')])->style($style);

    Livewire::test(QuickActionDock::class)->assertSeeHtml("data-qad-style=\"{$style->value}\"");
})->with(DockStyle::cases());

it('passes placement options to the client', function (): void {
    dock()->actions([Action::make('inbox')])->snapToEdges(false)->defaultPosition(DockPosition::BottomCenter)->offset(16);

    $config = Livewire::test(QuickActionDock::class)->instance()->getClientConfig();

    expect($config)->toMatchArray([
        'layout' => 'speed-dial',
        'panel' => 'admin',
        'draggable' => true,
        'snap' => false,
        'remember' => true,
        'defaultPosition' => 'bottom-center',
        'offset' => 16,
        'keyBindings' => ['mod+.'],
    ]);
});

it('identifies the user by a hash, never the raw id', function (): void {
    dock()->actions([Action::make('inbox')]);

    $this->actingAs(dockUser(7));
    $first = Livewire::test(QuickActionDock::class)->instance()->getUserHash();
    $again = Livewire::test(QuickActionDock::class)->instance()->getUserHash();

    $this->actingAs(dockUser(8));
    $other = Livewire::test(QuickActionDock::class)->instance()->getUserHash();

    expect($first)->toMatch('/^[a-f0-9]{16}$/')
        ->and($first)->toBe($again)
        ->and($first)->not->toBe($other)
        ->and($first)->not->toBe(hash('sha256', '7'));
});

it('uses a guest key when nobody is signed in', function (): void {
    dock()->actions([Action::make('inbox')])->authenticatedOnly(false);
    auth()->logout();

    expect(Livewire::test(QuickActionDock::class)->instance()->getUserHash())->toBe('guest');
});

it('renders the move controls only when draggable', function (): void {
    dock()->actions([Action::make('inbox')]);

    Livewire::test(QuickActionDock::class)->assertSeeHtml('qad-move');

    dock()->draggable(false);

    Livewire::test(QuickActionDock::class)->assertDontSeeHtml('qad-move');
});
