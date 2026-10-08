<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use HoceineEl\QuickActionDock\Support\DockContext;

it('flattens array and closure actions by name', function (): void {
    dock()
        ->actions([Action::make('first'), Action::make('second')])
        ->actions(fn (): array => [Action::make('third')]);

    expect(array_keys(dock()->getActions()))->toBe(['first', 'second', 'third']);
});

it('keeps labelled groups in order', function (): void {
    dock()
        ->actions([Action::make('inbox')])
        ->group('Create', [Action::make('newCustomer')])
        ->group(fn (): string => 'Help', fn (): array => [Action::make('openDocs')]);

    $groups = dock()->getGroups();

    expect($groups)->toHaveCount(3)
        ->and(array_column($groups, 'label'))->toBe([null, 'Create', 'Help'])
        ->and(array_keys($groups[2]['actions']))->toBe(['openDocs']);
});

it('rebuilds actions from closures on every call', function (): void {
    dock()->actions(fn (): array => [Action::make('fresh')]);

    expect(dock()->getActions()['fresh'])->not->toBe(dock()->getActions()['fresh']);
});

it('renders unless hidden on the current route', function (): void {
    dock()->hiddenOn(['filament.admin.auth.*', 'filament.admin.pages.settings']);

    DockContext::current()->set('filament.admin.pages.dashboard', []);
    expect(dock()->shouldRender())->toBeTrue();

    DockContext::current()->set('filament.admin.auth.login', []);
    expect(dock()->shouldRender())->toBeFalse();

    DockContext::current()->set('filament.admin.pages.settings', []);
    expect(dock()->shouldRender())->toBeFalse();
});

it('respects the visible condition', function (): void {
    dock()->visible(fn (): bool => DockContext::routeIs('filament.admin.resources.*'));

    DockContext::current()->set('filament.admin.pages.dashboard', []);
    expect(dock()->shouldRender())->toBeFalse();

    DockContext::current()->set('filament.admin.resources.customers.index', []);
    expect(dock()->shouldRender())->toBeTrue();

    expect(dock()->visible(false)->shouldRender())->toBeFalse();
});

it('mounts the dock at the end of the body on panels that register it', function (): void {
    dock()->actions([Action::make('newCustomer')]);
    Filament::bootCurrentPanel();

    expect(FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml())->toContain('qad-root');
});

it('renders nothing at the end of the body when hidden', function (): void {
    dock()->actions([Action::make('newCustomer')])->visible(false);
    Filament::bootCurrentPanel();

    expect(FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml())->not->toContain('qad-root');
});

it('renders nothing on panels without the plugin', function (): void {
    dock()->actions([Action::make('newCustomer')]);
    Filament::bootCurrentPanel();
    Filament::setCurrentPanel('other');

    expect(FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml())->not->toContain('qad-root');
});
