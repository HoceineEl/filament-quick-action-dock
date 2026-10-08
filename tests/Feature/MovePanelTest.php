<?php

declare(strict_types=1);

use Filament\Actions\Action;
use HoceineEl\QuickActionDock\Livewire\QuickActionDock;
use Livewire\Livewire;

it('labels the corner buttons by their physical screen corner', function (string $locale, array $labels): void {
    app()->setLocale($locale);
    dock()->actions([Action::make('inbox')]);

    $component = Livewire::test(QuickActionDock::class);

    foreach ($labels as $corner => $label) {
        $component->assertSeeHtml("data-corner=\"{$corner}\"")->assertSeeHtml("aria-label=\"{$label}\"");
    }
})->with([
    'English' => ['en', ['top-left' => 'Top left', 'top-right' => 'Top right', 'bottom-left' => 'Bottom left', 'bottom-right' => 'Bottom right']],
    'French' => ['fr', ['top-left' => 'En haut à gauche', 'bottom-right' => 'En bas à droite']],
    'Arabic' => ['ar', ['top-left' => 'أعلى اليسار', 'bottom-right' => 'أسفل اليمين']],
]);
