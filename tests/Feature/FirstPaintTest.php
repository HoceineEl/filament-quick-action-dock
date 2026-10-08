<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use HoceineEl\QuickActionDock\Enums\DockLayout;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use Livewire\Livewire;

it('renders the speed dial closed before any JavaScript runs', function (): void {
    dock()->actions([Action::make('inbox')]);

    Livewire::test(QuickActionDock::class)
        ->assertSeeHtml('data-mode="speed-dial"')
        ->assertSeeHtml('data-open="false"');
});

it('renders the bar expanded before any JavaScript runs', function (): void {
    dock()->actions([Action::make('inbox')])->layout(DockLayout::Bar);

    Livewire::test(QuickActionDock::class)
        ->assertSeeHtml('data-mode="bar"')
        ->assertSeeHtml('data-open="true"');
});

it('hides the closed speed dial menu with CSS alone', function (): void {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/index.css');

    expect($css)->toMatch("/\\.qad-root\\[data-mode='speed-dial'\\] \\.qad-menu \\{[^}]*visibility: hidden;/");
});

it('places the dock synchronously instead of lazy loading it', function (): void {
    dock()->actions([Action::make('inbox')]);
    Filament::bootCurrentPanel();

    $html = FilamentView::renderHook(PanelsRenderHook::BODY_END)->toHtml();

    expect($html)->not->toContain('x-load')
        ->and($html)->toContain('data-config=')
        ->and($html)->toMatch('/<\/div>\s*<script[^>]*>\s*window\.QuickActionDock\?\.prePlace\(/');
});
